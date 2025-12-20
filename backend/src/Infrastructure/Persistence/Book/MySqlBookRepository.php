<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Model\Book;
use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Infrastructure\Persistence\Book\Mappers\BookDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Book entity CRUD operations
 * Handles only Book table operations, no user relationships
 */
final class MySqlBookRepository implements BookRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE = 'book_statuses';
    private const STATUS_LINK_TABLE = 'book_has_statuses';
    private const STATUS_COLUMN = 'book_isbn';

    public function __construct(
        private readonly PDO $db,
        private readonly BookDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    public function findById(string $isbn): ?Book
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM books WHERE isbn = :isbn");
            $stmt->execute([':isbn' => $isbn]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return null;
            }

            // Get book statuses
            $data['user_statuses'] = $this->fetchStatusNames($isbn);

            return $this->mapper->toDomain($data);

        } catch (PDOException $e) {
            $this->logError('Error finding book by ISBN', $e, ['isbn' => $isbn]);
            throw new RuntimeException("Could not find book: " . $e->getMessage(), 0, $e);
        }
    }

    public function findAll(array $filters = []): array
    {
        try {
            $sql = "SELECT DISTINCT b.* FROM books b";
            $params = [];

            // Filter by user status if provided
            if (!empty($filters['userStatus'])) {
                $statusId = $this->getStatusId($filters['userStatus']);
                if ($statusId === null) {
                    return []; // Invalid status
                }
                $sql .= " JOIN book_has_statuses bhs ON b.isbn = bhs.book_isbn";
                $sql .= " WHERE bhs.status_id = :statusId";
                $params[':statusId'] = $statusId;
            }

            $sql .= " ORDER BY b.addedTimestamp DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $booksData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $books = [];
            foreach ($booksData as $data) {
                try {
                    $data['user_statuses'] = $this->fetchStatusNames($data['isbn']);
                    $books[] = $this->mapper->toDomain($data);
                } catch (\InvalidArgumentException $e) {
                    $this->logError('Error hydrating book', $e, ['isbn' => $data['isbn'] ?? 'unknown']);
                }
            }

            return $books;

        } catch (PDOException $e) {
            $this->logError('Error finding all books', $e, ['filters' => $filters]);
            throw new RuntimeException("Could not find books: " . $e->getMessage(), 0, $e);
        }
    }

    public function findByUserStatus(string $statusName): array
    {
        return $this->findAll(['userStatus' => $statusName]);
    }

    public function save(Book $book): void
    {
        $this->db->beginTransaction();
        try {
            $data = $this->mapper->toPersistence($book);

            // Format publication_year for DB (may be null)
            $publicationDate = null;
            if (isset($data['publication_year']) && $data['publication_year'] !== null) {
                $publicationDate = $data['publication_year'] . '-01-01';
            }

            $sql = "INSERT INTO books (isbn, title, author, publisher, publication_date, 
                    coverUrl, rating, pages, description, addedTimestamp, genres) 
                    VALUES (:isbn, :title, :author, :publisher, :publication_date, 
                    :coverUrl, :rating, :pages, :description, :addedTimestamp, :genres)
                    ON DUPLICATE KEY UPDATE 
                    title = VALUES(title), author = VALUES(author), publisher = VALUES(publisher), 
                    publication_date = VALUES(publication_date), coverUrl = VALUES(coverUrl), 
                    rating = VALUES(rating), pages = VALUES(pages), description = VALUES(description), 
                    addedTimestamp = VALUES(addedTimestamp), genres = VALUES(genres)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':isbn' => $data['isbn'],
                ':title' => $data['title'],
                ':author' => $data['author'],
                ':publisher' => $data['publisher'],
                ':publication_date' => $publicationDate,
                ':coverUrl' => $data['cover_url'],
                ':rating' => $data['rating'],
                ':pages' => $data['pages'],
                ':description' => $data['description'],
                ':addedTimestamp' => $data['addedTimestamp'],
                ':genres' => $data['genre'] ? json_encode([$data['genre']]) : null
            ]);

            $this->db->commit();
            $this->logInfo('Book saved successfully', ['isbn' => $data['isbn']]);

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error saving book', $e, ['book_isbn' => $book->getIsbn()->toString()]);
            throw new RuntimeException("Could not save book: " . $e->getMessage(), 0, $e);
        }
    }

    public function update(Book $book): void
    {
        try {
            $data = $this->mapper->toPersistence($book);

            $publicationDate = null;
            if (isset($data['publication_year']) && $data['publication_year'] !== null) {
                $publicationDate = $data['publication_year'] . '-01-01';
            }

            $sql = "UPDATE books SET 
                    title = :title, author = :author, publisher = :publisher, 
                    publication_date = :publication_date, coverUrl = :coverUrl, 
                    rating = :rating, pages = :pages, description = :description, 
                    genres = :genres
                    WHERE isbn = :isbn";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':isbn' => $data['isbn'],
                ':title' => $data['title'],
                ':author' => $data['author'],
                ':publisher' => $data['publisher'],
                ':publication_date' => $publicationDate,
                ':coverUrl' => $data['cover_url'],
                ':rating' => $data['rating'],
                ':pages' => $data['pages'],
                ':description' => $data['description'],
                ':genres' => $data['genre'] ? json_encode([$data['genre']]) : null
            ]);

            $this->logInfo('Book updated successfully', ['isbn' => $data['isbn']]);

        } catch (PDOException $e) {
            $this->logError('Error updating book', $e, ['book_isbn' => $book->getIsbn()->toString()]);
            throw new RuntimeException("Could not update book: " . $e->getMessage(), 0, $e);
        }
    }

    public function delete(string $isbn): bool
    {
        $this->db->beginTransaction();
        try {
            // Delete status relationships first
            $stmtDeleteStatuses = $this->db->prepare("DELETE FROM book_has_statuses WHERE book_isbn = :isbn");
            $stmtDeleteStatuses->execute([':isbn' => $isbn]);

            // Delete book
            $stmtDeleteBook = $this->db->prepare("DELETE FROM books WHERE isbn = :isbn");
            $stmtDeleteBook->execute([':isbn' => $isbn]);

            $deleted = $stmtDeleteBook->rowCount() > 0;
            $this->db->commit();

            if ($deleted) {
                $this->logInfo('Book deleted successfully', ['isbn' => $isbn]);
            }

            return $deleted;

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error deleting book', $e, ['isbn' => $isbn]);
            throw new RuntimeException("Could not delete book: " . $e->getMessage(), 0, $e);
        }
    }

    public function fetchAllowedStatuses(): array
    {
        return $this->getAllowedStatusesFromDb();
    }

    public function updateRating(string $isbn, float $rating): void
    {
        try {
            $stmt = $this->db->prepare("UPDATE books SET rating = :rating WHERE isbn = :isbn");
            $stmt->execute([
                ':rating' => $rating,
                ':isbn' => $isbn
            ]);

            $this->logInfo('Book rating updated', ['isbn' => $isbn, 'rating' => $rating]);

        } catch (PDOException $e) {
            $this->logError('Error updating book rating', $e, ['isbn' => $isbn]);
            throw new RuntimeException("Could not update book rating: " . $e->getMessage(), 0, $e);
        }
    }

    public function getTotalPages(string $isbn): int
    {
        try {
            $stmt = $this->db->prepare("SELECT pages FROM books WHERE isbn = :isbn");
            $stmt->execute([':isbn' => $isbn]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result && $result['pages'] !== null ? (int) $result['pages'] : 0;

        } catch (PDOException $e) {
            $this->logError('Error getting total pages', $e, ['isbn' => $isbn]);
            return 0;
        }
    }

    /**
     * Fetch status names for a book
     */
    private function fetchStatusNames(string $isbn): array
    {
        $sql = "SELECT s.name FROM book_statuses s 
                JOIN book_has_statuses bhs ON s.id = bhs.status_id 
                WHERE bhs.book_isbn = :isbn";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':isbn' => $isbn]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
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
