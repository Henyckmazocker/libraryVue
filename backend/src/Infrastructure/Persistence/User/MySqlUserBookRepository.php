<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\User;

use App\Domain\Repository\User\UserBookRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation of User-Book relationship repository
 */
class MySqlUserBookRepository implements UserBookRepositoryInterface
{
    use LoggableTrait;

    private const TABLE_USER_BOOKS = 'user_books';
    private const TABLE_BOOKS = 'books';
    private const TABLE_USER_BOOK_STATUSES = 'user_book_statuses';
    private const TABLE_BOOK_STATUSES = 'book_statuses';

    public function __construct(
        private readonly PDO $database,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function findByUser(int $userId, array $filters = []): array
    {
        try {
            $sql = "
                SELECT b.*, ub.added_at as user_added_at,
                       ub.personal_rating, ub.personal_notes, ub.consumed_at,
                       GROUP_CONCAT(bs.name SEPARATOR ', ') as user_statuses
                FROM " . self::TABLE_BOOKS . " b
                INNER JOIN " . self::TABLE_USER_BOOKS . " ub ON b.isbn = ub.book_isbn
                LEFT JOIN " . self::TABLE_USER_BOOK_STATUSES . " ubs 
                    ON b.isbn = ubs.book_isbn AND ubs.user_id = :userId
                LEFT JOIN " . self::TABLE_BOOK_STATUSES . " bs ON ubs.status_id = bs.id
                WHERE ub.user_id = :userId
            ";

            $params = [':userId' => $userId];

            // Apply status filter
            if (isset($filters['status']) && !empty($filters['status'])) {
                $sql .= " AND bs.name = :status";
                $params[':status'] = $filters['status'];
            }

            // Apply title filter
            if (isset($filters['title']) && !empty($filters['title'])) {
                $sql .= " AND b.title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }

            // Apply author filter
            if (isset($filters['author']) && !empty($filters['author'])) {
                $sql .= " AND b.authors LIKE :author";
                $params[':author'] = '%' . $filters['author'] . '%';
            }

            $sql .= " GROUP BY b.isbn, b.title, b.author, b.publisher, b.publication_date, b.coverUrl, b.rating, b.pages, b.description, b.addedTimestamp, b.genres, ub.added_at, ub.personal_rating, ub.personal_notes, ub.consumed_at ORDER BY ub.added_at DESC";

            $stmt = $this->database->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logInfo('Retrieved user books', [
                'user_id' => $userId,
                'count' => count($books),
                'filters' => $filters
            ]);

            return $books;
        } catch (PDOException $e) {
            $this->logError('Failed to get user books', $e, [
                'user_id' => $userId,
                'filters' => $filters
            ]);
            throw new RuntimeException(
                "Could not get user books. DB Error: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function hasBook(int $userId, string $isbn): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM " . self::TABLE_USER_BOOKS . " 
                    WHERE user_id = :userId AND book_isbn = :isbn";
            
            $stmt = $this->database->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn
            ]);
            
            $exists = $stmt->fetchColumn() > 0;

            $this->logDebug('Checked user book existence', [
                'user_id' => $userId,
                'isbn' => $isbn,
                'exists' => $exists
            ]);

            return $exists;
        } catch (PDOException $e) {
            $this->logError('Failed to check user book', $e, [
                'user_id' => $userId,
                'isbn' => $isbn
            ]);
            throw new RuntimeException(
                "Could not check user book. DB Error: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function count(int $userId): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM " . self::TABLE_USER_BOOKS . " 
                    WHERE user_id = :userId";
            
            $stmt = $this->database->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            
            $count = (int) $stmt->fetchColumn();

            $this->logDebug('Counted user books', [
                'user_id' => $userId,
                'count' => $count
            ]);

            return $count;
        } catch (PDOException $e) {
            $this->logError('Failed to count user books', $e, [
                'user_id' => $userId
            ]);
            throw new RuntimeException(
                "Could not count user books. DB Error: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function countByStatus(int $userId): array
    {
        try {
            $sql = "
                SELECT bs.name as status, COUNT(*) as count
                FROM " . self::TABLE_USER_BOOKS . " ub
                LEFT JOIN " . self::TABLE_USER_BOOK_STATUSES . " ubs 
                    ON ub.book_isbn = ubs.book_isbn AND ub.user_id = ubs.user_id
                LEFT JOIN " . self::TABLE_BOOK_STATUSES . " bs ON ubs.status_id = bs.id
                WHERE ub.user_id = :userId
                GROUP BY bs.name
            ";
            
            $stmt = $this->database->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logDebug('Counted user books by status', [
                'user_id' => $userId,
                'results' => $results
            ]);

            return $results;
        } catch (PDOException $e) {
            $this->logError('Failed to count user books by status', $e, [
                'user_id' => $userId
            ]);
            throw new RuntimeException(
                "Could not count user books by status. DB Error: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
