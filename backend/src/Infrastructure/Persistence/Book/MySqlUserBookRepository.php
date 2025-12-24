<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Model\Book;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Infrastructure\Persistence\Book\Mappers\BookDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for User-Book relationships
 * Handles user-specific book operations and statuses
 */
final class MySqlUserBookRepository implements UserBookRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE = 'book_statuses';
    private const STATUS_LINK_TABLE = 'user_book_statuses';
    private const STATUS_COLUMN = 'book_isbn';

    public function __construct(
        private readonly PDO $db,
        private readonly BookDataMapper $mapper,
        private readonly LoggerInterface $logger,
        private readonly MySqlBookRepository $bookRepository
    ) {}

    public function findByUser(int $userId, array $filters = []): array
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT b.*, 
                       ub.added_at as user_added_at, 
                       ub.personal_rating as user_rating, 
                       ub.current_page,
                       ub.personal_notes,
                       ub.consumed_at,
                       GROUP_CONCAT(DISTINCT bs.name SEPARATOR ', ') as user_statuses
                FROM books b
                INNER JOIN user_books ub ON b.isbn = ub.book_isbn
                LEFT JOIN user_book_statuses ubs ON b.isbn = ubs.book_isbn AND ubs.user_id = ub.user_id
                LEFT JOIN book_statuses bs ON ubs.status_id = bs.id
                WHERE ub.user_id = :userId
            ";

            $params = [':userId' => $userId];

            // Apply filters
            if (isset($filters['status']) && !empty($filters['status'])) {
                $sql .= " AND bs.name = :status";
                $params[':status'] = $filters['status'];
            }

            if (isset($filters['title']) && !empty($filters['title'])) {
                $sql .= " AND b.title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }

            if (isset($filters['genre']) && !empty($filters['genre'])) {
                $sql .= " AND b.genre = :genre";
                $params[':genre'] = $filters['genre'];
            }

            $sql .= " GROUP BY b.isbn, b.title, b.author, b.publisher, b.publication_date, b.coverUrl, b.rating, b.pages, b.description, b.addedTimestamp, b.genres, ub.added_at, ub.personal_rating, ub.current_page, ub.personal_notes, ub.consumed_at ORDER BY ub.added_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $booksData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch allowed statuses once for all books
            $allowedStatuses = $this->bookRepository->fetchAllowedStatuses();
            
            $this->logger->info('Fetched allowed statuses', [
                'allowedStatuses' => $allowedStatuses,
                'count' => count($allowedStatuses)
            ]);

            $books = [];
            foreach ($booksData as $data) {
                try {
                    // Let the mapper handle user_statuses conversion
                    // user_statuses comes as comma-separated string from GROUP_CONCAT
                    $data['allowedStatuses'] = $allowedStatuses;
                    
                    $this->logger->info('Processing book', [
                        'isbn' => $data['isbn'] ?? 'unknown',
                        'allowedStatuses' => $allowedStatuses
                    ]);
                    
                    $books[] = $this->mapper->toDomain($data);
                } catch (\InvalidArgumentException $e) {
                    $this->logError('Error hydrating book', $e, ['isbn' => $data['isbn'] ?? 'unknown']);
                }
            }

            return $books;

        } catch (PDOException $e) {
            $this->logError('Error finding books by user', $e, ['userId' => $userId]);
            throw new RuntimeException("Could not find books by user: " . $e->getMessage(), 0, $e);
        }
    }

    public function hasBook(int $userId, string $isbn): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM user_books 
                WHERE user_id = :userId AND book_isbn = :isbn
            ");
            $stmt->execute([':userId' => $userId, ':isbn' => $isbn]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result && (int) $result['count'] > 0;

        } catch (PDOException $e) {
            $this->logError('Error checking if user has book', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return false;
        }
    }

    public function add(int $userId, string $isbn, array $statuses = []): void
    {
        $this->db->beginTransaction();
        try {
            // Verify book exists
            $checkBook = $this->db->prepare("SELECT isbn FROM books WHERE isbn = :isbn");
            $checkBook->execute([':isbn' => $isbn]);

            if (!$checkBook->fetch()) {
                throw new RuntimeException("Book with ISBN {$isbn} does not exist. Please add the book first.");
            }

            // Add user-book relationship
            $stmt = $this->db->prepare("
                INSERT INTO user_books (user_id, book_isbn, added_at) 
                VALUES (:userId, :isbn, NOW())
                ON DUPLICATE KEY UPDATE added_at = NOW()
            ");
            $stmt->execute([':userId' => $userId, ':isbn' => $isbn]);

            // Add statuses if provided
            if (!empty($statuses)) {
                $this->updateStatuses($userId, $isbn, $statuses);
            }

            $this->db->commit();
            $this->logInfo('Book added to user', ['userId' => $userId, 'isbn' => $isbn]);

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error adding book to user', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("Could not add book to user: " . $e->getMessage(), 0, $e);
        }
    }

    public function remove(int $userId, string $isbn): bool
    {
        $this->db->beginTransaction();
        try {
            // Delete related data (notes, tags, progress, statuses)
            $this->db->prepare("DELETE FROM reading_progress_history WHERE user_id = :userId AND book_isbn = :isbn")
                ->execute([':userId' => $userId, ':isbn' => $isbn]);

            $this->db->prepare("DELETE FROM user_book_notes WHERE user_id = :userId AND book_isbn = :isbn")
                ->execute([':userId' => $userId, ':isbn' => $isbn]);

            $this->db->prepare("DELETE FROM user_book_tag_assignments WHERE user_id = :userId AND book_isbn = :isbn")
                ->execute([':userId' => $userId, ':isbn' => $isbn]);

            $this->db->prepare("DELETE FROM user_book_statuses WHERE user_id = :userId AND book_isbn = :isbn")
                ->execute([':userId' => $userId, ':isbn' => $isbn]);

            // Delete main relationship
            $stmt = $this->db->prepare("DELETE FROM user_books WHERE user_id = :userId AND book_isbn = :isbn");
            $stmt->execute([':userId' => $userId, ':isbn' => $isbn]);

            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();

            if ($deleted) {
                $this->logInfo('Book removed from user', ['userId' => $userId, 'isbn' => $isbn]);
            }

            return $deleted;

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error removing book from user', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("Could not remove book from user: " . $e->getMessage(), 0, $e);
        }
    }

    public function edit(int $userId, string $isbn, array $data): void
    {
        $this->db->beginTransaction();
        try {
            $updates = [];
            $params = [':userId' => $userId, ':isbn' => $isbn];

            if (isset($data['current_page'])) {
                $updates[] = "current_page = :current_page";
                $params[':current_page'] = (int) $data['current_page'];
            }

            if (isset($data['personal_rating'])) {
                $updates[] = "personal_rating = :personal_rating";
                $params[':personal_rating'] = $data['personal_rating'] !== null ? (float) $data['personal_rating'] : null;
            }

            if (isset($data['personal_notes'])) {
                $updates[] = "personal_notes = :personal_notes";
                $params[':personal_notes'] = $data['personal_notes'];
            }

            if (isset($data['consumed_at'])) {
                $updates[] = "consumed_at = :consumed_at";
                $params[':consumed_at'] = $data['consumed_at'];
            }

            if (empty($updates)) {
                $this->db->rollBack();
                return;
            }

            $sql = "UPDATE user_books SET " . implode(', ', $updates) . " WHERE user_id = :userId AND book_isbn = :isbn";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Update statuses if provided
            if (isset($data['statuses'])) {
                $this->updateStatuses($userId, $isbn, $data['statuses']);
            }

            $this->db->commit();
            $this->logInfo('User book edited', ['userId' => $userId, 'isbn' => $isbn]);

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error editing user book', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("Could not edit user book: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateStatuses(int $userId, string $isbn, array $statuses): void
    {
        $weStartedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $weStartedTransaction = true;
        }

        try {
            // Delete existing statuses
            $stmtDelete = $this->db->prepare("
                DELETE FROM user_book_statuses 
                WHERE user_id = :userId AND book_isbn = :isbn
            ");
            $stmtDelete->execute([':userId' => $userId, ':isbn' => $isbn]);

            // Insert new statuses
            if (!empty($statuses)) {
                $stmtInsert = $this->db->prepare("
                    INSERT INTO user_book_statuses (user_id, book_isbn, status_id) 
                    VALUES (:userId, :isbn, :statusId)
                ");

                foreach ($statuses as $statusName) {
                    $statusId = $this->getStatusId($statusName);
                    if ($statusId !== null) {
                        $stmtInsert->execute([
                            ':userId' => $userId,
                            ':isbn' => $isbn,
                            ':statusId' => $statusId
                        ]);
                    } else {
                        $this->logWarning('Invalid status name', ['status' => $statusName]);
                    }
                }
            }

            if ($weStartedTransaction) {
                $this->db->commit();
            }

            $this->logInfo('User book statuses updated', ['userId' => $userId, 'isbn' => $isbn, 'statuses' => $statuses]);

        } catch (PDOException $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error updating user book statuses', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("Could not update user book statuses: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateRating(int $userId, string $isbn, ?float $rating): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_books 
                SET personal_rating = :rating 
                WHERE user_id = :userId AND book_isbn = :isbn
            ");
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn,
                ':rating' => $rating
            ]);

            $this->logInfo('User book rating updated', ['userId' => $userId, 'isbn' => $isbn, 'rating' => $rating]);

        } catch (PDOException $e) {
            $this->logError('Error updating user book rating', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("Could not update user book rating: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserStatuses(int $userId, string $isbn): array
    {
        try {
            $sql = "SELECT bs.name 
                    FROM book_statuses bs
                    JOIN user_book_statuses ubs ON bs.id = ubs.status_id
                    WHERE ubs.user_id = :userId AND ubs.book_isbn = :isbn";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId, ':isbn' => $isbn]);

            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        } catch (PDOException $e) {
            $this->logError('Error getting user book statuses', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return [];
        }
    }

    public function getCurrentPage(int $userId, string $isbn): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT current_page 
                FROM user_books 
                WHERE user_id = :userId AND book_isbn = :isbn
            ");
            $stmt->execute([':userId' => $userId, ':isbn' => $isbn]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result && $result['current_page'] !== null ? (int) $result['current_page'] : 0;

        } catch (PDOException $e) {
            $this->logError('Error getting current page', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return 0;
        }
    }

    public function count(int $userId): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM user_books WHERE user_id = :userId");
            $stmt->execute([':userId' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? (int) $result['total'] : 0;

        } catch (PDOException $e) {
            $this->logError('Error counting user books', $e, ['userId' => $userId]);
            return 0;
        }
    }

    public function countByStatus(int $userId, string $statusName): int
    {
        try {
            $statusId = $this->getStatusId($statusName);
            if ($statusId === null) {
                return 0;
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT ubs.book_isbn) as total
                FROM user_book_statuses ubs
                WHERE ubs.user_id = :userId AND ubs.status_id = :statusId
            ");
            $stmt->execute([':userId' => $userId, ':statusId' => $statusId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? (int) $result['total'] : 0;

        } catch (PDOException $e) {
            $this->logError('Error counting books by status', $e, ['userId' => $userId, 'status' => $statusName]);
            return 0;
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    protected function getDatabase(): PDO
    {
        return $this->db;
    }

    protected function getStatusTableName(): string
    {
        return self::STATUS_TABLE;
    }

    protected function getEntityStatusTableName(): string
    {
        return self::STATUS_LINK_TABLE;
    }

    protected function getEntityIdColumnName(): string
    {
        return self::STATUS_COLUMN;
    }
}
