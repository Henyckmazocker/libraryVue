<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Movie;

use App\Domain\Repository\Movie\MovieTagRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Movie Tag management
 * Handles user-specific movie tags
 */
final class MySqlMovieTagRepository implements MovieTagRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function getByUser(int $userId): array
    {
        try {
            $sql = 'SELECT id, name, color FROM user_movie_tags WHERE user_id = :userId ORDER BY name';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB Error getting user movie tags', $e, ['userId' => $userId]);
            throw new RuntimeException('Could not get user movie tags. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getByMovie(int $userId, string $movieId): array
    {
        try {
            $sql = 'SELECT t.id, t.name, t.color 
                    FROM user_movie_tag_assignments a
                    INNER JOIN user_movie_tags t ON a.tag_id = t.id
                    WHERE a.user_id = :userId AND a.movie_isbn = :movieId
                    ORDER BY t.name';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieId
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB Error getting movie tags', $e, [
                'userId' => $userId,
                'movieId' => $movieId
            ]);
            throw new RuntimeException('Could not get movie tags. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function create(int $userId, string $name, string $color): int
    {
        try {
            $sql = 'INSERT INTO user_movie_tags (user_id, name, color) VALUES (:userId, :name, :color)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':name' => $name,
                ':color' => $color
            ]);
            
            $tagId = (int)$this->db->lastInsertId();
            
            $this->logInfo('Movie tag created successfully', [
                'user_id' => $userId,
                'tag_id' => $tagId,
                'name' => $name
            ]);
            
            return $tagId;
        } catch (PDOException $e) {
            // If tag already exists, get its ID
            if ($e->getCode() === '23000') { // Duplicate entry
                $stmt = $this->db->prepare('SELECT id FROM user_movie_tags WHERE user_id = :userId AND name = :name');
                $stmt->execute([
                    ':userId' => $userId,
                    ':name' => $name
                ]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return (int)$row['id'];
                }
            }
            
            $this->logError('DB Error creating movie tag', $e, [
                'userId' => $userId,
                'name' => $name
            ]);
            throw new RuntimeException('Could not create movie tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function assign(int $userId, string $movieId, int $tagId): void
    {
        try {
            $sql = 'INSERT INTO user_movie_tag_assignments (user_id, movie_isbn, tag_id) 
                    VALUES (:userId, :movieId, :tagId)';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieId,
                ':tagId' => $tagId
            ]);
            
            $this->logInfo('Tag assigned to movie successfully', [
                'user_id' => $userId,
                'movie_id' => $movieId,
                'tag_id' => $tagId
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                // Already assigned, no action needed
                return;
            }
            
            $this->logError('DB Error assigning tag to movie', $e, [
                'userId' => $userId,
                'movieId' => $movieId,
                'tagId' => $tagId
            ]);
            throw new RuntimeException('Could not assign tag to movie. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function removeAll(int $userId, string $movieId): void
    {
        try {
            $sql = 'DELETE FROM user_movie_tag_assignments 
                    WHERE user_id = :userId AND movie_isbn = :movieId';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieId
            ]);
            
            $this->logInfo('All tags removed from movie', [
                'user_id' => $userId,
                'movie_id' => $movieId,
                'removed_count' => $stmt->rowCount()
            ]);
        } catch (PDOException $e) {
            $this->logError('DB Error removing tags from movie', $e, [
                'userId' => $userId,
                'movieId' => $movieId
            ]);
            throw new RuntimeException('Could not remove tags from movie. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getAllowedTags(int $userId, ?string $isbn = null): array
    {
        return $this->getByUser($userId);
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
