<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use PDOException;
use RuntimeException;
use App\Domain\Repository\MovieRepositoryInterface;
use App\Domain\Model\Movie;
use Monolog\Logger;

class MySqlMovieRepository implements MovieRepositoryInterface
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

        /**
     * Actualiza el rating de una película por imdbID o isbn
     * @param string $id Puede ser imdbID o isbn
     * @param float $rating
     * @return void
     */
    public function updateMovieRating(string $id, float $rating): void
    {
        // Primero intentamos por imdbID, si no, por isbn
        $sql = "UPDATE movie SET rating = :rating WHERE isbn = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            // Movie not found - this is not necessarily an error
        }
    }

    /**
     * Obtiene todas las películas con filtros avanzados (título, estado)
     * @param array $filters ['title' => string|null, 'status' => string|null]
     * @return array
     */
    public function findAllWithFilters(array $filters = []): array
    {
        $sql = "SELECT DISTINCT m.* FROM movie m";
        $params = [];
        $joins = [];
        $wheres = [];

        // Filtro por estado
        if (!empty($filters['status'])) {
            $joins[] = "JOIN movie_has_statuses mhs ON m.isbn = mhs.movie_isbn";
            $joins[] = "JOIN movie_statuses s ON mhs.status_id = s.id";
            $wheres[] = "s.name = :statusName";
            $params[':statusName'] = $filters['status'];
        }

        // Filtro por título (búsqueda parcial, case-insensitive)
        if (!empty($filters['title'])) {
            $wheres[] = "LOWER(m.title) LIKE :title";
            $params[':title'] = '%' . strtolower($filters['title']) . '%';
        }

        if ($joins) {
            $sql .= ' ' . implode(' ', $joins);
        }
        if ($wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $wheres);
        }
        $sql .= " ORDER BY m.addedTimestamp DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $moviesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $movies = [];
        foreach ($moviesData as $data) {
            $data['rating'] = isset($data['rating']) ? (float)$data['rating'] : null;
            $data['addedTimestamp'] = isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null;
            $userStatuses = $this->fetchMovieStatusNames($data['isbn']);
            $data['userStatuses'] = is_array($userStatuses) ? $userStatuses : [];
            
            // Mapear el campo 'isbn' a 'id' ya que Movie espera 'id'
            $data['id'] = $data['isbn'];
            
            try {
                $allowedStatuses = $this->fetchAllowedStatuses();
                $movies[] = Movie::fromArray($data, $allowedStatuses);
            } catch (\InvalidArgumentException $e) {
                // Skip invalid movie data - error will be logged at higher level if needed
            }
        }
        return $movies;
    }

    private function getStatusId(string $statusName): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM movie_statuses WHERE name = :name");
        $stmt->bindParam(':name', $statusName);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }

    private function fetchMovieStatusNames(string $isbn): array
    {
        $sql = "SELECT s.name FROM movie_statuses s " .
               "JOIN movie_has_statuses mhs ON s.id = mhs.status_id " .
               "WHERE mhs.movie_isbn = :isbn";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':isbn', $isbn);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function fetchAllowedStatuses(): array
    {
        $stmt = $this->db->query("SELECT name FROM movie_statuses");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function findAll(array $filters = []): array
    {
        $sql = "SELECT DISTINCT m.* FROM movie m";
        $params = [];

        if (!empty($filters['userStatus'])) {
            $statusName = $filters['userStatus'];
            $statusId = $this->getStatusId($statusName);
            if ($statusId === null) {
                // Status not found - return empty array gracefully
                return [];
            }
            $sql .= " JOIN movie_has_statuses mhs ON m.isbn = mhs.movie_isbn";
            $sql .= " WHERE mhs.status_id = :statusId";
            $params[':statusId'] = $statusId;
        }
        $sql .= " ORDER BY m.addedTimestamp DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $moviesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $movies = [];

        foreach ($moviesData as $data) {
            $data['rating'] = isset($data['rating']) ? (float)$data['rating'] : null;
            $data['addedTimestamp'] = isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null;
            $userStatuses = $this->fetchMovieStatusNames($data['isbn']);
            $data['userStatuses'] = is_array($userStatuses) ? $userStatuses : [];
            
            // Mapear el campo 'isbn' a 'id' ya que Movie espera 'id'
            $data['id'] = $data['isbn'];
            
            try {
                $allowedStatuses = $this->fetchAllowedStatuses();
                $movies[] = Movie::fromArray($data, $allowedStatuses);
            } catch (\InvalidArgumentException $e) {
                // Skip invalid movie data - will be logged at higher level if needed
                continue;
            }
        }
        return $movies;
    }

    public function save(Movie $movie): void
    {
        $this->db->beginTransaction();
        try {
            $sqlMovie = "INSERT INTO movie (isbn, title, original_title, director, author, coverUrl, rating, description, addedTimestamp) " .
                   "VALUES (:isbn, :title, :original_title, :director, :author, :coverUrl, :rating, :description, :addedTimestamp) " .
                   "ON DUPLICATE KEY UPDATE " .
                   "title = VALUES(title), original_title = VALUES(original_title), director = VALUES(director), author = VALUES(author), coverUrl = VALUES(coverUrl), " .
                   "rating = VALUES(rating), description = VALUES(description), addedTimestamp = VALUES(addedTimestamp)";
            $stmtMovie = $this->db->prepare($sqlMovie);
            $stmtMovie->execute([
                ':isbn' => $movie->getId(),
                ':title' => $movie->getTitle(),
                ':original_title' => $movie->getOriginalTitle(),
                ':director' => $movie->getDirector(),
                ':author' => $movie->getDirector(), // For compatibility
                ':coverUrl' => $movie->getCoverUrl(),
                ':rating' => $movie->getRating(),
                ':description' => $movie->getDescription(),
                ':addedTimestamp' => time()
            ]);
            $isbn = $movie->getId();
            $userStatusNames = $movie->getUserStatuses();
            $stmtDeleteStatuses = $this->db->prepare("DELETE FROM movie_has_statuses WHERE movie_isbn = :isbn");
            $stmtDeleteStatuses->bindParam(':isbn', $isbn);
            $stmtDeleteStatuses->execute();
            if (empty($userStatusNames)) {
                throw new RuntimeException("Movie must have at least one user status to save. ISBN: " . $isbn);
            }
            $sqlInsertStatus = "INSERT INTO movie_has_statuses (movie_isbn, status_id) VALUES (:isbn, :status_id)";
            $stmtInsertStatus = $this->db->prepare($sqlInsertStatus);
            foreach ($userStatusNames as $statusName) {
                $statusId = $this->getStatusId($statusName);
                if ($statusId === null) {
                    throw new RuntimeException("Invalid status name '{$statusName}' encountered for movie ISBN {$isbn}. Not found in 'movie_statuses' table.");
                }
                $stmtInsertStatus->execute([':isbn' => $isbn, ':status_id' => $statusId]);
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Save Error', $e, [
                'movie_data' => $movie->toArray(),
                'operation' => 'save_movie'
            ]);
            throw new RuntimeException("Could not save movie and/or its statuses. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Generic Error during save', $e, [
                'movie_data' => $movie->toArray(),
                'operation' => 'save_movie'
            ]);
            throw new RuntimeException("An unexpected error occurred while saving movie and statuses: " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteByIsbn(string $isbn): bool
    {
        $this->db->beginTransaction();
        try {
            $stmtDeleteLinks = $this->db->prepare("DELETE FROM movie_has_statuses WHERE movie_isbn = :isbn");
            $stmtDeleteLinks->bindParam(':isbn', $isbn);
            $stmtDeleteLinks->execute();
            $stmtDeleteMovie = $this->db->prepare("DELETE FROM movie WHERE isbn = :isbn");
            $stmtDeleteMovie->bindParam(':isbn', $isbn);
            $stmtDeleteMovie->execute();
            $deleted = $stmtDeleteMovie->rowCount() > 0;
            $this->db->commit();
            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Delete Error', $e, [
                'isbn' => $isbn,
                'operation' => 'delete_by_isbn'
            ]);
            throw new RuntimeException("Could not delete movie. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Generic Error during delete', $e, [
                'isbn' => $isbn,
                'operation' => 'delete_by_isbn'
            ]);
            throw new RuntimeException("An unexpected error occurred while deleting movie: " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteById(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            $stmtDeleteLinks = $this->db->prepare("DELETE FROM movie_has_statuses WHERE movie_isbn = (SELECT isbn FROM movie WHERE id = :id)");
            $stmtDeleteLinks->bindParam(':id', $id);
            $stmtDeleteLinks->execute();
            $stmtDeleteMovie = $this->db->prepare("DELETE FROM movie WHERE id = :id");
            $stmtDeleteMovie->bindParam(':id', $id);
            $stmtDeleteMovie->execute();
            $deleted = $stmtDeleteMovie->rowCount() > 0;
            $this->db->commit();
            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Delete Error', $e, ['id' => $id, 'operation' => 'delete_by_id']);
            throw new RuntimeException("Could not delete movie. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Generic Error during delete', $e, ['id' => $id, 'operation' => 'delete_by_id']);
            throw new RuntimeException("An unexpected error occurred while deleting movie: " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteByName(string $title): bool
    {
        $this->db->beginTransaction();
        try {
            $stmtDeleteLinks = $this->db->prepare("DELETE FROM movie_has_statuses WHERE movie_isbn IN (SELECT isbn FROM movie WHERE title = :title)");
            $stmtDeleteLinks->bindParam(':title', $title);
            $stmtDeleteLinks->execute();
            $stmtDeleteMovie = $this->db->prepare("DELETE FROM movie WHERE title = :title");
            $stmtDeleteMovie->bindParam(':title', $title);
            $stmtDeleteMovie->execute();
            $deleted = $stmtDeleteMovie->rowCount() > 0;
            $this->db->commit();
            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Delete Error', $e, ['title' => $title, 'operation' => 'delete_by_title']);
            throw new RuntimeException("Could not delete movie. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Generic Error during delete', $e, ['title' => $title, 'operation' => 'delete_by_title']);
            throw new RuntimeException("An unexpected error occurred while deleting movie: " . $e->getMessage(), 0, $e);
        }
    }

    public function findById(string $isbn): ?array
    {
        $sql = "SELECT * FROM movie WHERE isbn = :isbn";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':isbn', $isbn);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        $data['rating'] = isset($data['rating']) ? (float)$data['rating'] : null;
        $data['addedTimestamp'] = isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null;
        $userStatuses = $this->fetchMovieStatusNames($isbn);
        $data['userStatuses'] = is_array($userStatuses) ? $userStatuses : [];
        $data['allowedStatuses'] = $this->fetchAllowedStatuses();
        return $data; // O mapear a Movie::fromArray si tienes un modelo Movie
    }

        /**
     * Actualiza los estados de usuario de una película por imdbID
     * @param string $imdbID
     * @param array $statuses
     * @return void
     */
    public function updateUserStatuses(string $imdbID, array $statuses): void
    {
        $db = $this->db;
        // Eliminar los estados actuales
        $deleteSql = "DELETE FROM movie_has_statuses WHERE movie_isbn = :imdbID";
        $stmt = $db->prepare($deleteSql);
        $stmt->bindParam(':imdbID', $imdbID);
        $stmt->execute();

        // Insertar los nuevos estados
        $insertSql = "INSERT INTO movie_has_statuses (movie_isbn, status_id) VALUES (:imdbID, :statusId)";
        $insertStmt = $db->prepare($insertSql);
        foreach ($statuses as $statusName) {
            // Obtener el ID del estado
            $statusId = $this->getStatusId($statusName);
            if ($statusId !== null) {
                $insertStmt->execute([':imdbID' => $imdbID, ':statusId' => $statusId]);
            }
        }
    }

    // User-related methods implementation
    public function addMovieToUser(int $userId, string $movieId, array $statuses = []): void
    {
        try {
            // Ensure userId is actually an integer
            $userId = (int) $userId;
            
            $this->db->beginTransaction();

            // Check if movie exists, if not create it
            $checkMovie = $this->db->prepare("SELECT isbn FROM movie WHERE isbn = :movieId");
            $checkMovie->bindParam(':movieId', $movieId);
            $checkMovie->execute();
            
            if (!$checkMovie->fetch()) {
                throw new RuntimeException("Movie with ID {$movieId} does not exist. Please add the movie first.");
            }

            // Add relationship between user and movie
            $stmt = $this->db->prepare("
                INSERT INTO user_movies (user_id, movie_isbn, added_at) 
                VALUES (:userId, :movieId, NOW())
                ON DUPLICATE KEY UPDATE added_at = NOW()
            ");
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':movieId', $movieId);
            $stmt->execute();

            // Add user-specific statuses if provided
            if (!empty($statuses)) {
                $this->updateUserMovieStatuses((int)$userId, $movieId, $statuses, false);
            }

            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error adding movie to user', $e, ['user_id' => $userId, 'movie_isbn' => $movieIsbn]);
            throw new RuntimeException("Could not add movie to user. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Error adding movie to user', $e, ['user_id' => $userId, 'movie_isbn' => $movieIsbn]);
            throw new RuntimeException("An unexpected error occurred while adding movie to user: " . $e->getMessage(), 0, $e);
        }
    }

    public function removeMovieFromUser(int $userId, string $movieId): bool
    {
        try {
            $this->db->beginTransaction();

            // Remove user-specific statuses
            $stmtStatuses = $this->db->prepare("DELETE FROM user_movie_statuses WHERE user_id = :userId AND movie_isbn = :movieId");
            $stmtStatuses->bindParam(':userId', $userId);
            $stmtStatuses->bindParam(':movieId', $movieId);
            $stmtStatuses->execute();

            // Remove user-movie relationship
            $stmt = $this->db->prepare("DELETE FROM user_movies WHERE user_id = :userId AND movie_isbn = :movieId");
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':movieId', $movieId);
            $stmt->execute();

            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();
            return $deleted;

        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error removing movie from user', $e, ['user_id' => $userId, 'movie_isbn' => $movieIsbn]);
            throw new RuntimeException("Could not remove movie from user. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Error removing movie from user', $e, ['user_id' => $userId, 'movie_isbn' => $movieIsbn]);
            throw new RuntimeException("An unexpected error occurred while removing movie from user: " . $e->getMessage(), 0, $e);
        }
    }

    public function findMoviesByUser(int $userId, array $filters = []): array
    {
        try {
            // Ensure userId is actually an integer
            $userId = (int) $userId;
            
            $sql = "
                SELECT m.*, um.added_at as user_added_at, um.personal_rating as user_rating,
                       GROUP_CONCAT(ms.name SEPARATOR ', ') as user_statuses
                FROM movie m
                INNER JOIN user_movies um ON m.isbn = um.movie_isbn
                LEFT JOIN user_movie_statuses ums ON m.isbn = ums.movie_isbn AND ums.user_id = um.user_id
                LEFT JOIN movie_statuses ms ON ums.status_id = ms.id
                WHERE um.user_id = :userId
            ";

            $params = [':userId' => $userId];

            // Apply filters
            if (isset($filters['status']) && !empty($filters['status'])) {
                $sql .= " AND ms.name = :status";
                $params[':status'] = $filters['status'];
            }

            if (isset($filters['title']) && !empty($filters['title'])) {
                $sql .= " AND m.title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }

            $sql .= " GROUP BY m.isbn, m.title, m.original_title, m.director, m.author, m.rating, m.coverUrl, m.description, m.addedTimestamp, um.added_at, um.personal_rating ORDER BY um.added_at DESC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $moviesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $movies = [];

            foreach ($moviesData as $data) {
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
                
                // Mapear el campo 'isbn' a 'id' ya que Movie espera 'id'
                $data['id'] = $data['isbn'];
                
                try {
                    $allowedStatuses = $this->fetchAllowedStatuses();
                    $movies[] = Movie::fromArray($data, $allowedStatuses);
                } catch (\InvalidArgumentException $e) {
                    // Skip invalid movie data
                }
            }

            return $movies;

        } catch (PDOException $e) {
            $this->logError('DB Error finding movies by user', $e, ['user_id' => $userId]);
            throw new RuntimeException("Could not find movies by user. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateUserMovieStatuses(int $userId, string $movieId, array $statuses, bool $manageTransaction = true): void
    {
        try {
            // Ensure userId is actually an integer
            $userId = (int) $userId;
            
            if ($manageTransaction) {
                $this->db->beginTransaction();
            }

            // Remove existing statuses for this user-movie combination
            $deleteStmt = $this->db->prepare("DELETE FROM user_movie_statuses WHERE user_id = :userId AND movie_isbn = :movieId");
            $deleteStmt->bindParam(':userId', $userId);
            $deleteStmt->bindParam(':movieId', $movieId);
            $deleteStmt->execute();

            // Add new statuses
            if (!empty($statuses)) {
                $insertStmt = $this->db->prepare("
                    INSERT INTO user_movie_statuses (user_id, movie_isbn, status_id) 
                    VALUES (:userId, :movieId, :statusId)
                ");

                foreach ($statuses as $statusName) {
                    $statusId = $this->getStatusId($statusName);
                    if ($statusId !== null) {
                        $insertStmt->bindParam(':userId', $userId);
                        $insertStmt->bindParam(':movieId', $movieId);
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
            $this->logError('DB Error updating user movie statuses', $e, ['movie_id' => $movieId, 'statuses' => $statuses, 'user_id' => $userId]);
            throw new RuntimeException("Could not update user movie statuses. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($manageTransaction) {
                $this->db->rollBack();
            }
            $this->logError('Error updating user movie statuses', $e, ['movie_id' => $movieId, 'statuses' => $statuses, 'user_id' => $userId]);
            throw new RuntimeException("An unexpected error occurred while updating user movie statuses: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateUserMovieRating(int $userId, string $movieId, ?float $rating): void
    {
        try {
            // Debug info removed
            
            $stmt = $this->db->prepare("
                UPDATE user_movies 
                SET personal_rating = :rating 
                WHERE user_id = :userId AND movie_isbn = :movieId
            ");
            
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':movieId', $movieId);
            $stmt->bindParam(':rating', $rating);
            $stmt->execute();

            $rowCount = $stmt->rowCount();
            // Debug info removed

            if ($rowCount === 0) {
                throw new RuntimeException("No user-movie relationship found to update rating. userId=$userId, movieId=$movieId");
            }

        } catch (PDOException $e) {
            $this->logError('DB Error updating user movie rating', $e, ['user_id' => $userId, 'movie_id' => $movieId, 'rating' => $rating]);
            throw new RuntimeException("Could not update user movie rating. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserMovieStatuses(int $userId, string $movieId): array
    {
        try {
            $sql = "
                SELECT ms.name 
                FROM movie_statuses ms
                INNER JOIN user_movie_statuses ums ON ms.id = ums.status_id
                WHERE ums.user_id = :userId AND ums.movie_isbn = :movieId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':movieId', $movieId);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        } catch (PDOException $e) {
            $this->logError('DB Error getting user movie statuses', $e, ['user_id' => $userId, 'movie_id' => $movieId]);
            throw new RuntimeException("Could not get user movie statuses. DB Error: " . $e->getMessage(), 0, $e);
        }
    }
    /**
     * Añade una nota a user_movie_notes para un usuario y película específica.
     */
    public function addUserMovieNote(int $userId, string $movieIsbn, string $noteText, string $noteType = 'note', bool $isPrivate = true): int
    {
        $sql = 'INSERT INTO user_movie_notes (user_id, movie_isbn, note_text, note_type, is_private) VALUES (:userId, :movieIsbn, :noteText, :noteType, :isPrivate)';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':movieIsbn', $movieIsbn);
            $stmt->bindValue(':noteText', $noteText);
            $stmt->bindValue(':noteType', $noteType);
            $stmt->bindValue(':isPrivate', $isPrivate ? 1 : 0, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            $this->logError('DB Error añadiendo nota a user_movie_notes', $e, ['userId' => $userId, 'movieIsbn' => $movieIsbn]);
            throw new \RuntimeException('No se pudo añadir la nota. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Añade un tag personalizado para un usuario en user_movie_tags.
     * Devuelve el id del tag creado o existente.
     */
    public function addUserMovieTag(int $userId, string $name, string $color = '#007bff'): int
    {
        $sql = 'INSERT INTO user_movie_tags (user_id, name, color) VALUES (:userId, :name, :color)';
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
                $stmt = $this->db->prepare('SELECT id FROM user_movie_tags WHERE user_id = :userId AND name = :name');
                $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':name', $name);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return (int)$row['id'];
            }
            $this->logError('DB Error añadiendo tag a user_movie_tags', $e, ['userId' => $userId, 'name' => $name]);
            throw new \RuntimeException('No se pudo añadir el tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Asigna un tag a una película de usuario en user_movie_tag_assignments.
     */
    public function assignUserMovieTag(int $userId, string $movieIsbn, int $tagId): void
    {
        $sql = 'INSERT INTO user_movie_tag_assignments (user_id, movie_isbn, tag_id) VALUES (:userId, :movieIsbn, :tagId)';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':movieIsbn', $movieIsbn);
            $stmt->bindValue(':tagId', $tagId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                // Ya asignado, no hacemos nada
                return;
            }
            $this->logError('DB Error asignando tag a user_movie_tag_assignments', $e, ['userId' => $userId, 'movieIsbn' => $movieIsbn, 'tagId' => $tagId]);
            throw new \RuntimeException('No se pudo asignar el tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }
    /**
     * Edita los campos de user_movies para un usuario y película específica.
     * Solo actualiza los campos que se pasan (null no modifica ese campo).
     */
    public function editUserMovie(int $userId, string $movieIsbn, ?float $personalRating = null, ?string $personalNotes = null, ?string $consumedAt = null): void
    {
        $fields = [];
        $params = [':userId' => $userId, ':movieIsbn' => $movieIsbn];
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
        $sql = 'UPDATE user_movies SET ' . implode(', ', $fields) . ' WHERE user_id = :userId AND movie_isbn = :movieIsbn';
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('No se encontró relación user-movie para editar');
            }
        } catch (\PDOException $e) {
            $this->logError('DB Error editando user_movies', $e, ['userId' => $userId, 'movieIsbn' => $movieIsbn]);
            throw new \RuntimeException('No se pudo editar user_movies. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }
}

