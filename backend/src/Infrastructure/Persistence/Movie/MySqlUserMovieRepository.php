<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Movie;

use App\Domain\Model\Movie;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Infrastructure\Persistence\Movie\Mappers\MovieDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for User-Movie relationships
 * Handles user-specific movie operations and statuses
 */
final class MySqlUserMovieRepository implements UserMovieRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE = 'movie_statuses';
    private const STATUS_LINK_TABLE = 'movie_has_statuses';
    private const STATUS_COLUMN = 'movie_isbn';

    public function __construct(
        private readonly PDO $db,
        private readonly MovieDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    public function findByUser(int $userId, array $filters = []): array
    {
        try {
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

            if (isset($filters['status']) && !empty($filters['status'])) {
                $sql .= " AND ms.name = :status";
                $params[':status'] = $filters['status'];
            }

            if (isset($filters['title']) && !empty($filters['title'])) {
                $sql .= " AND m.title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }

            $sql .= " GROUP BY m.isbn, m.title, m.original_title, m.director, m.author, m.rating, m.coverUrl, m.description, m.addedTimestamp, m.genres, um.added_at, um.personal_rating, um.personal_notes, um.consumed_at ORDER BY um.added_at DESC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $moviesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->mapper->toDomainCollection($moviesData);

        } catch (PDOException $e) {
            $this->logError('DB Error finding movies by user', $e, ['user_id' => $userId]);
            throw new RuntimeException("Could not find movies by user. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function hasMovie(int $userId, string $movieId): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM user_movies WHERE user_id = :userId AND movie_isbn = :movieId";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieId
            ]);
            
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logError('DB Error checking user movie', $e, [
                'user_id' => $userId,
                'movie_id' => $movieId
            ]);
            throw new RuntimeException("Could not check if user has movie. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function add(
        int $userId,
        string $movieIsbn,
        array $statuses = [],
        ?float $personalRating = null,
        ?string $personalNotes = null,
        ?string $consumedAt = null
    ): void
    {
        try {
            $userId = (int) $userId;
            
            $this->db->beginTransaction();

            // Check if movie exists
            $checkMovie = $this->db->prepare("SELECT isbn FROM movie WHERE isbn = :movieId");
            $checkMovie->execute([':movieId' => $movieIsbn]);
            
            if (!$checkMovie->fetch()) {
                throw new RuntimeException("Movie with ID {$movieIsbn} does not exist. Please add the movie first.");
            }

            // Add relationship between user and movie
            $stmt = $this->db->prepare("
                INSERT INTO user_movies (user_id, movie_isbn, added_at, personal_rating, personal_notes, consumed_at) 
                VALUES (:userId, :movieId, NOW(), :personalRating, :personalNotes, :consumedAt)
                ON DUPLICATE KEY UPDATE 
                    added_at = NOW(),
                    personal_rating = COALESCE(VALUES(personal_rating), personal_rating),
                    personal_notes = COALESCE(VALUES(personal_notes), personal_notes),
                    consumed_at = COALESCE(VALUES(consumed_at), consumed_at)
            ");
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieIsbn,
                ':personalRating' => $personalRating,
                ':personalNotes' => $personalNotes,
                ':consumedAt' => $consumedAt
            ]);

            // Add statuses if provided
            if (!empty($statuses)) {
                $this->updateStatuses($userId, $movieIsbn, $statuses);
            }

            $this->db->commit();
            
            $this->logInfo('Movie added to user successfully', [
                'user_id' => $userId,
                'movie_isbn' => $movieIsbn,
                'statuses' => $statuses,
                'personal_rating' => $personalRating,
                'personal_notes' => $personalNotes,
                'consumed_at' => $consumedAt
            ]);
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error adding movie to user', $e, [
                'user_id' => $userId,
                'movie_isbn' => $movieIsbn
            ]);
            throw new RuntimeException("Could not add movie to user. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Error adding movie to user', $e, [
                'user_id' => $userId,
                'movie_isbn' => $movieIsbn
            ]);
            throw new RuntimeException("An unexpected error occurred while adding movie to user: " . $e->getMessage(), 0, $e);
        }
    }

    public function remove(int $userId, string $movieId): bool
    {
        try {
            $this->db->beginTransaction();

            // Remove user-specific statuses
            $stmtStatuses = $this->db->prepare("DELETE FROM user_movie_statuses WHERE user_id = :userId AND movie_isbn = :movieId");
            $stmtStatuses->execute([
                ':userId' => $userId,
                ':movieId' => $movieId
            ]);

            // Remove user-movie relationship
            $stmt = $this->db->prepare("DELETE FROM user_movies WHERE user_id = :userId AND movie_isbn = :movieId");
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieId
            ]);

            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();
            
            if ($deleted) {
                $this->logInfo('Movie removed from user successfully', [
                    'user_id' => $userId,
                    'movie_id' => $movieId
                ]);
            }
            
            return $deleted;

        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error removing movie from user', $e, [
                'user_id' => $userId,
                'movie_id' => $movieId
            ]);
            throw new RuntimeException("Could not remove movie from user. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Error removing movie from user', $e, [
                'user_id' => $userId,
                'movie_id' => $movieId
            ]);
            throw new RuntimeException("An unexpected error occurred while removing movie from user: " . $e->getMessage(), 0, $e);
        }
    }

    public function edit(int $userId, string $movieIsbn, array $data): void
    {
        $this->db->beginTransaction();
        try {
            $updates = [];
            $params = [':userId' => $userId, ':movieIsbn' => $movieIsbn];

            if (isset($data['personal_rating'])) {
                $updates[] = "personal_rating = :personalRating";
                $params[':personalRating'] = $data['personal_rating'] !== null ? (float) $data['personal_rating'] : null;
            }

            if (isset($data['personal_notes'])) {
                $updates[] = "personal_notes = :personalNotes";
                $params[':personalNotes'] = $data['personal_notes'];
            }

            if (isset($data['consumed_at'])) {
                $updates[] = "consumed_at = :consumedAt";
                $params[':consumedAt'] = $data['consumed_at'];
            }

            if (!empty($updates)) {
                $sql = "UPDATE user_movies SET " . implode(', ', $updates);
                $sql .= " WHERE user_id = :userId AND movie_isbn = :movieIsbn";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            
            $this->logInfo('User movie data edited successfully', [
                'user_id' => $userId,
                'movie_isbn' => $movieIsbn,
                'data' => $data
            ]);
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error editing user movie', $e, [
                'user_id' => $userId,
                'movie_isbn' => $movieIsbn
            ]);
            throw new RuntimeException("Could not edit user movie. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Error editing user movie', $e, [
                'user_id' => $userId,
                'movie_isbn' => $movieIsbn
            ]);
            throw new RuntimeException("An unexpected error occurred while editing user movie: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateStatuses(int $userId, string $movieId, array $statuses): void
    {
        $weStartedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $weStartedTransaction = true;
        }
        
        try {
            $userId = (int) $userId;

            // Remove existing statuses for this user-movie combination
            $deleteStmt = $this->db->prepare("DELETE FROM user_movie_statuses WHERE user_id = :userId AND movie_isbn = :movieId");
            $deleteStmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieId
            ]);

            // Add new statuses
            if (!empty($statuses)) {
                $insertStmt = $this->db->prepare("
                    INSERT INTO user_movie_statuses (user_id, movie_isbn, status_id) 
                    VALUES (:userId, :movieId, :statusId)
                ");

                foreach ($statuses as $statusName) {
                    $statusId = $this->getStatusId($statusName);
                    if ($statusId !== null) {
                        $insertStmt->execute([
                            ':userId' => $userId,
                            ':movieId' => $movieId,
                            ':statusId' => $statusId
                        ]);
                    }
                }
            }

            if ($weStartedTransaction) {
                $this->db->commit();
            }

        } catch (PDOException $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('DB Error updating user movie statuses', $e, [
                'movie_id' => $movieId,
                'statuses' => $statuses,
                'user_id' => $userId
            ]);
            throw new RuntimeException("Could not update user movie statuses. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error updating user movie statuses', $e, [
                'movie_id' => $movieId,
                'statuses' => $statuses,
                'user_id' => $userId
            ]);
            throw new RuntimeException("An unexpected error occurred while updating user movie statuses: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateRating(int $userId, string $movieId, ?float $rating): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_movies 
                SET personal_rating = :rating 
                WHERE user_id = :userId AND movie_isbn = :movieId
            ");
            
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieId,
                ':rating' => $rating
            ]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException("No user-movie relationship found to update rating. userId=$userId, movieId=$movieId");
            }
            
            $this->logInfo('User movie rating updated', [
                'user_id' => $userId,
                'movie_id' => $movieId,
                'rating' => $rating
            ]);

        } catch (PDOException $e) {
            $this->logError('DB Error updating user movie rating', $e, [
                'user_id' => $userId,
                'movie_id' => $movieId,
                'rating' => $rating
            ]);
            throw new RuntimeException("Could not update user movie rating. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserStatuses(int $userId, string $movieId): array
    {
        try {
            $sql = "
                SELECT ms.name 
                FROM movie_statuses ms
                INNER JOIN user_movie_statuses ums ON ms.id = ums.status_id
                WHERE ums.user_id = :userId AND ums.movie_isbn = :movieId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieId
            ]);

            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        } catch (PDOException $e) {
            $this->logError('DB Error getting user movie statuses', $e, [
                'user_id' => $userId,
                'movie_id' => $movieId
            ]);
            throw new RuntimeException("Could not get user movie statuses. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function count(int $userId): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM user_movies WHERE user_id = :userId";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('DB Error counting user movies', $e, ['user_id' => $userId]);
            throw new RuntimeException("Could not count user movies. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function countByStatus(int $userId): array
    {
        try {
            $sql = "
                SELECT ms.name, COUNT(DISTINCT um.movie_isbn) as count
                FROM movie_statuses ms
                LEFT JOIN user_movie_statuses ums ON ms.id = ums.status_id AND ums.user_id = :userId
                LEFT JOIN user_movies um ON um.movie_isbn = ums.movie_isbn AND um.user_id = :userId
                GROUP BY ms.id, ms.name
                ORDER BY ms.name
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            
            $result = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result[$row['name']] = (int)$row['count'];
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->logError('DB Error counting user movies by status', $e, [
                'user_id' => $userId
            ]);
            throw new RuntimeException("Could not count user movies by status. DB Error: " . $e->getMessage(), 0, $e);
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
