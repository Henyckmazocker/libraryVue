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

    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info($message, ['context' => $context]);
        }
    }

    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->debug($message, ['context' => $context]);
        }
    }

    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->warning($message, ['context' => $context]);
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
        try {
            $stmt = $this->db->query("SELECT name FROM book_statuses");
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            // Asegurar que siempre retorne un array
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            error_log("Error in fetchAllowedStatuses: " . $e->getMessage());
            // Retornar array vacío en caso de error
            return [];
        }
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
            $data['genres'] = isset($data['genres']) ? json_decode($data['genres'], true) : null;
            $userStatuses = $this->fetchBookStatusNames($data['isbn']);
            // Si no tiene userStatuses, asignamos un array vacío
            $data['userStatuses'] = is_array($userStatuses) ? $userStatuses : [];
            try {
                $data['allowedStatuses'] = $this->fetchAllowedStatuses();
                $books[] = Book::fromArray($data);
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
        $data['genres'] = isset($data['genres']) ? json_decode($data['genres'], true) : null;
        $data['userStatuses'] = $this->fetchBookStatusNames($isbn);
        // Si no tiene userStatuses, asignamos un array vacío
        if (!is_array($data['userStatuses']) || empty($data['userStatuses'])) {
            $data['userStatuses'] = [];
        }
        try {
            // Para findById no tenemos userId, así que omitimos tags y usamos arrays vacíos
            $data['tags'] = [];
            $data['allowedTags'] = [];
            $data['allowedStatuses'] = $this->fetchAllowedStatuses();
            return Book::fromArray($data);
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
            $sqlBook = "INSERT INTO books (isbn, title, author, publisher, publication_date, coverUrl, rating, pages, description, addedTimestamp, genres) " .
                   "VALUES (:isbn, :title, :author, :publisher, :publication_date, :coverUrl, :rating, :pages, :description, :addedTimestamp, :genres) " .
                   "ON DUPLICATE KEY UPDATE " .
                   "title = VALUES(title), author = VALUES(author), publisher = VALUES(publisher), " .
                   "publication_date = VALUES(publication_date), coverUrl = VALUES(coverUrl), " .
                   "rating = VALUES(rating), pages = VALUES(pages), description = VALUES(description), addedTimestamp = VALUES(addedTimestamp), genres = VALUES(genres)";
            
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
                ':addedTimestamp' => time(),
                ':genres' => $book->getGenres() ? json_encode($book->getGenres()) : null
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
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('DB Save Error', $e, [
                'book_data' => $book->toArray(),
                'operation' => 'save_book'
            ]);
            throw new RuntimeException("Could not save book and/or its book_statuses. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("DB Delete Error (MySqlBookRepository): " . $e->getMessage() . " ISBN: " . $isbn);
            throw new RuntimeException("Could not delete book. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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
                $this->updateUserBookStatuses((int)$userId, $isbn, $statuses);
            }

            $this->db->commit();
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("DB Error adding book to user (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("Could not add book to user. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error adding book to user (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("An unexpected error occurred while adding book to user: " . $e->getMessage(), 0, $e);
        }
    }

    public function removeBookFromUser(int $userId, string $isbn): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Remove reading progress history
            $stmtProgress = $this->db->prepare("DELETE FROM reading_progress_history WHERE user_id = :userId AND book_isbn = :isbn");
            $stmtProgress->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmtProgress->bindParam(':isbn', $isbn);
            $stmtProgress->execute();
            $progressDeleted = $stmtProgress->rowCount();

            // 2. Remove user book notes
            $stmtNotes = $this->db->prepare("DELETE FROM user_book_notes WHERE user_id = :userId AND book_isbn = :isbn");
            $stmtNotes->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmtNotes->bindParam(':isbn', $isbn);
            $stmtNotes->execute();
            $notesDeleted = $stmtNotes->rowCount();

            // 3. Remove user book tag assignments
            $stmtTags = $this->db->prepare("DELETE FROM user_book_tag_assignments WHERE user_id = :userId AND book_isbn = :isbn");
            $stmtTags->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmtTags->bindParam(':isbn', $isbn);
            $stmtTags->execute();
            $tagsDeleted = $stmtTags->rowCount();

            // 4. Remove user-specific statuses
            $stmtStatuses = $this->db->prepare("DELETE FROM user_book_statuses WHERE user_id = :userId AND book_isbn = :isbn");
            $stmtStatuses->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmtStatuses->bindParam(':isbn', $isbn);
            $stmtStatuses->execute();
            $statusesDeleted = $stmtStatuses->rowCount();

            // 5. Remove user-book relationship (main table)
            $stmt = $this->db->prepare("DELETE FROM user_books WHERE user_id = :userId AND book_isbn = :isbn");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->execute();
            $userBookDeleted = $stmt->rowCount() > 0;

            $this->db->commit();
            
            // Log deletion details for debugging
            $this->logInfo('Book removed from user', [
                'userId' => $userId,
                'isbn' => $isbn,
                'userBookDeleted' => $userBookDeleted,
                'progressRecordsDeleted' => $progressDeleted,
                'notesDeleted' => $notesDeleted,
                'tagsDeleted' => $tagsDeleted,
                'statusesDeleted' => $statusesDeleted
            ]);
            
            return $userBookDeleted;

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('DB Error removing book from user', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("Could not remove book from user. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error removing book from user', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("An unexpected error occurred while removing book from user: " . $e->getMessage(), 0, $e);
        }
    }

    public function findBooksByUser(int $userId, array $filters = []): array
    {
        try {
            // Ensure userId is actually an integer
            $userId = (int) $userId;
            
            $sql = "
                SELECT b.*, 
                       ub.added_at as user_added_at, 
                       ub.personal_rating as user_rating, 
                       ub.current_page,
                       ub.active_reading_session_id,
                       ub.total_sessions_completed,
                       ub.last_session_completed_at,
                       rs.session_number as current_session_number,
                       rs.started_at as session_started_at,
                       GROUP_CONCAT(bs.name SEPARATOR ', ') as user_statuses
                FROM books b
                INNER JOIN user_books ub ON b.isbn = ub.book_isbn
                LEFT JOIN user_book_statuses ubs ON b.isbn = ubs.book_isbn AND ubs.user_id = ub.user_id
                LEFT JOIN book_statuses bs ON ubs.status_id = bs.id
                LEFT JOIN reading_sessions rs ON ub.active_reading_session_id = rs.id
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

            $sql .= " GROUP BY b.isbn, b.title, b.author, b.publisher, b.publication_date, b.pages, b.rating, b.coverUrl, b.description, b.addedTimestamp, ub.added_at, ub.personal_rating, ub.current_page, ub.active_reading_session_id, ub.total_sessions_completed, ub.last_session_completed_at, rs.session_number, rs.started_at ORDER BY ub.added_at DESC";

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
                $data['currentPage'] = isset($data['current_page']) ? (int)$data['current_page'] : 0;
                $data['genres'] = isset($data['genres']) ? json_decode($data['genres'], true) : null;
                
                // Session data
                $data['active_reading_session_id'] = isset($data['active_reading_session_id']) ? (int)$data['active_reading_session_id'] : null;
                $data['total_sessions_completed'] = isset($data['total_sessions_completed']) ? (int)$data['total_sessions_completed'] : 0;
                $data['current_session_number'] = isset($data['current_session_number']) ? (int)$data['current_session_number'] : null;
                $data['session_started_at'] = $data['session_started_at'] ?? null;
                $data['last_session_completed_at'] = $data['last_session_completed_at'] ?? null;
                
                // Handle user statuses - convert comma-separated string to array
                $userStatusesString = $data['user_statuses'] ?? '';
                $data['userStatuses'] = !empty($userStatusesString) 
                    ? array_filter(explode(', ', $userStatusesString))
                    : [];
                
                // Remove the comma-separated field since we now have the array
                unset($data['user_statuses']);

                try {
                    $data['tags'] = $this->getBookTags($userId, $data['isbn']);
                    $data['allowedTags'] = $this->getAllowedTags($userId, $data['isbn']);
                    $allowedStatuses = $this->fetchAllowedStatuses();
                    $data['allowedStatuses'] = $allowedStatuses;
                    
                    error_log("DEBUG findBooksByUser - About to create Book with data: " . json_encode([
                        'isbn' => $data['isbn'],
                        'allowedStatuses_type' => gettype($data['allowedStatuses']),
                        'allowedStatuses_count' => is_array($data['allowedStatuses']) ? count($data['allowedStatuses']) : 'not_array',
                        'allowedStatuses_content' => $data['allowedStatuses']
                    ]));
                    
                    $books[] = Book::fromArray($data);
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

    public function updateUserBookStatuses(int|string $userId, string $isbn, array $statuses): void
    {
        // Ensure userId is actually an integer (can come as string from session or PDO fetch)
        $userId = (int) $userId;
        
        // Detectar si este método debe gestionar la transacción
        $weStartedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $weStartedTransaction = true;
        }
        
        try {
            
            // Obtener estados actuales antes de la modificación para detectar cambios
            $currentStatuses = $this->getUserBookStatuses($userId, $isbn);
            
            // VALIDACIÓN Y LIMPIEZA: Si 'read' está en los nuevos estados
            if (in_array('read', $statuses)) {
                // Si 'read' es nuevo (no estaba antes), validar que current_page == total_pages
                if (!in_array('read', $currentStatuses)) {
                    $bookInfo = $this->findById($isbn);
                    $currentPage = $this->getCurrentPage($userId, $isbn);
                    
                    if ($bookInfo && $bookInfo->getPages() && $currentPage < $bookInfo->getPages()) {
                        // Verificar si hay transacción activa (independiente de quién la inició)
                        if ($this->db->inTransaction()) {
                            $this->db->rollBack();
                        }
                        throw new \InvalidArgumentException(
                            "Debes marcar la última página ({$bookInfo->getPages()}) como leída antes de completar el libro. Página actual: {$currentPage}"
                        );
                    }
                }
                
                // SIEMPRE eliminar estados incompatibles cuando 'read' está presente
                $incompatibleStatuses = ['reading', 'to-read', 'paused', 're-reading'];
                $statusesBeforeClean = $statuses;
                $statuses = array_values(array_diff($statuses, $incompatibleStatuses));
                if (!in_array('read', $statuses)) {
                    $statuses[] = 'read';
                }
                
                // Log solo si hubo limpieza
                $removedFromNew = array_diff($statusesBeforeClean, $statuses);
                if (!empty($removedFromNew)) {
                    $this->logInfo("Limpiando estados incompatibles al tener 'read'", [
                        'userId' => $userId,
                        'isbn' => $isbn,
                        'removedFromNewStatuses' => array_values($removedFromNew),
                        'finalStatuses' => $statuses
                    ]);
                }
            }

            // LIMPIEZA: Si 'paused' o 'abandoned' están presentes, eliminar 'reading'
            if (in_array('paused', $statuses) || in_array('abandoned', $statuses)) {
                if (in_array('reading', $statuses)) {
                    $statusesBeforeClean = $statuses;
                    $statuses = array_values(array_diff($statuses, ['reading']));
                    
                    $this->logInfo("Eliminando 'reading' al pausar o abandonar", [
                        'userId' => $userId,
                        'isbn' => $isbn,
                        'statusesBefore' => $statusesBeforeClean,
                        'statusesAfter' => $statuses
                    ]);
                }
            }

            // LOGGING DIAGNÓSTICO: Antes del DELETE
            $this->logInfo("🔍 INICIO DELETE user_book_statuses", [
                'userId' => $userId,
                'isbn' => $isbn,
                'currentStatuses' => $currentStatuses,
                'newStatuses' => $statuses,
                'inTransaction' => $this->db->inTransaction(),
                'weStartedTransaction' => $weStartedTransaction
            ]);

            // Remove existing statuses for this user-book combination
            $deleteStmt = $this->db->prepare("DELETE FROM user_book_statuses WHERE user_id = :userId AND book_isbn = :isbn");
            $deleteStmt->bindParam(':userId', $userId);
            $deleteStmt->bindParam(':isbn', $isbn);
            $deleteStmt->execute();
            
            // LOGGING DIAGNÓSTICO: Después del DELETE
            $deletedRows = $deleteStmt->rowCount();
            $this->logInfo("✅ RESULTADO DELETE", [
                'rowsDeleted' => $deletedRows,
                'expectedToDelete' => count($currentStatuses)
            ]);

            // Add new statuses
            if (!empty($statuses)) {
                $this->logInfo("🔍 INICIO INSERT user_book_statuses", [
                    'statusesToInsert' => $statuses,
                    'count' => count($statuses)
                ]);
                
                $insertStmt = $this->db->prepare("
                    INSERT INTO user_book_statuses (user_id, book_isbn, status_id) 
                    VALUES (:userId, :isbn, :statusId)
                ");

                $insertedCount = 0;
                foreach ($statuses as $statusName) {
                    $statusId = $this->getStatusId($statusName);
                    if ($statusId !== null) {
                        $insertStmt->bindParam(':userId', $userId);
                        $insertStmt->bindParam(':isbn', $isbn);
                        $insertStmt->bindParam(':statusId', $statusId);
                        $insertStmt->execute();
                        $insertedCount++;
                        
                        $this->logDebug("  ➕ Insertado estado", [
                            'statusName' => $statusName,
                            'statusId' => $statusId
                        ]);
                    } else {
                        $this->logWarning("  ⚠️ Status ID NULL para", [
                            'statusName' => $statusName
                        ]);
                    }
                }
                
                $this->logInfo("✅ RESULTADO INSERT", [
                    'statusesInserted' => $insertedCount,
                    'statusNames' => $statuses
                ]);
            } else {
                $this->logWarning("⚠️ SKIP INSERT - Array de estados vacío", []);
            }
            
            // LÓGICA AUTOMÁTICA PRIORITARIA: Completar sesión si se agrega "read" (ANTES de actualizar estados)
            // Esto debe ejecutarse PRIMERO para que la sesión se complete antes de limpiar el estado 'reading'
            if (in_array('read', $statuses) && !in_array('read', $currentStatuses)) {
                try {
                    $activeSession = $this->getActiveReadingSession($userId, $isbn);
                    
                    $this->logInfo("Verificando sesión activa para completar al añadir 'read'", [
                        'userId' => $userId, 
                        'isbn' => $isbn,
                        'activeSessionFound' => $activeSession !== null,
                        'activeSessionData' => $activeSession
                    ]);
                    
                    if ($activeSession !== null) {
                        $currentPage = $this->getCurrentPage((int) $userId, $isbn);
                        $this->logInfo("Completando sesión automáticamente ANTES de cambiar estado a 'read'", [
                            'userId' => $userId, 
                            'isbn' => $isbn,
                            'sessionId' => $activeSession['id'],
                            'finalPage' => $currentPage
                        ]);
                        // Esto limpiará active_reading_session_id y incrementará total_sessions_completed
                        $this->updateSessionStatus($activeSession['id'], 'completed', $currentPage);
                        
                        $this->logInfo("Sesión completada - verificando resultado", [
                            'sessionId' => $activeSession['id']
                        ]);
                    } else {
                        $this->logWarning("No hay sesión activa para completar al añadir 'read'", [
                            'userId' => $userId, 
                            'isbn' => $isbn
                        ]);
                    }
                } catch (\Exception $sessionError) {
                    $this->logError('Error completando sesión automática para estado "read"', $sessionError, [
                        'userId' => $userId, 
                        'isbn' => $isbn
                    ]);
                }
            }
            
            // LÓGICA AUTOMÁTICA: Crear sesión de lectura si se agrega el estado "reading"
            if (in_array('reading', $statuses) && !in_array('reading', $currentStatuses)) {
                try {
                    // Verificar que no hay sesión activa
                    $activeSession = $this->getActiveReadingSession($userId, $isbn);
                    if ($activeSession === null) {
                        $this->logInfo("Creando sesión automáticamente al cambiar estado a 'reading'", [
                            'userId' => $userId, 
                            'isbn' => $isbn,
                            'previousStatuses' => $currentStatuses,
                            'newStatuses' => $statuses
                        ]);
                        $this->createReadingSession($userId, $isbn, null);
                    }
                } catch (\Exception $sessionError) {
                    // Log el error pero no abortar la transacción principal
                    $this->logError('Error creando sesión automática para estado "reading"', $sessionError, [
                        'userId' => $userId, 
                        'isbn' => $isbn
                    ]);
                }
            }

            // LÓGICA AUTOMÁTICA: Pausar sesión si se cambia a "paused"
            if (in_array('paused', $statuses) && !in_array('paused', $currentStatuses)) {
                try {
                    $activeSession = $this->getActiveReadingSession($userId, $isbn);
                    if ($activeSession !== null) {
                        $this->logInfo("Pausando sesión automáticamente al cambiar estado a 'paused'", [
                            'userId' => $userId, 
                            'isbn' => $isbn,
                            'sessionId' => $activeSession['id']
                        ]);
                        $this->updateSessionStatus($activeSession['id'], 'paused');
                    }
                } catch (\Exception $sessionError) {
                    $this->logError('Error pausando sesión automática', $sessionError, [
                        'userId' => $userId, 
                        'isbn' => $isbn
                    ]);
                }
            }

            // LÓGICA AUTOMÁTICA: Reactivar sesión si se quita "paused" y se mantiene "reading"
            if (!in_array('paused', $statuses) && in_array('paused', $currentStatuses) && in_array('reading', $statuses)) {
                try {
                    // Buscar sesión pausada para este libro
                    $pausedSessionSql = "SELECT * FROM reading_sessions 
                                        WHERE user_id = :user_id AND book_isbn = :isbn AND status = 'paused'
                                        ORDER BY started_at DESC LIMIT 1";
                    $pausedSessionStmt = $this->db->prepare($pausedSessionSql);
                    $pausedSessionStmt->execute(['user_id' => $userId, 'isbn' => $isbn]);
                    $pausedSession = $pausedSessionStmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($pausedSession !== null) {
                        $this->logInfo("Reactivando sesión pausada al quitar estado 'paused'", [
                            'userId' => $userId, 
                            'isbn' => $isbn,
                            'sessionId' => $pausedSession['id']
                        ]);
                        $this->updateSessionStatus($pausedSession['id'], 'active');
                    } else {
                        $this->logWarning("No se encontró sesión pausada para reactivar", [
                            'userId' => $userId, 
                            'isbn' => $isbn
                        ]);
                    }
                } catch (\Exception $sessionError) {
                    $this->logError('Error reactivando sesión pausada', $sessionError, [
                        'userId' => $userId, 
                        'isbn' => $isbn
                    ]);
                }
            }

            // LÓGICA AUTOMÁTICA: Abandonar sesión si se cambia a "abandoned"
            if (in_array('abandoned', $statuses) && !in_array('abandoned', $currentStatuses)) {
                try {
                    $activeSession = $this->getActiveReadingSession($userId, $isbn);
                    if ($activeSession !== null) {
                        $currentPage = $this->getCurrentPage((int) $userId, $isbn);
                        $this->logInfo("Abandonando sesión automáticamente al cambiar estado a 'abandoned'", [
                            'userId' => $userId, 
                            'isbn' => $isbn,
                            'sessionId' => $activeSession['id'],
                            'finalPage' => $currentPage
                        ]);
                        $this->updateSessionStatus($activeSession['id'], 'abandoned', $currentPage);
                    }
                } catch (\Exception $sessionError) {
                    $this->logError('Error abandonando sesión automática', $sessionError, [
                        'userId' => $userId, 
                        'isbn' => $isbn
                    ]);
                }
            }

            // LOGGING DIAGNÓSTICO: Antes del commit
            $this->logInfo("🔍 PRE-COMMIT estado", [
                'weStartedTransaction' => $weStartedTransaction,
                'inTransaction' => $this->db->inTransaction(),
                'finalStatuses' => $this->getUserBookStatuses($userId, $isbn)
            ]);
            
            if ($weStartedTransaction) {
                $this->db->commit();
                $this->logInfo("✅ COMMIT EXITOSO", []);
            } else {
                $this->logInfo("ℹ️ NO COMMIT (transacción externa)", []);
            }
            
            // LOGGING DIAGNÓSTICO: Verificación POST-COMMIT
            $statusesAfterCommit = $this->getUserBookStatuses($userId, $isbn);
            $this->logInfo("🔍 POST-OPERACIÓN estados en DB", [
                'statusesBeforeOperation' => $currentStatuses,
                'statusesRequestedToSet' => $statuses,
                'statusesActuallyInDB' => $statusesAfterCommit,
                'updateSuccessful' => ($statusesAfterCommit === $statuses || sort($statusesAfterCommit) === sort($statuses))
            ]);

        } catch (PDOException $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("DB Error updating user book statuses (MySqlBookRepository): " . $e->getMessage());
            throw new RuntimeException("Could not update user book statuses. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
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

    public function getUserBookStatuses(int|string $userId, string $isbn): array
    {
        $userId = (int) $userId;
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
        
        // Detectar si este método debe gestionar la transacción
        $weStartedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $weStartedTransaction = true;
        }
        
        try {
            
            // Si se está actualizando la página actual, manejar historial y consistencia
            if ($currentPage !== null) {
                // Obtener las páginas totales del libro para verificar si se completó
                $totalPages = $this->getTotalPages($isbn);
                
                // Obtener la última página de progreso real (del historial), no la actual de user_books
                $previousPage = $this->getLastProgressPage($userId, $isbn);
                
                // Obtener estados actuales para detectar transiciones
                $currentStatuses = $this->getUserBookStatuses($userId, $isbn);
                
                // LÓGICA AUTOMÁTICA: Detectar transiciones de progreso especiales
                $wasCompleted = ($previousPage >= $totalPages && $totalPages > 0); // Venía del 100%
                $wasAtStart = ($previousPage <= 0); // Venía del 0%
                $isStartingReading = ($currentPage > 0 && $wasAtStart); // 0% → X%
                $isReReading = ($currentPage > 0 && $wasCompleted); // 100% → X%
                
                // Si viene del 0% y empieza a leer, cambiar a "reading" y crear sesión
                if ($isStartingReading) {
                    try {
                        if (!in_array('reading', $currentStatuses)) {
                            $this->logInfo('Transición 0% → X% detectada - agregando estado "reading" automáticamente', [
                                'userId' => $userId, 
                                'isbn' => $isbn,
                                'previousPage' => $previousPage,
                                'currentPage' => $currentPage
                            ]);
                            
                            $newStatuses = array_unique(array_merge($currentStatuses, ['reading']));
                            $this->updateUserBookStatuses($userId, $isbn, $newStatuses);
                        }
                        
                        // Crear sesión de lectura si no hay una activa
                        $activeSession = $this->getActiveReadingSession($userId, $isbn);
                        if ($activeSession === null) {
                            $this->logInfo('Creando sesión automáticamente para transición 0% → X%', [
                                'userId' => $userId, 
                                'isbn' => $isbn,
                                'previousPage' => $previousPage,
                                'currentPage' => $currentPage
                            ]);
                            $this->createReadingSession($userId, $isbn, null);
                        }
                    } catch (\Exception $statusError) {
                        $this->logError('Error agregando estado "reading" en transición 0% → X%', $statusError, [
                            'userId' => $userId, 
                            'isbn' => $isbn
                        ]);
                    }
                }
                
                // NOTA: La lógica de re-reading ahora se maneja a través del sistema de sesiones
                // No agregamos automáticamente estados "re-reading" aquí
                /*
                if ($isReReading) {
                    try {
                        $statusesToAdd = [];
                        if (!in_array('re-reading', $currentStatuses)) {
                            $statusesToAdd[] = 're-reading';
                        }
                        if (!in_array('reading', $currentStatuses)) {
                            $statusesToAdd[] = 'reading';
                        }
                        
                        if (!empty($statusesToAdd)) {
                            $this->logInfo('Transición 100% → X% detectada - agregando estados para re-lectura', [
                                'userId' => $userId, 
                                'isbn' => $isbn,
                                'previousPage' => $previousPage,
                                'currentPage' => $currentPage,
                                'statusesToAdd' => $statusesToAdd
                            ]);
                            
                            $newStatuses = array_unique(array_merge($currentStatuses, $statusesToAdd));
                            $this->updateUserBookStatuses($userId, $isbn, $newStatuses);
                        }
                        
                        // Crear nueva sesión para re-lectura
                        $activeSession = $this->getActiveReadingSession($userId, $isbn);
                        if ($activeSession === null) {
                            $this->logInfo('Creando nueva sesión para re-lectura', [
                                'userId' => $userId, 
                                'isbn' => $isbn
                            ]);
                            $this->createReadingSession($userId, $isbn, null);
                        }
                    } catch (\Exception $statusError) {
                        $this->logError('Error manejando re-lectura en transición 100% → X%', $statusError, [
                            'userId' => $userId, 
                            'isbn' => $isbn
                        ]);
                    }
                }
                */
                
                // Solo registrar en el historial si hay un avance real
                if ($currentPage > $previousPage) {
                    try {
                        // Obtener sesión activa para vincular el progreso
                        $activeSession = $this->getActiveReadingSession($userId, $isbn);
                        $sessionId = $activeSession ? $activeSession['id'] : null;
                        
                        $this->addReadingProgressHistory($userId, $isbn, $currentPage, $previousPage, $sessionId);
                    } catch (\Exception $historyError) {
                        // Si falla el registro del historial, NO hacer rollback aquí
                        // Dejar que la excepción se propague y el catch externo maneje el rollback
                        $this->logError('Error registrando historial - no se actualizará currentPage', $historyError, [
                            'userId' => $userId, 
                            'isbn' => $isbn, 
                            'currentPage' => $currentPage, 
                            'previousPage' => $previousPage
                        ]);
                        throw new \RuntimeException('No se pudo actualizar el progreso: error en el historial de lectura. ' . $historyError->getMessage(), 0, $historyError);
                    }
                }
                
                $fields[] = 'current_page = :currentPage';
                $params[':currentPage'] = $currentPage;
                
                // Si el progreso llega al 100% (currentPage >= totalPages), completar sesión y marcar como "read"
                if ($totalPages > 0 && $currentPage >= $totalPages) {
                    try {
                        // PASO 1: Completar sesión activa si existe (ANTES de cambiar estados)
                        $activeSession = $this->getActiveReadingSession($userId, $isbn);
                        if ($activeSession !== null) {
                            $this->logInfo('Completando sesión automáticamente al alcanzar 100% de progreso', [
                                'userId' => $userId, 
                                'isbn' => $isbn,
                                'sessionId' => $activeSession['id'],
                                'currentPage' => $currentPage,
                                'totalPages' => $totalPages
                            ]);
                            $this->updateSessionStatus($activeSession['id'], 'completed', $currentPage);
                        }
                        
                        // PASO 2: Verificar si el usuario ya tiene el estado "read"
                        $updatedStatuses = $this->getUserBookStatuses($userId, $isbn);
                        if (!in_array('read', $updatedStatuses)) {
                            // Agregar estado "read" automáticamente (esto limpiará 'reading' internamente)
                            $this->logInfo('Progreso completado al 100% - agregando estado "read" automáticamente', [
                                'userId' => $userId, 
                                'isbn' => $isbn, 
                                'currentPage' => $currentPage, 
                                'totalPages' => $totalPages
                            ]);
                            
                            $newStatuses = array_unique(array_merge($updatedStatuses, ['read']));
                            // updateUserBookStatuses limpiará 'reading' y otros incompatibles automáticamente
                            $this->updateUserBookStatuses($userId, $isbn, $newStatuses);
                        }
                    } catch (\Exception $statusError) {
                        // Log el error pero no abortar la transacción principal
                        $this->logError('Error actualizando sesión/estado al completar libro', $statusError, [
                            'userId' => $userId, 
                            'isbn' => $isbn, 
                            'currentPage' => $currentPage, 
                            'totalPages' => $totalPages
                        ]);
                    }
                }
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
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw new \InvalidArgumentException('No hay campos para actualizar');
            }
            
            $sql = 'UPDATE user_books SET ' . implode(', ', $fields) . ' WHERE user_id = :userId AND book_isbn = :isbn';
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            
            // Confirmar transacción
            if ($weStartedTransaction) {
                $this->db->commit();
            }
            
        } catch (\PDOException $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('DB Error editando user_books', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new \RuntimeException('No se pudo editar user_books. DB Error: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e; // Re-lanzar otras excepciones
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

    /**
     * Elimina todos los tags asignados a un libro de usuario.
     */
    public function removeAllUserBookTags(int $userId, string $isbn): void
    {
        $sql = 'DELETE FROM user_book_tag_assignments WHERE user_id = :userId AND book_isbn = :isbn';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':isbn', $isbn);
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->logError('DB Error eliminando tags de user_book_tag_assignments', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new \RuntimeException('No se pudieron eliminar los tags del libro. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

        /**
         * Obtiene los tags asignados a un libro específico de un usuario.
         * Devuelve un array de tags (id, name, color).
         */
        public function getBookTags(int $userId, string $isbn): array
        {
            $sql = 'SELECT t.id, t.name, t.color FROM user_book_tag_assignments a
                    INNER JOIN user_book_tags t ON a.tag_id = t.id
                    WHERE a.user_id = :userId AND a.book_isbn = :isbn';
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':isbn', $isbn);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                $this->logError('DB Error obteniendo tags de libro', $e, ['userId' => $userId, 'isbn' => $isbn]);
                throw new \RuntimeException('No se pudieron obtener los tags del libro. DB Error: ' . $e->getMessage(), 0, $e);
            }
        }

        /**
         * Obtiene todos los tags creados por el usuario.
         * Devuelve un array de tags (id, name, color).
         */
        public function getUserBookTags(int $userId): array
        {
            $sql = 'SELECT id, name, color FROM user_book_tags WHERE user_id = :userId';
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                $this->logError('DB Error obteniendo todos los tags del usuario', $e, ['userId' => $userId]);
                throw new \RuntimeException('No se pudieron obtener los tags del usuario. DB Error: ' . $e->getMessage(), 0, $e);
            }
        }

        /**
         * Obtiene todos los tags permitidos para un usuario (alias de getUserBookTags).
         * Devuelve un array de tags (id, name, color).
         */
        public function getAllowedTags(int $userId, string $isbn = null): array
        {
            return $this->getUserBookTags($userId);
        }

        /**
         * Obtiene las notas de un libro por página para un usuario.
         * Devuelve un array de notas (id, page_number, note_text, note_type, is_private, created_at).
         */
        public function getBookNotesByPage(int $userId, string $isbn): array
        {
            $sql = 'SELECT id, page_number, note_text, note_type, is_private, created_at
                    FROM user_book_notes
                    WHERE user_id = :userId AND book_isbn = :isbn
                    ORDER BY page_number ASC, created_at DESC';
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':isbn', $isbn);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                $this->logError('DB Error obteniendo notas por página', $e, ['userId' => $userId, 'isbn' => $isbn]);
                throw new \RuntimeException('No se pudieron obtener las notas por página. DB Error: ' . $e->getMessage(), 0, $e);
            }
        }

    /**
     * Obtiene la página actual de un libro para un usuario.
     */
    public function getCurrentPage(int $userId, string $isbn): int
    {
        $sql = 'SELECT current_page FROM user_books WHERE user_id = :userId AND book_isbn = :isbn';
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':isbn', $isbn);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['current_page'] : 0;
        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo página actual', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return 0; // Valor por defecto en caso de error
        }
    }

    /**
     * Obtiene el número total de páginas de un libro.
     */
    public function getTotalPages(string $isbn): int
    {
        $sql = 'SELECT pages FROM books WHERE isbn = :isbn';
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':isbn', $isbn);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)($result['pages'] ?? 0) : 0;
        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo páginas totales del libro', $e, ['isbn' => $isbn]);
            return 0; // Valor por defecto en caso de error
        }
    }

    /**
     * Obtiene la página del último progreso registrado en el historial.
     * Si no hay historial, obtiene la página actual de user_books.
     */
    public function getLastProgressPage(int $userId, string $isbn): int
    {
        // Primero intentar obtener el último progreso del historial
        $sql = 'SELECT current_page 
                FROM reading_progress_history 
                WHERE user_id = :userId AND book_isbn = :isbn 
                ORDER BY logged_at DESC 
                LIMIT 1';
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':isbn', $isbn);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return (int)$result['current_page'];
            }
            
            // Si no hay historial, obtener de user_books
            return $this->getCurrentPage($userId, $isbn);
        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo última página de progreso', $e, ['userId' => $userId, 'isbn' => $isbn]);
            // Fallback a getCurrentPage en caso de error
            return $this->getCurrentPage($userId, $isbn);
        }
    }

    /**
     * Registra un nuevo progreso de lectura en el historial.
     * Solo registra si currentPage > previousPage.
     */
    public function addReadingProgressHistory(int $userId, string $isbn, int $currentPage, int $previousPage, ?int $sessionId = null): void
    {
        // Solo registrar si hay un avance real
        if ($currentPage <= $previousPage) {
            return;
        }

        // Si no se proporciona sessionId, intentar obtener la sesión activa
        if ($sessionId === null) {
            $activeSession = $this->getActiveReadingSession($userId, $isbn);
            $sessionId = $activeSession ? $activeSession['id'] : null;
        }

        $sql = 'INSERT INTO reading_progress_history (user_id, book_isbn, current_page, previous_page, reading_session_id) 
                VALUES (:userId, :isbn, :currentPage, :previousPage, :sessionId)';
        
        // Detectar si este método debe gestionar la transacción
        $weStartedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $weStartedTransaction = true;
        }
        
        try {
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':isbn', $isbn);
            $stmt->bindValue(':currentPage', $currentPage, PDO::PARAM_INT);
            $stmt->bindValue(':previousPage', $previousPage, PDO::PARAM_INT);
            $stmt->bindValue(':sessionId', $sessionId, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($weStartedTransaction) {
                $this->db->commit();
            }
        } catch (\PDOException $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('DB Error registrando historial de progreso', $e, [
                'userId' => $userId, 
                'isbn' => $isbn, 
                'currentPage' => $currentPage, 
                'previousPage' => $previousPage,
                'sessionId' => $sessionId
            ]);
            throw new \RuntimeException('No se pudo registrar el historial de progreso. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtiene el historial de progreso de lectura de un libro para un usuario.
     */
    public function getReadingProgressHistory(int $userId, string $isbn): array
    {
        $sql = 'SELECT id, current_page, previous_page, logged_at 
                FROM reading_progress_history 
                WHERE user_id = :userId AND book_isbn = :isbn 
                ORDER BY logged_at DESC';
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':isbn', $isbn);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo historial de progreso', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new \RuntimeException('No se pudo obtener el historial de progreso. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtiene estadísticas de páginas leídas por mes para un usuario.
     */
    public function getMonthlyPagesReadStats(int $userId, int $months = 12): array
    {
        $sql = 'SELECT 
                    DATE_FORMAT(logged_at, "%Y-%m") as month,
                    SUM(current_page - previous_page) as pages_read
                FROM reading_progress_history 
                WHERE user_id = :userId 
                    AND logged_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                GROUP BY DATE_FORMAT(logged_at, "%Y-%m")
                ORDER BY month ASC';
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':months', $months, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convertir a array asociativo con meses como claves
            $monthlyStats = [];
            foreach ($results as $row) {
                $monthlyStats[$row['month']] = (int)$row['pages_read'];
            }
            
            // Generar datos para todos los meses solicitados (incluso si son 0)
            $monthlyData = [];
            for ($i = $months - 1; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                $monthlyData[$month] = $monthlyStats[$month] ?? 0;
            }
            
            return $monthlyData;
        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo estadísticas mensuales de páginas', $e, ['userId' => $userId]);
            throw new \RuntimeException('No se pudieron obtener las estadísticas mensuales de páginas. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    // ===================================
    // MÉTODOS DE SESIONES DE LECTURA
    // ===================================

    public function createReadingSession(int|string $userId, string $isbn, ?int $sessionNumber = null, ?int $startPage = null): int
    {
        $userId = (int) $userId;
        
        // Detectar si este método debe gestionar la transacción
        $weStartedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $weStartedTransaction = true;
        }
        
        try {
            
            // Verificar que el libro existe
            if (!$this->findById($isbn)) {
                throw new \RuntimeException("Libro no encontrado con ISBN: $isbn");
            }
            
            $this->logInfo("Creando nueva sesión de lectura", ['userId' => $userId, 'isbn' => $isbn, 'sessionNumber' => $sessionNumber, 'startPage' => $startPage]);

            // Verificar que no hay una sesión activa para este libro y usuario
            $activeSession = $this->getActiveReadingSession($userId, $isbn);
            if ($activeSession !== null) {
                throw new \RuntimeException("Ya existe una sesión activa para este libro");
            }

            // Determinar página de inicio
            $initialPage = $startPage;
            if ($initialPage === null) {
                // Si no se especifica, usar página actual del usuario
                $initialPage = $this->getCurrentPage($userId, $isbn);
                if ($initialPage === 0) {
                    $initialPage = 1; // Empezar desde página 1 si no hay progreso
                }
            }
            
            // Determinar el número de sesión
            $actualSessionNumber = $sessionNumber;
            if ($actualSessionNumber === null) {
                // Obtener el próximo número de sesión
                $lastSessionSql = "SELECT COALESCE(MAX(session_number), 0) FROM reading_sessions WHERE user_id = :user_id AND book_isbn = :isbn";
                $lastSessionStmt = $this->db->prepare($lastSessionSql);
                $lastSessionStmt->execute(['user_id' => $userId, 'isbn' => $isbn]);
                $actualSessionNumber = $lastSessionStmt->fetchColumn() + 1;
            }

            $sql = "INSERT INTO reading_sessions (user_id, book_isbn, session_number, started_at, status) 
                    VALUES (:user_id, :isbn, :session_number, NOW(), 'active')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'isbn' => $isbn,
                'session_number' => $actualSessionNumber
            ]);

            $sessionId = (int) $this->db->lastInsertId();
            
            // Actualizar user_books con el ID de la sesión activa
            $updateUserBookSql = "UPDATE user_books 
                                 SET active_reading_session_id = :session_id 
                                 WHERE user_id = :user_id AND book_isbn = :isbn";
            $updateUserBookStmt = $this->db->prepare($updateUserBookSql);
            $updateUserBookStmt->execute([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'isbn' => $isbn
            ]);
            
            // Actualizar estado del libro a 'reading' si no lo tiene
            $this->updateUserBookStatuses($userId, $isbn, ['reading']);
            
            if ($weStartedTransaction) {
                $this->db->commit();
            }
            
            $this->logInfo("Sesión de lectura creada exitosamente", ['sessionId' => $sessionId]);
            return $sessionId;

        } catch (\PDOException $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('DB Error creando sesión de lectura', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new \RuntimeException('No se pudo crear la sesión de lectura. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getActiveReadingSession(int|string $userId, string $isbn): ?array
    {
        $userId = (int) $userId;
        try {
            $sql = "SELECT * FROM reading_sessions 
                    WHERE user_id = :user_id AND book_isbn = :isbn AND status = 'active'
                    ORDER BY started_at DESC LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'isbn' => $isbn]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;

        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo sesión activa', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new \RuntimeException('No se pudo obtener la sesión activa. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function completeReadingSession(int $sessionId, ?int $finalPage = null): void
    {
        try {
            $this->logInfo("Completando sesión de lectura", ['sessionId' => $sessionId, 'finalPage' => $finalPage]);

            // Obtener info de la sesión antes de actualizarla
            $sessionSql = "SELECT user_id, book_isbn FROM reading_sessions WHERE id = :session_id";
            $sessionStmt = $this->db->prepare($sessionSql);
            $sessionStmt->execute(['session_id' => $sessionId]);
            $sessionInfo = $sessionStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$sessionInfo) {
                throw new \RuntimeException("Sesión no encontrada");
            }

            $sql = "UPDATE reading_sessions 
                    SET completed_at = NOW(), final_page = :final_page, status = 'completed' 
                    WHERE id = :session_id AND status = 'active'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'session_id' => $sessionId,
                'final_page' => $finalPage
            ]);

            if ($stmt->rowCount() === 0) {
                $this->logWarning("No se encontró sesión activa para completar", ['sessionId' => $sessionId]);
                throw new \RuntimeException("No se encontró sesión activa para completar");
            }

            // Actualizar user_books: limpiar active_reading_session_id
            $updateUserBookSql = "UPDATE user_books SET active_reading_session_id = NULL WHERE user_id = :user_id AND book_isbn = :isbn";
            $updateUserBookStmt = $this->db->prepare($updateUserBookSql);
            $updateUserBookStmt->execute([
                'user_id' => $sessionInfo['user_id'],
                'isbn' => $sessionInfo['book_isbn']
            ]);

            $this->logInfo("Sesión de lectura completada exitosamente", ['sessionId' => $sessionId]);

        } catch (\PDOException $e) {
            $this->logError('DB Error completando sesión de lectura', $e, ['sessionId' => $sessionId]);
            throw new \RuntimeException('No se pudo completar la sesión de lectura. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Actualiza el estado de una sesión de lectura
     * @param int $sessionId ID de la sesión
     * @param string $status Nuevo estado ('active', 'completed', 'paused', 'abandoned')
     * @param int|null $finalPage Página final (opcional, para estados completed/abandoned)
     */
    public function updateSessionStatus(int $sessionId, string $status, ?int $finalPage = null): void
    {
        try {
            if (!$this->db) {
                $this->initializeDatabase();
            }

            $allowedStatuses = ['active', 'completed', 'paused', 'abandoned'];
            if (!in_array($status, $allowedStatuses)) {
                throw new \InvalidArgumentException("Estado de sesión inválido: $status");
            }

            // Obtener info de la sesión
            $sessionInfoSql = "SELECT user_id, book_isbn, status as current_status FROM reading_sessions WHERE id = :session_id";
            $sessionInfoStmt = $this->db->prepare($sessionInfoSql);
            $sessionInfoStmt->execute(['session_id' => $sessionId]);
            $sessionInfo = $sessionInfoStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$sessionInfo) {
                $this->logWarning("Sesión no encontrada para actualizar", ['sessionId' => $sessionId]);
                throw new \RuntimeException("Sesión no encontrada");
            }

            // Construir el SQL dinámicamente según el estado
            if (in_array($status, ['completed', 'abandoned'])) {
                $sql = "UPDATE reading_sessions 
                        SET status = :status,
                            completed_at = NOW(),
                            final_page = :final_page
                        WHERE id = :session_id";
            } else {
                $sql = "UPDATE reading_sessions 
                        SET status = :status,
                            completed_at = NULL,
                            final_page = :final_page
                        WHERE id = :session_id";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':session_id', $sessionId, \PDO::PARAM_INT);
            $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
            $stmt->bindValue(':final_page', $finalPage, \PDO::PARAM_INT);
            $stmt->execute();

            $rowsAffected = $stmt->rowCount();
            
            $this->logInfo("Actualización de reading_sessions ejecutada", [
                'sessionId' => $sessionId,
                'oldStatus' => $sessionInfo['current_status'],
                'newStatus' => $status,
                'finalPage' => $finalPage,
                'rowsAffected' => $rowsAffected
            ]);

            if ($rowsAffected === 0) {
                $this->logWarning("No se actualizó ninguna sesión - posible problema", [
                    'sessionId' => $sessionId, 
                    'status' => $status,
                    'sessionInfo' => $sessionInfo
                ]);
            }

            // Si se completa o abandona, limpiar active_reading_session_id en user_books
            if ($sessionInfo && in_array($status, ['completed', 'abandoned'])) {
                // Si se completa, incrementar contador de sesiones completadas
                if ($status === 'completed') {
                    $updateUserBookSql = "UPDATE user_books 
                                         SET active_reading_session_id = NULL,
                                             total_sessions_completed = total_sessions_completed + 1,
                                             last_session_completed_at = NOW()
                                         WHERE user_id = :user_id AND book_isbn = :isbn AND active_reading_session_id = :session_id";
                } else {
                    // Para 'abandoned'
                    $updateUserBookSql = "UPDATE user_books 
                                         SET active_reading_session_id = NULL 
                                         WHERE user_id = :user_id AND book_isbn = :isbn AND active_reading_session_id = :session_id";
                }
                
                $updateUserBookStmt = $this->db->prepare($updateUserBookSql);
                $updateUserBookStmt->execute([
                    'user_id' => $sessionInfo['user_id'],
                    'isbn' => $sessionInfo['book_isbn'],
                    'session_id' => $sessionId
                ]);
                
                $userBookRowsAffected = $updateUserBookStmt->rowCount();
                
                $this->logInfo("Actualización de user_books ejecutada", [
                    'userId' => $sessionInfo['user_id'],
                    'isbn' => $sessionInfo['book_isbn'],
                    'sessionId' => $sessionId,
                    'newStatus' => $status,
                    'rowsAffected' => $userBookRowsAffected
                ]);
                
                if ($userBookRowsAffected === 0) {
                    $this->logWarning("user_books no se actualizó - verificar active_reading_session_id", [
                        'userId' => $sessionInfo['user_id'],
                        'isbn' => $sessionInfo['book_isbn'],
                        'sessionId' => $sessionId
                    ]);
                }
            }
            
            // Si se reactiva (paused -> active), asegurar que active_reading_session_id esté establecido
            if ($status === 'active' && $sessionInfo['current_status'] === 'paused') {
                $updateUserBookSql = "UPDATE user_books 
                                     SET active_reading_session_id = :session_id
                                     WHERE user_id = :user_id AND book_isbn = :isbn";
                
                $updateUserBookStmt = $this->db->prepare($updateUserBookSql);
                $updateUserBookStmt->execute([
                    'user_id' => $sessionInfo['user_id'],
                    'isbn' => $sessionInfo['book_isbn'],
                    'session_id' => $sessionId
                ]);
                
                $this->logInfo("Sesión reactivada - active_reading_session_id restaurado", [
                    'userId' => $sessionInfo['user_id'],
                    'isbn' => $sessionInfo['book_isbn'],
                    'sessionId' => $sessionId,
                    'rowsAffected' => $updateUserBookStmt->rowCount()
                ]);
            }

            $this->logInfo("Session status updated", [
                'sessionId' => $sessionId,
                'newStatus' => $status,
                'finalPage' => $finalPage
            ]);
        } catch (\PDOException $e) {
            $this->logError('DB Error updating session status', $e, [
                'sessionId' => $sessionId,
                'status' => $status
            ]);
            throw new \RuntimeException("Could not update session status: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateReadingProgressWithSession(int $userId, string $isbn, int $currentPage, string $progressType = 'advance', ?string $notes = null): void
    {
        try {
            // Verificar que el libro existe
            if (!$this->findById($isbn)) {
                throw new \RuntimeException("Libro no encontrado con ISBN: $isbn");
            }
            
            $this->db->beginTransaction();

            // Obtener progreso anterior para detectar transiciones
            $previousPage = $this->getCurrentPage($userId, $isbn);
            $totalPages = $this->getTotalPages($isbn);
            
            // Detectar transiciones especiales
            $wasCompleted = ($previousPage >= $totalPages && $totalPages > 0);
            $wasAtStart = ($previousPage <= 0);
            $isStartingReading = ($currentPage > 0 && $wasAtStart);
            $isReReading = ($currentPage > 0 && $wasCompleted);
            
            // Obtener la sesión activa
            $activeSession = $this->getActiveReadingSession($userId, $isbn);
            $sessionId = $activeSession ? $activeSession['id'] : null;
            
            // LÓGICA AUTOMÁTICA: Crear sesión si no hay una activa y se está progresando
            if ($sessionId === null && $currentPage > 0) {
                try {
                    // Determinar qué estados agregar
                    $currentStatuses = $this->getUserBookStatuses($userId, $isbn);
                    $statusesToAdd = [];
                    
                    // NOTA: La lógica de re-reading se maneja mediante cambios de estado explícitos
                    // Solo agregamos "reading" si es necesario
                    if (!in_array('reading', $currentStatuses)) {
                        $statusesToAdd[] = 'reading';
                        
                        if ($isStartingReading) {
                            $this->logInfo('Creando sesión automáticamente - transición 0% → X%', [
                                'userId' => $userId, 
                                'isbn' => $isbn,
                                'previousPage' => $previousPage,
                                'currentPage' => $currentPage
                            ]);
                        } else {
                            $this->logInfo('Creando sesión automáticamente - progreso sin estado "reading"', [
                                'userId' => $userId, 
                                'isbn' => $isbn,
                                'currentPage' => $currentPage
                            ]);
                        }
                    }
                    
                    /*
                    // COMENTADO: Esta lógica causaba estados duplicados
                    if ($isStartingReading && !in_array('reading', $currentStatuses)) {
                        $statusesToAdd[] = 'reading';
                        $this->logInfo('Creando sesión automáticamente - transición 0% → X%', [
                            'userId' => $userId, 
                            'isbn' => $isbn,
                            'previousPage' => $previousPage,
                            'currentPage' => $currentPage
                        ]);
                    } elseif ($isReReading) {
                        if (!in_array('re-reading', $currentStatuses)) {
                            $statusesToAdd[] = 're-reading';
                        }
                        if (!in_array('reading', $currentStatuses)) {
                            $statusesToAdd[] = 'reading';
                        }
                        $this->logInfo('Creando sesión automáticamente - transición 100% → X% (re-lectura)', [
                            'userId' => $userId, 
                            'isbn' => $isbn,
                            'previousPage' => $previousPage,
                            'currentPage' => $currentPage
                        ]);
                    } elseif (!in_array('reading', $currentStatuses)) {
                        $statusesToAdd[] = 'reading';
                        $this->logInfo('Creando sesión automáticamente - progreso sin estado "reading"', [
                            'userId' => $userId, 
                            'isbn' => $isbn,
                            'currentPage' => $currentPage
                        ]);
                    }
                    */
                    
                    // Actualizar estados si es necesario
                    if (!empty($statusesToAdd)) {
                        $newStatuses = array_unique(array_merge($currentStatuses, $statusesToAdd));
                        $this->updateUserBookStatuses($userId, $isbn, $newStatuses);
                    }
                    
                    // Crear nueva sesión (no manejar transacción porque ya estamos en una)
                    $sessionId = $this->createReadingSession($userId, $isbn, null);
                    $this->logInfo('Sesión creada automáticamente', [
                        'userId' => $userId, 
                        'isbn' => $isbn,
                        'sessionId' => $sessionId,
                        'reason' => $isStartingReading ? 'start_reading' : ($isReReading ? 're_reading' : 'progress_update')
                    ]);
                    
                } catch (\Exception $sessionError) {
                    $this->logError('Error creando sesión automática en updateReadingProgressWithSession', $sessionError, [
                        'userId' => $userId, 
                        'isbn' => $isbn,
                        'currentPage' => $currentPage
                    ]);
                    // Continuar sin sesión en caso de error
                }
            }

            // Actualizar user_books (no manejar transacción porque ya estamos en una)
            $this->editUserBook($userId, $isbn, $currentPage, null, null, null);

            // Registrar en historial con sesión (puede ser null si no se pudo crear)
            $sql = "INSERT INTO reading_progress_history (user_id, book_isbn, session_id, page_number, recorded_at) 
                    VALUES (:user_id, :isbn, :session_id, :page_number, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'isbn' => $isbn,
                'session_id' => $sessionId,
                'page_number' => $currentPage
            ]);

            $this->db->commit();
            
            $this->logInfo("Progreso actualizado con sesión", [
                'userId' => $userId, 
                'isbn' => $isbn,
                'currentPage' => $currentPage,
                'sessionId' => $sessionId,
                'progressType' => $progressType,
                'notes' => $notes,
                'wasAutoSessionCreated' => ($activeSession === null && $sessionId !== null)
            ]);

        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error actualizando progreso con sesión', $e, [
                'userId' => $userId, 
                'isbn' => $isbn, 
                'currentPage' => $currentPage
            ]);
            throw new \RuntimeException('No se pudo actualizar el progreso con sesión. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getReadingSessionHistory(int $userId, string $isbn): array
    {
        try {
            $sql = "SELECT rs.*, 
                           TIMESTAMPDIFF(MINUTE, rs.started_at, rs.completed_at) as reading_minutes
                    FROM reading_sessions rs
                    WHERE rs.user_id = :user_id AND rs.book_isbn = :isbn
                    ORDER BY rs.started_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'isbn' => $isbn]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo historial de sesiones', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new \RuntimeException('No se pudo obtener el historial de sesiones. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getSessionProgress(int $sessionId): array
    {
        try {
            $sql = "SELECT rph.*, rs.start_page, rs.end_page as session_end_page
                    FROM reading_progress_history rph
                    JOIN reading_sessions rs ON rph.session_id = rs.id
                    WHERE rph.session_id = :session_id
                    ORDER BY rph.recorded_at ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['session_id' => $sessionId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo progreso de sesión', $e, ['sessionId' => $sessionId]);
            throw new \RuntimeException('No se pudo obtener el progreso de la sesión. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getUserActiveReadingSessions(int $userId): array
    {
        try {
            $sql = "SELECT rs.*, b.title, b.author, b.pages as total_pages
                    FROM reading_sessions rs
                    JOIN books b ON rs.book_isbn = b.isbn
                    WHERE rs.user_id = :user_id AND rs.status = 'active'
                    ORDER BY rs.started_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo sesiones activas del usuario', $e, ['userId' => $userId]);
            throw new \RuntimeException('No se pudieron obtener las sesiones activas. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function pauseReadingSession(int $sessionId, ?string $reason = null): void
    {
        try {
            // Obtener info de la sesión antes de actualizarla
            $sessionSql = "SELECT user_id, book_isbn FROM reading_sessions WHERE id = :session_id";
            $sessionStmt = $this->db->prepare($sessionSql);
            $sessionStmt->execute(['session_id' => $sessionId]);
            $sessionInfo = $sessionStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$sessionInfo) {
                throw new \RuntimeException("Sesión no encontrada");
            }

            $sql = "UPDATE reading_sessions 
                    SET status = 'paused', 
                        session_notes = CONCAT(COALESCE(session_notes, ''), '\nPausado: ', COALESCE(:reason, ''))
                    WHERE id = :session_id AND status = 'active'";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                'session_id' => $sessionId,
                'reason' => $reason
            ]);

            $success = $stmt->rowCount() > 0;
            
            if ($success) {
                // Actualizar estado del libro a 'paused' (cast explícito de user_id de PDO)
                $userId = (int)$sessionInfo['user_id'];
                $this->updateUserBookStatuses($userId, $sessionInfo['book_isbn'], ['paused']);
                
                $this->logInfo("Sesión pausada exitosamente", [
                    'sessionId' => $sessionId,
                    'reason' => $reason
                ]);
            } else {
                $this->logWarning("No se pudo pausar la sesión - posiblemente no estaba activa", [
                    'sessionId' => $sessionId
                ]);
                throw new \RuntimeException('No se pudo pausar la sesión de lectura. La sesión puede no existir o no estar activa.');
            }

        } catch (\PDOException $e) {
            $this->logError('DB Error pausando sesión', $e, [
                'sessionId' => $sessionId,
                'reason' => $reason
            ]);
            throw new \RuntimeException('No se pudo pausar la sesión. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function resumeReadingSession(int $sessionId): void
    {
        try {
            // Obtener información de la sesión antes de actualizarla
            $sessionSql = "SELECT user_id, book_isbn FROM reading_sessions WHERE id = :session_id";
            $sessionStmt = $this->db->prepare($sessionSql);
            $sessionStmt->execute(['session_id' => $sessionId]);
            $sessionInfo = $sessionStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$sessionInfo) {
                throw new \RuntimeException('Sesión no encontrada');
            }
            
            $sql = "UPDATE reading_sessions 
                    SET status = 'active' 
                    WHERE id = :session_id AND status = 'paused'";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute(['session_id' => $sessionId]);

            $success = $stmt->rowCount() > 0;
            
            if ($success) {
                // Actualizar estado del libro a 'reading' cuando se reanuda (cast explícito de user_id de PDO)
                $userId = (int)$sessionInfo['user_id'];
                $this->updateUserBookStatuses($userId, $sessionInfo['book_isbn'], ['reading']);
                
                $this->logInfo("Sesión reanudada exitosamente", ['sessionId' => $sessionId]);
            } else {
                $this->logWarning("No se pudo reanudar la sesión - posiblemente no estaba pausada", [
                    'sessionId' => $sessionId
                ]);
                throw new \RuntimeException('No se pudo reanudar la sesión de lectura. La sesión puede no existir o no estar pausada.');
            }

        } catch (\PDOException $e) {
            $this->logError('DB Error reanudando sesión', $e, ['sessionId' => $sessionId]);
            throw new \RuntimeException('No se pudo reanudar la sesión. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function abandonReadingSession(int $sessionId, ?string $reason = null): void
    {
        try {
            // Obtener información de la sesión antes de actualizarla
            $sessionSql = "SELECT user_id, book_isbn FROM reading_sessions WHERE id = :session_id";
            $sessionStmt = $this->db->prepare($sessionSql);
            $sessionStmt->execute(['session_id' => $sessionId]);
            $sessionInfo = $sessionStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$sessionInfo) {
                throw new \RuntimeException('Sesión no encontrada');
            }
            
            $sql = "UPDATE reading_sessions 
                    SET status = 'abandoned', 
                        session_notes = CONCAT(COALESCE(session_notes, ''), :reason),
                        completed_at = NOW()
                    WHERE id = :session_id AND status IN ('active', 'paused')";
            
            $stmt = $this->db->prepare($sql);
            $reasonText = $reason ? "\n[ABANDONADO: $reason]" : "\n[ABANDONADO]";
            $result = $stmt->execute([
                'session_id' => $sessionId,
                'reason' => $reasonText
            ]);

            $success = $stmt->rowCount() > 0;
            
            if ($success) {
                // Actualizar estado del libro a 'abandoned' (cast explícito de user_id de PDO)
                $userId = (int)$sessionInfo['user_id'];
                $this->updateUserBookStatuses($userId, $sessionInfo['book_isbn'], ['abandoned']);
                
                $this->logInfo("Sesión abandonada exitosamente", [
                    'sessionId' => $sessionId,
                    'reason' => $reason
                ]);
            } else {
                $this->logWarning("No se pudo abandonar la sesión - posiblemente no estaba activa o pausada", [
                    'sessionId' => $sessionId
                ]);
                throw new \RuntimeException('No se pudo abandonar la sesión de lectura. La sesión puede no existir o no estar en estado activo/pausado.');
            }

        } catch (\PDOException $e) {
            $this->logError('DB Error abandonando sesión', $e, [
                'sessionId' => $sessionId,
                'reason' => $reason
            ]);
            throw new \RuntimeException('No se pudo abandonar la sesión. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deleteReadingSession(int $sessionId, bool $keepHistory = true): void
    {
        try {
            $this->db->beginTransaction();
            
            // Verificar que la sesión existe y no está activa
            $sessionSql = "SELECT status FROM reading_sessions WHERE id = :session_id";
            $sessionStmt = $this->db->prepare($sessionSql);
            $sessionStmt->execute(['session_id' => $sessionId]);
            $session = $sessionStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$session) {
                throw new \RuntimeException("Sesión no encontrada");
            }
            
            if ($session['status'] === 'active') {
                throw new \RuntimeException("No se puede eliminar una sesión activa");
            }

            // Manejar historial de progreso según keepHistory
            if (!$keepHistory) {
                $sql1 = "DELETE FROM reading_progress_history WHERE session_id = :session_id";
                $stmt1 = $this->db->prepare($sql1);
                $stmt1->execute(['session_id' => $sessionId]);
            } else {
                // Desvincular pero mantener el historial
                $sql1 = "UPDATE reading_progress_history SET session_id = NULL WHERE session_id = :session_id";
                $stmt1 = $this->db->prepare($sql1);
                $stmt1->execute(['session_id' => $sessionId]);
            }

            // Eliminar la sesión
            $sql2 = "DELETE FROM reading_sessions WHERE id = :session_id";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute(['session_id' => $sessionId]);

            $this->db->commit();
            $this->logInfo("Sesión eliminada exitosamente", ['sessionId' => $sessionId, 'keepHistory' => $keepHistory]);

        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error eliminando sesión', $e, ['sessionId' => $sessionId]);
            throw new \RuntimeException('No se pudo eliminar la sesión. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getUserReadingSessions(int $userId, ?string $status = null): array
    {
        try {
            $sql = "SELECT rs.*, 
                           b.title, b.author, b.isbn,
                           ub.current_page,
                           b.pages as total_pages
                    FROM reading_sessions rs
                    INNER JOIN user_books ub ON rs.user_id = ub.user_id AND rs.book_isbn = ub.book_isbn
                    INNER JOIN books b ON rs.book_isbn = b.isbn
                    WHERE rs.user_id = :user_id";
            
            $params = ['user_id' => $userId];
            
            if ($status !== null) {
                $sql .= " AND rs.status = :status";
                $params['status'] = $status;
            }
            
            $sql .= " ORDER BY rs.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $sessions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->logInfo("Sesiones de usuario obtenidas exitosamente", [
                'userId' => $userId,
                'status' => $status,
                'count' => count($sessions)
            ]);
            
            return $sessions;

        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo sesiones del usuario', $e, [
                'userId' => $userId,
                'status' => $status
            ]);
            throw new \RuntimeException('No se pudieron obtener las sesiones del usuario. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getBookReadingSummary(int $userId, int $bookId): array
    {
        try {
            $sql = "SELECT * FROM v_book_reading_summary 
                    WHERE user_id = :user_id AND book_id = :book_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'book_id' => $bookId]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: [];

        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo resumen de lectura del libro', $e, ['userId' => $userId, 'bookId' => $bookId]);
            throw new \RuntimeException('No se pudo obtener el resumen de lectura. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getDetailedProgressHistory(int $userId, int $bookId): array
    {
        try {
            $sql = "SELECT * FROM v_detailed_progress_history 
                    WHERE user_id = :user_id AND book_id = :book_id
                    ORDER BY recorded_at ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'book_id' => $bookId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo historial detallado', $e, ['userId' => $userId, 'bookId' => $bookId]);
            throw new \RuntimeException('No se pudo obtener el historial detallado. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getUserReadingStats(int $userId): array
    {
        try {
            $sql = "SELECT * FROM v_user_reading_stats WHERE user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: [];

        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo estadísticas de usuario', $e, ['userId' => $userId]);
            throw new \RuntimeException('No se pudieron obtener las estadísticas del usuario. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getCurrentReadingSessions(int $userId): array
    {
        try {
            $sql = "SELECT * FROM v_current_reading_sessions WHERE user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo sesiones actuales', $e, ['userId' => $userId]);
            throw new \RuntimeException('No se pudieron obtener las sesiones actuales. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getNextSessionNumber(int $userId, string $isbn): int
    {
        try {
            $sql = "SELECT COALESCE(MAX(session_number), 0) + 1 FROM reading_sessions WHERE user_id = :user_id AND book_isbn = :isbn";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'isbn' => $isbn]);
            
            return (int) $stmt->fetchColumn();
            
        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo próximo número de sesión', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return 1;
        }
    }

    public function getReadingSessionStats(int $userId): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_sessions,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
                        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_sessions,
                        COUNT(CASE WHEN status = 'abandoned' THEN 1 END) as abandoned_sessions,
                        COUNT(CASE WHEN status = 'paused' THEN 1 END) as paused_sessions,
                        COUNT(DISTINCT book_id) as books_with_sessions,
                        AVG(CASE WHEN status = 'completed' AND end_page IS NOT NULL AND start_page IS NOT NULL 
                            THEN (end_page - start_page) END) as avg_pages_per_session,
                        AVG(CASE WHEN status = 'completed' AND completed_at IS NOT NULL AND started_at IS NOT NULL 
                            THEN TIMESTAMPDIFF(MINUTE, started_at, completed_at) END) as avg_minutes_per_session
                    FROM reading_sessions 
                    WHERE user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            
        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo estadísticas de sesiones', $e, ['userId' => $userId]);
            throw new \RuntimeException('No se pudieron obtener las estadísticas de sesiones. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function hasCompletedBook(int $userId, string $isbn): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM reading_sessions WHERE user_id = :user_id AND book_isbn = :isbn AND status = 'completed'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'isbn' => $isbn]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (\PDOException $e) {
            $this->logError('DB Error verificando libro completado', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return false;
        }
    }

    public function getBookCompletionCount(int $userId, string $isbn): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM reading_sessions WHERE user_id = :user_id AND book_isbn = :isbn AND status = 'completed'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'isbn' => $isbn]);
            
            return (int) $stmt->fetchColumn();
            
        } catch (\PDOException $e) {
            $this->logError('DB Error obteniendo contador de completado', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return 0;
        }
    }

    public function updateBookStatusesBasedOnSessions(int $userId, string $isbn): void
    {
        try {
            $this->db->beginTransaction();
            
            // Obtener información de sesiones
            $activeCount = $this->db->prepare("SELECT COUNT(*) FROM reading_sessions WHERE user_id = :user_id AND book_isbn = :isbn AND status = 'active'");
            $activeCount->execute(['user_id' => $userId, 'isbn' => $isbn]);
            $hasActiveSessions = $activeCount->fetchColumn() > 0;
            
            $completedCount = $this->db->prepare("SELECT COUNT(*) FROM reading_sessions WHERE user_id = :user_id AND book_isbn = :isbn AND status = 'completed'");
            $completedCount->execute(['user_id' => $userId, 'isbn' => $isbn]);
            $completionCount = $completedCount->fetchColumn();
            
            // Actualizar estados basados en sesiones
            $newStatus = 'to-read';
            if ($hasActiveSessions) {
                $newStatus = $completionCount > 0 ? 're-reading' : 'reading';
            } elseif ($completionCount > 0) {
                $newStatus = 'completed';
            }
            
            // Actualizar el estado del libro
            $updateSql = "UPDATE user_books SET status = :status, total_sessions_completed = :total_sessions WHERE user_id = :user_id AND book_id = :book_id";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                'status' => $newStatus,
                'total_sessions' => $completionCount,
                'user_id' => $userId,
                'book_id' => $bookId
            ]);
            
            $this->db->commit();
            
            $this->logInfo("Estados del libro actualizados basados en sesiones", [
                'userId' => $userId,
                'isbn' => $isbn,
                'newStatus' => $newStatus,
                'completionCount' => $completionCount
            ]);
            
        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error actualizando estados basados en sesiones', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new \RuntimeException('No se pudieron actualizar los estados del libro. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }


}
