<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Model\Book;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Infrastructure\Persistence\Book\Mappers\BookDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use InvalidArgumentException;
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
            // Por el modelo Work/Edition, como ya hace `updateStatuses()` unas
            // lineas mas abajo. Consultaba `user_books`, que NO existe en el
            // esquema: la PDOException caia en el catch de abajo y devolvia
            // `false`, asi que `update_book_user_statuses` respondia
            // «Book not found in your library» con el libro delante.
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM user_book_editions ube
                INNER JOIN book_editions be ON be.edition_id = ube.edition_id
                WHERE ube.user_id = :userId
                  AND (be.isbn_13 = :isbn OR be.isbn_10 = :isbn2)
            ");
            $stmt->execute([':userId' => $userId, ':isbn' => $isbn, ':isbn2' => $isbn]);
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
            // First, find the edition_id from the ISBN
            $sql = "SELECT edition_id FROM book_editions WHERE isbn_13 = :isbn OR isbn_10 = :isbn2 LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':isbn' => $isbn, ':isbn2' => $isbn]);
            $edition = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$edition) {
                $this->db->rollBack();
                throw new RuntimeException("Edition not found for ISBN: {$isbn}");
            }
            
            $editionId = (int) $edition['edition_id'];
            
            $updates = [];
            $params = [':userId' => $userId, ':editionId' => $editionId];

            if (isset($data['current_page'])) {
                $updates[] = "current_page = :current_page";
                $params[':current_page'] = (int) $data['current_page'];
            }

            if (isset($data['personal_rating'])) {
                $updates[] = "edition_rating = :edition_rating";
                $params[':edition_rating'] = $data['personal_rating'] !== null ? (float) $data['personal_rating'] : null;
            }

            if (isset($data['personal_notes'])) {
                $updates[] = "personal_notes = :personal_notes";
                $params[':personal_notes'] = $data['personal_notes'];
            }

            if (isset($data['consumed_at'])) {
                $updates[] = "consumed_at = :consumed_at";
                $params[':consumed_at'] = $data['consumed_at'];
            }

            if (array_key_exists('ownership_format_id', $data)) {
                $updates[] = "ownership_format_id = :ownership_format_id";
                $params[':ownership_format_id'] = $data['ownership_format_id'] !== null ? (int) $data['ownership_format_id'] : null;
            }

            if (empty($updates)) {
                $this->db->rollBack();
                return;
            }

            $sql = "UPDATE user_book_editions SET " . implode(', ', $updates) . " WHERE user_id = :userId AND edition_id = :editionId";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Update statuses if provided
            if (isset($data['statuses'])) {
                $this->updateStatuses($userId, $isbn, $data['statuses']);
            }

            $this->db->commit();
            $this->logInfo('User book edited', ['userId' => $userId, 'isbn' => $isbn, 'editionId' => $editionId]);

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
            // First, find the edition_id from the ISBN
            $sql = "SELECT edition_id FROM book_editions WHERE isbn_13 = :isbn OR isbn_10 = :isbn2 LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':isbn' => $isbn, ':isbn2' => $isbn]);
            $edition = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$edition) {
                if ($weStartedTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw new RuntimeException("Edition not found for ISBN: {$isbn}");
            }
            
            $editionId = (int) $edition['edition_id'];
            
            // Find the user_book_edition id
            $sql = "SELECT id FROM user_book_editions WHERE user_id = :userId AND edition_id = :editionId LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId, ':editionId' => $editionId]);
            $userBookEdition = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$userBookEdition) {
                if ($weStartedTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw new RuntimeException("User book edition not found for user: {$userId}, edition: {$editionId}");
            }
            
            $userBookEditionId = (int) $userBookEdition['id'];

            // Validar lógica de estados excluyentes
            $this->validateStatusLogic($statuses);

            // Delete existing statuses
            $stmtDelete = $this->db->prepare("
                DELETE FROM user_book_statuses 
                WHERE user_edition_id = :userEditionId
            ");
            $stmtDelete->execute([':userEditionId' => $userBookEditionId]);

            // Insert new statuses
            if (!empty($statuses)) {
                $stmtInsert = $this->db->prepare("
                    INSERT INTO user_book_statuses (user_edition_id, status_id) 
                    VALUES (:userEditionId, :statusId)
                ");

                foreach ($statuses as $statusName) {
                    $statusId = $this->getStatusId($statusName);
                    if ($statusId !== null) {
                        $stmtInsert->execute([
                            ':userEditionId' => $userBookEditionId,
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

            $this->logInfo('User book statuses updated', ['userId' => $userId, 'isbn' => $isbn, 'editionId' => $editionId, 'statuses' => $statuses]);

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

    public function getTrendingBooks(int $limit = 20, int $daysWindow = 90, ?int $userId = null): array
    {
        try {
            $readingStatusId = $this->getStatusId('reading');
            $recentDays = 30;
            
            // Build user library check if userId provided
            $userLibraryCheck = $userId 
                ? "EXISTS(SELECT 1 FROM user_book_editions ube2 INNER JOIN book_editions be2 ON ube2.edition_id = be2.edition_id WHERE be2.work_id = w.work_id AND ube2.user_id = {$userId}) as is_in_user_library,"
                : "0 as is_in_user_library,";
            
            // Use string interpolation for INTERVAL and repeated parameters
            // Values are type-hinted as int, so they're safe
            $sql = "
                SELECT 
                    COALESCE(be.isbn_13, be.isbn_10) as isbn,
                    w.title as title,
                    JSON_UNQUOTE(JSON_EXTRACT(w.authors, '$[0].name')) as author,
                    COALESCE(be.cover_url_large, be.cover_url_medium, be.cover_url_small) as coverUrl,
                    be.publisher as publisher,
                    be.pages as pages,
                    {$userLibraryCheck}
                    COUNT(DISTINCT ube.user_id) as user_count,
                    AVG(ube.work_rating) as avg_rating,
                    SUM(CASE 
                        WHEN ube.added_at >= DATE_SUB(NOW(), INTERVAL {$recentDays} DAY) 
                        THEN 1 ELSE 0 
                    END) as recent_adds,
                    SUM(CASE 
                        WHEN ubs.status_id = {$readingStatusId}
                        THEN 1 ELSE 0 
                    END) as reading_count,
                    MAX(ube.added_at) as last_added,
                    -- Trending score calculation
                    (
                        (COUNT(DISTINCT ube.user_id) * 10) +
                        (COALESCE(AVG(ube.work_rating), 0) * 5) +
                        (SUM(CASE WHEN ube.added_at >= DATE_SUB(NOW(), INTERVAL {$recentDays} DAY) THEN 1 ELSE 0 END) * 15) +
                        (SUM(CASE WHEN ubs.status_id = {$readingStatusId} THEN 1 ELSE 0 END) * 8) -
                        (DATEDIFF(NOW(), MAX(ube.added_at)) * 0.1)
                    ) as trending_score
                FROM user_book_editions ube
                INNER JOIN book_editions be ON ube.edition_id = be.edition_id
                INNER JOIN book_works w ON be.work_id = w.work_id
                LEFT JOIN user_book_statuses ubs ON ube.id = ubs.user_edition_id
                WHERE ube.added_at >= DATE_SUB(NOW(), INTERVAL {$daysWindow} DAY)
                GROUP BY w.work_id, COALESCE(be.isbn_13, be.isbn_10), w.title, be.publisher, be.pages,
                         be.cover_url_large, be.cover_url_medium, be.cover_url_small
                HAVING user_count >= 1
                ORDER BY trending_score DESC
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logDebug('Trending books fetched', [
                'count' => count($results),
                'limit' => $limit,
                'daysWindow' => $daysWindow
            ]);

            return $results;

        } catch (PDOException $e) {
            $this->logError('Error getting trending books', $e, [
                'limit' => $limit,
                'daysWindow' => $daysWindow
            ]);
            throw new RuntimeException("Could not get trending books: " . $e->getMessage(), 0, $e);
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

    /**
     * Valida que los estados sean lógicamente compatibles
     *
     * Reglas:
     * - 'read' puede coexistir con cualquier otro estado (es histórico)
     * - Solo uno de: 'to-read', 'reading', 're-reading', 'paused', 'abandoned' (estado actual de lectura)
     * - Solo uno de: 'owned', 'want-to-buy' (estado de propiedad)
     *
     * @throws InvalidArgumentException si hay estados incompatibles
     */
    private function validateStatusLogic(array $statuses): void
    {
        // Categorías de estados
        $readingStates = ['to-read', 'reading', 're-reading', 'paused', 'abandoned'];
        $ownershipStates = ['owned', 'want-to-buy'];

        // Validar estados de lectura (solo uno permitido)
        $selectedReadingStates = array_intersect($statuses, $readingStates);
        if (count($selectedReadingStates) > 1) {
            throw new InvalidArgumentException(
                "Solo se permite un estado de actividad de lectura simultáneamente. " .
                "Recibidos: " . implode(', ', $selectedReadingStates)
            );
        }

        // Validar estados de propiedad (solo uno permitido)
        $selectedOwnershipStates = array_intersect($statuses, $ownershipStates);
        if (count($selectedOwnershipStates) > 1) {
            throw new InvalidArgumentException(
                "Solo se permite un estado de propiedad simultáneamente. " .
                "Recibidos: " . implode(', ', $selectedOwnershipStates)
            );
        }
    }
}
