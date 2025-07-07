<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Domain\Model\Book;
use App\Application\Domain\Repository\BookRepositoryInterface;
use App\Infrastructure\Database\DatabaseConnector; // To get the PDO instance
use PDO;
use PDOException;
use RuntimeException;

class MySqlBookRepository implements BookRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseConnector::getConnection();
    }

    private function getStatusId(string $statusName): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM book_statuses WHERE name = :name");
        $stmt->bindParam(':name', $statusName);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }

    private function fetchBookStatusNames(string $isbn): array
    {
        $sql = "SELECT s.name FROM book_statuses s " .
               "JOIN book_has_statuses bhs ON s.id = bhs.status_id " .
               "WHERE bhs.book_isbn = :isbn";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':isbn', $isbn);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    // Hacer público el método para los UseCases
    public function fetchAllowedStatuses(): array
    {
        $stmt = $this->db->query("SELECT name FROM book_statuses");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * @param array $filters Optional filters (e.g., ['userStatus' => 'read'])
     * @return Book[]
     */
    public function findAll(array $filters = []): array
    {
        $sql = "SELECT DISTINCT b.* FROM books b";
        $params = [];

        if (!empty($filters['userStatus'])) {
            $statusName = $filters['userStatus'];
            // It's good practice to ensure the status name is valid according to Book::ALLOWED_STATUSES
            // or that it actually exists in the 'statuses' table.
            if (!in_array($statusName, Book::ALLOWED_STATUSES, true)) {
                 error_log("findAll: Attempted to filter by an invalid or non-allowed status name: " . $statusName);
                 return []; // Or handle as an error, depending on desired strictness
            }
            $statusId = $this->getStatusId($statusName);
            if ($statusId === null) {
                error_log("findAll: Status name '{$statusName}' not found in book_statuses table.");
                return []; // No books can match a non-existent status ID
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
            $data['rating'] = isset($data['rating']) ? (float)$data['rating'] : null;
            $data['addedTimestamp'] = isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null;
            $userStatuses = $this->fetchBookStatusNames($data['isbn']);
            // Si no tiene userStatuses, asignamos un array vacío
            $data['userStatuses'] = is_array($userStatuses) ? $userStatuses : [];
            try {
                $allowedStatuses = $this->fetchAllowedStatuses();
                $books[] = Book::fromArray($data, $allowedStatuses);
            } catch (\InvalidArgumentException $e) {
                error_log("Error hydrating book from DB (findAll): " . $e->getMessage() . " Data: " . json_encode($data));
            }
        }
        return $books;
    }

    public function findByIsbn(string $isbn): ?Book
    {
        $stmt = $this->db->prepare("SELECT * FROM books WHERE isbn = :isbn");
        $stmt->bindParam(':isbn', $isbn);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }
        $data['rating'] = isset($data['rating']) ? (float)$data['rating'] : null;
        $data['addedTimestamp'] = isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null;
        $data['userStatuses'] = $this->fetchBookStatusNames($isbn);
        // Si no tiene userStatuses, asignamos un array vacío
        if (!is_array($data['userStatuses']) || empty($data['userStatuses'])) {
            $data['userStatuses'] = [];
        }
        try {
            $allowedStatuses = $this->fetchAllowedStatuses();
            return Book::fromArray($data, $allowedStatuses);
        } catch (\InvalidArgumentException $e) {
            error_log("Error hydrating book from DB (findByIsbn): " . $e->getMessage() . " Data: " . json_encode($data));
            throw new RuntimeException("Failed to hydrate book from DB due to inconsistent data: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Finds books by a specific user status.
     * @param string $status The user status to filter by.
     * @return Book[]
     */
    public function findByUserStatus(string $statusName): array
    {
        if (!in_array($statusName, Book::ALLOWED_STATUSES, true)) {
            error_log("findByUserStatus: Attempted to find books with an invalid or non-allowed status: " . $statusName);
            return [];
        }
        $statusId = $this->getStatusId($statusName);
        if ($statusId === null) {
            error_log("findByUserStatus: Status name '{$statusName}' not found in book_statuses table.");
            return [];
        }
        // Re-use findAll with the status name, which will internally convert to ID
        return $this->findAll(['userStatus' => $statusName]);
    }


    public function save(Book $book): void
    {
        $this->db->beginTransaction();
        try {
            $sqlBook = "INSERT INTO books (isbn, title, author, coverUrl, rating, addedTimestamp) " .
                   "VALUES (:isbn, :title, :author, :coverUrl, :rating, :addedTimestamp) " .
                   "ON DUPLICATE KEY UPDATE " .
                   "title = VALUES(title), author = VALUES(author), coverUrl = VALUES(coverUrl), " .
                   "rating = VALUES(rating), addedTimestamp = VALUES(addedTimestamp)";
            
            $stmtBook = $this->db->prepare($sqlBook);
            $stmtBook->execute([
                ':isbn' => $book->getIsbn(),
                ':title' => $book->getTitle(),
                ':author' => $book->getAuthor(),
                ':coverUrl' => $book->getCoverUrl(),
                ':rating' => $book->getRating(),
                ':addedTimestamp' => $book->getAddedTimestamp() ?? time()
            ]);

            $isbn = $book->getIsbn();
            $userStatusNames = $book->getUserStatuses(); // These are names like 'owned', 'read'

            $stmtDeleteStatuses = $this->db->prepare("DELETE FROM book_has_statuses WHERE book_isbn = :isbn");
            $stmtDeleteStatuses->bindParam(':isbn', $isbn);
            $stmtDeleteStatuses->execute();

            if (empty($userStatusNames)) {
                // Log detallado para depuración
                error_log("[BookRepository] Intento de guardar libro sin userStatuses. ISBN: $isbn. userStatusNames: " . json_encode($userStatusNames));
                // También mostrar los statuses permitidos en la tabla
                $allowed = $this->fetchAllowedStatuses();
                error_log("[BookRepository] Statuses permitidos en tabla: " . json_encode($allowed));
                throw new RuntimeException("Book must have at least one user status to save. ISBN: " . $isbn);
            }

            $sqlInsertStatus = "INSERT INTO book_has_statuses (book_isbn, status_id) VALUES (:isbn, :status_id)";
            $stmtInsertStatus = $this->db->prepare($sqlInsertStatus);
            
            foreach ($userStatusNames as $statusName) {
                $statusId = $this->getStatusId($statusName);
                if ($statusId === null) {
                    // Log detallado para depuración
                    error_log("[BookRepository] Status inválido recibido: '$statusName' para ISBN: $isbn. userStatusNames: " . json_encode($userStatusNames));
                    $allowed = $this->fetchAllowedStatuses();
                    error_log("[BookRepository] Statuses permitidos en tabla: " . json_encode($allowed));
                    throw new RuntimeException("Invalid status name '{$statusName}' encountered for book ISBN {$isbn}. Not found in 'book_statuses' table.");
                }
                $stmtInsertStatus->execute([':isbn' => $isbn, ':status_id' => $statusId]);
            }

            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Save Error (MySqlBookRepository): " . $e->getMessage() . " Book data: " . json_encode($book->toArray()));
            throw new RuntimeException("Could not save book and/or its book_statuses. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Generic Error during save (MySqlBookRepository): " . $e->getMessage() . " Book data: " . json_encode($book->toArray()));
            throw new RuntimeException("An unexpected error occurred while saving book and book_statuses: " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteByIsbn(string $isbn): bool
    {
        $this->db->beginTransaction();
        try {
            // Deleting from book_has_statuses will be handled by ON DELETE CASCADE if book is deleted from 'books' table.
            // However, explicit deletion can be kept if ON DELETE CASCADE is not universally relied upon or for clarity.
            $stmtDeleteLinks = $this->db->prepare("DELETE FROM book_has_statuses WHERE book_isbn = :isbn");
            $stmtDeleteLinks->bindParam(':isbn', $isbn);
            $stmtDeleteLinks->execute();

            $stmtDeleteBook = $this->db->prepare("DELETE FROM books WHERE isbn = :isbn");
            $stmtDeleteBook->bindParam(':isbn', $isbn);
            $stmtDeleteBook->execute();
            
            $deleted = $stmtDeleteBook->rowCount() > 0;
            $this->db->commit();
            return $deleted;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Delete Error (MySqlBookRepository): " . $e->getMessage() . " ISBN: " . $isbn);
            throw new RuntimeException("Could not delete book. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Generic Error during delete (MySqlBookRepository): " . $e->getMessage() . " ISBN: " . $isbn);
            throw new RuntimeException("An unexpected error occurred while deleting book: " . $e->getMessage(), 0, $e);
        }
    }
}