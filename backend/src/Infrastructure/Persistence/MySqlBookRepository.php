<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Model\Book;
use App\Domain\Repository\BookRepositoryInterface;
use PDO;
use PDOException;
use RuntimeException;
use Monolog\Logger;

class MySqlBookRepository implements BookRepositoryInterface
{
    private PDO $db;
    private ?Logger $logger;

    public function __construct(PDO $pdo, ?Logger $logger = null)
    {
        $this->db = $pdo;
        $this->logger = $logger;
    }

    private function logError(string $message, \Exception $e, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, [
                'exception' => [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ],
                'context' => $context
            ]);
        }
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
                // Status not found - return empty array gracefully
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

    public function findById(string $isbn): ?Book
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
            error_log("Error hydrating book from DB (findById): " . $e->getMessage() . " Data: " . json_encode($data));
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


    private function formatPublicationDate(?string $publicationDate): ?string
    {
        if ($publicationDate === null || trim($publicationDate) === '') {
            return null;
        }
        
        $publicationDate = trim($publicationDate);
        
        // Si es solo un año (4 dígitos), convertir a fecha del 1 de enero
        if (preg_match('/^\d{4}$/', $publicationDate)) {
            return $publicationDate . '-01-01';
        }
        
        // Si ya está en formato YYYY-MM-DD, devolverlo tal como está
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $publicationDate)) {
            return $publicationDate;
        }
        
        // Si está en formato YYYY-MM, agregar día 01
        if (preg_match('/^\d{4}-\d{2}$/', $publicationDate)) {
            return $publicationDate . '-01';
        }
        
        // Para otros formatos, intentar convertir o devolver null si no es válido
        try {
            $date = new \DateTime($publicationDate);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            error_log("Invalid publication date format: " . $publicationDate . " - " . $e->getMessage());
            return null;
        }
    }

    public function save(Book $book): void
    {
        $this->db->beginTransaction();
        try {
            $sqlBook = "INSERT INTO books (isbn, title, author, publisher, publication_date, coverUrl, rating, pages, description, addedTimestamp) " .
                   "VALUES (:isbn, :title, :author, :publisher, :publication_date, :coverUrl, :rating, :pages, :description, :addedTimestamp) " .
                   "ON DUPLICATE KEY UPDATE " .
                   "title = VALUES(title), author = VALUES(author), publisher = VALUES(publisher), " .
                   "publication_date = VALUES(publication_date), coverUrl = VALUES(coverUrl), " .
                   "rating = VALUES(rating), pages = VALUES(pages), description = VALUES(description), addedTimestamp = VALUES(addedTimestamp)";
            
            $stmtBook = $this->db->prepare($sqlBook);
            $stmtBook->execute([
                ':isbn' => $book->getIsbn(),
                ':title' => $book->getTitle(),
                ':author' => $book->getAuthor(),
                ':publisher' => $book->getPublisher(),
                ':publication_date' => $this->formatPublicationDate($book->getPublicationDate()),
                ':coverUrl' => $book->getCoverUrl(),
                ':rating' => $book->getRating(),
                ':pages' => $book->getPages(),
                ':description' => $book->getDescription(),
                ':addedTimestamp' => time()
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
            $this->logError('DB Save Error', $e, [
                'book_data' => $book->toArray(),
                'operation' => 'save_book'
            ]);
            throw new RuntimeException("Could not save book and/or its book_statuses. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Generic Error during save', $e, [
                'book_data' => $book->toArray(),
                'operation' => 'save_book'
            ]);
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

    // User-related methods implementation
    public function addBookToUser(int $userId, string $isbn, array $statuses = []): void
    {
        try {
            // Ensure userId is actually an integer
            $userId = (int) $userId;
            
            $this->db->beginTransaction();

            // Check if book exists, if not create it
            $checkBook = $this->db->prepare("SELECT isbn FROM books WHERE isbn = :isbn");
            $checkBook->bindParam(':isbn', $isbn);
            $checkBook->execute();
            
            if (!$checkBook->fetch()) {
                throw new RuntimeException("Book with ISBN {$isbn} does not exist. Please add the book first.");
            }

            // Add relationship between user and book
            $stmt = $this->db->prepare("
                INSERT INTO user_books (user_id, book_isbn, added_at) 
                VALUES (:userId, :isbn, NOW())
                ON DUPLICATE KEY UPDATE added_at = NOW()
            ");
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->execute();

            // Add user-specific statuses if provided
            if (!empty($statuses)) {
                $this->updateUserBookStatuses((int)$userId, $isbn, $statuses, false);
            }

            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Error adding book to user (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("Could not add book to user. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Error adding book to user (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("An unexpected error occurred while adding book to user: " . $e->getMessage(), 0, $e);
        }
    }

    public function removeBookFromUser(int $userId, string $isbn): bool
    {
        try {
            $this->db->beginTransaction();

            // Remove user-specific statuses
            $stmtStatuses = $this->db->prepare("DELETE FROM user_book_statuses WHERE user_id = :userId AND book_isbn = :isbn");
            $stmtStatuses->bindParam(':userId', $userId);
            $stmtStatuses->bindParam(':isbn', $isbn);
            $stmtStatuses->execute();

            // Remove user-book relationship
            $stmt = $this->db->prepare("DELETE FROM user_books WHERE user_id = :userId AND book_isbn = :isbn");
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->execute();

            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();
            return $deleted;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Error removing book from user (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("Could not remove book from user. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Error removing book from user (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("An unexpected error occurred while removing book from user: " . $e->getMessage(), 0, $e);
        }
    }

    public function findBooksByUser(int $userId, array $filters = []): array
    {
        try {
            // Ensure userId is actually an integer
            $userId = (int) $userId;
            
            $sql = "
                SELECT b.*, ub.added_at as user_added_at, ub.personal_rating as user_rating,
                       GROUP_CONCAT(bs.name SEPARATOR ', ') as user_statuses
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

            $sql .= " GROUP BY b.isbn, b.title, b.author, b.publisher, b.publication_date, b.pages, b.rating, b.coverUrl, b.description, b.addedTimestamp, ub.added_at, ub.personal_rating ORDER BY ub.added_at DESC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $booksData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $books = [];

            foreach ($booksData as $data) {
                // Convert data types properly
                $data['rating'] = isset($data['rating']) ? (float)$data['rating'] : null;
                $data['addedTimestamp'] = isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null;
                
                // Handle user statuses - convert comma-separated string to array
                $userStatusesString = $data['user_statuses'] ?? '';
                $data['userStatuses'] = !empty($userStatusesString) 
                    ? array_filter(explode(', ', $userStatusesString))
                    : [];
                
                // Remove the comma-separated field since we now have the array
                unset($data['user_statuses']);
                
                try {
                    $allowedStatuses = $this->fetchAllowedStatuses();
                    $books[] = Book::fromArray($data, $allowedStatuses);
                } catch (\InvalidArgumentException $e) {
                    error_log("Error hydrating book from DB (findBooksByUser): " . $e->getMessage() . " Data: " . json_encode($data));
                }
            }

            return $books;

        } catch (PDOException $e) {
            error_log("DB Error finding books by user (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("Could not find books by user. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateUserBookStatuses(int $userId, string $isbn, array $statuses, bool $manageTransaction = true): void
    {
        try {
            // Ensure userId is actually an integer
            $userId = (int) $userId;
            
            if ($manageTransaction) {
                $this->db->beginTransaction();
            }

            // Remove existing statuses for this user-book combination
            $deleteStmt = $this->db->prepare("DELETE FROM user_book_statuses WHERE user_id = :userId AND book_isbn = :isbn");
            $deleteStmt->bindParam(':userId', $userId);
            $deleteStmt->bindParam(':isbn', $isbn);
            $deleteStmt->execute();

            // Add new statuses
            if (!empty($statuses)) {
                $insertStmt = $this->db->prepare("
                    INSERT INTO user_book_statuses (user_id, book_isbn, status_id) 
                    VALUES (:userId, :isbn, :statusId)
                ");

                foreach ($statuses as $statusName) {
                    $statusId = $this->getStatusId($statusName);
                    if ($statusId !== null) {
                        $insertStmt->bindParam(':userId', $userId);
                        $insertStmt->bindParam(':isbn', $isbn);
                        $insertStmt->bindParam(':statusId', $statusId);
                        $insertStmt->execute();
                    }
                }
            }

            if ($manageTransaction) {
                $this->db->commit();
            }

        } catch (PDOException $e) {
            if ($manageTransaction) {
                $this->db->rollBack();
            }
            error_log("DB Error updating user book statuses (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("Could not update user book statuses. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($manageTransaction) {
                $this->db->rollBack();
            }
            error_log("Error updating user book statuses (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("An unexpected error occurred while updating user book statuses: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateUserBookRating(int $userId, string $isbn, ?float $rating): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_books 
                SET personal_rating = :rating 
                WHERE user_id = :userId AND book_isbn = :isbn
            ");
            
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->bindParam(':rating', $rating);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException("No user-book relationship found to update rating");
            }

        } catch (PDOException $e) {
            error_log("DB Error updating user book rating (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("Could not update user book rating. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserBookStatuses(int $userId, string $isbn): array
    {
        try {
            $sql = "
                SELECT bs.name 
                FROM book_statuses bs
                INNER JOIN user_book_statuses ubs ON bs.id = ubs.status_id
                WHERE ubs.user_id = :userId AND ubs.book_isbn = :isbn
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        } catch (PDOException $e) {
            error_log("DB Error getting user book statuses (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("Could not get user book statuses. DB Error: " . $e->getMessage(), 0, $e);
        }
    }
    /**
     * Edita los campos de user_books para un usuario y libro específico.
     * Solo actualiza los campos que se pasan (null no modifica ese campo).
     */
    public function editUserBook(int $userId, string $isbn, ?int $currentPage = null, ?float $personalRating = null, ?string $personalNotes = null, ?string $consumedAt = null): void
    {
        $fields = [];
        $params = [':userId' => $userId, ':isbn' => $isbn];
        if ($currentPage !== null) {
            $fields[] = 'current_page = :currentPage';
            $params[':currentPage'] = $currentPage;
        }
        if ($personalRating !== null) {
            $fields[] = 'personal_rating = :personalRating';
            $params[':personalRating'] = $personalRating;
        }
        if ($personalNotes !== null) {
            $fields[] = 'personal_notes = :personalNotes';
            $params[':personalNotes'] = $personalNotes;
        }
        if ($consumedAt !== null) {
            $fields[] = 'consumed_at = :consumedAt';
            $params[':consumedAt'] = $consumedAt;
        }
        if (empty($fields)) {
            throw new \InvalidArgumentException('No hay campos para actualizar');
        }
        $sql = 'UPDATE user_books SET ' . implode(', ', $fields) . ' WHERE user_id = :userId AND book_isbn = :isbn';
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('No se encontró relación user-book para editar');
            }
        } catch (\PDOException $e) {
            $this->logError('DB Error editando user_books', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new \RuntimeException('No se pudo editar user_books. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }
    /**
     * Añade una nota a user_book_notes para un usuario y libro específico.
     */
    public function addUserBookNote(int $userId, string $isbn, int $pageNumber, string $noteText, string $noteType = 'note', bool $isPrivate = true): int
    {
        $sql = 'INSERT INTO user_book_notes (user_id, book_isbn, page_number, note_text, note_type, is_private) VALUES (:userId, :isbn, :pageNumber, :noteText, :noteType, :isPrivate)';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':isbn', $isbn);
            $stmt->bindValue(':pageNumber', $pageNumber, PDO::PARAM_INT);
            $stmt->bindValue(':noteText', $noteText);
            $stmt->bindValue(':noteType', $noteType);
            $stmt->bindValue(':isPrivate', $isPrivate ? 1 : 0, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            $this->logError('DB Error añadiendo nota a user_book_notes', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new \RuntimeException('No se pudo añadir la nota. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }
    /**
     * Añade un tag personalizado para un usuario en user_book_tags.
     * Devuelve el id del tag creado o existente.
     */
    public function addUserBookTag(int $userId, string $name, string $color = '#007bff'): int
    {
        $sql = 'INSERT INTO user_book_tags (user_id, name, color) VALUES (:userId, :name, :color)';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':color', $color);
            $stmt->execute();
            return (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            // Si el tag ya existe, obtenemos su id
            if ($e->getCode() === '23000') { // Duplicate entry
                $stmt = $this->db->prepare('SELECT id FROM user_book_tags WHERE user_id = :userId AND name = :name');
                $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':name', $name);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return (int)$row['id'];
            }
            $this->logError('DB Error añadiendo tag a user_book_tags', $e, ['userId' => $userId, 'name' => $name]);
            throw new \RuntimeException('No se pudo añadir el tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Asigna un tag a un libro de usuario en user_book_tag_assignments.
     */
    public function assignUserBookTag(int $userId, string $isbn, int $tagId): void
    {
        $sql = 'INSERT INTO user_book_tag_assignments (user_id, book_isbn, tag_id) VALUES (:userId, :isbn, :tagId)';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':isbn', $isbn);
            $stmt->bindValue(':tagId', $tagId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                // Ya asignado, no hacemos nada
                return;
            }
            $this->logError('DB Error asignando tag a user_book_tag_assignments', $e, ['userId' => $userId, 'isbn' => $isbn, 'tagId' => $tagId]);
            throw new \RuntimeException('No se pudo asignar el tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }
}