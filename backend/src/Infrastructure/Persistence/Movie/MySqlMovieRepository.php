<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Movie;

use App\Domain\Model\Movie;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Infrastructure\Persistence\Movie\Mappers\MovieDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Movie repository
 * Handles Movie CRUD operations only
 */
final class MySqlMovieRepository implements MovieRepositoryInterface
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

    public function findById(string $id): ?Movie
    {
        try {
            $sql = "SELECT * FROM movie WHERE isbn = :isbn";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':isbn' => $id]);
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                return null;
            }

            return $this->mapper->toDomain($data);
        } catch (PDOException $e) {
            $this->logError('DB Find Error', $e, [
                'id' => $id,
                'operation' => 'find_by_id'
            ]);
            throw new RuntimeException("Could not find movie. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function findAll(array $filters = []): array
    {
        try {
            $sql = "SELECT DISTINCT m.* FROM movie m";
            $params = [];

            if (!empty($filters['userStatus'])) {
                $statusName = $filters['userStatus'];
                $statusId = $this->getStatusId($statusName);
                if ($statusId === null) {
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

            return $this->mapper->toDomainCollection($moviesData);
        } catch (PDOException $e) {
            $this->logError('DB FindAll Error', $e, [
                'filters' => $filters,
                'operation' => 'find_all'
            ]);
            throw new RuntimeException("Could not fetch movies. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function save(Movie $movie): Movie
    {
        $this->db->beginTransaction();
        try {
            $persistenceData = $this->mapper->toPersistence($movie);
            
            $sql = "INSERT INTO movie (isbn, title, original_title, director, author, coverUrl, rating, description, addedTimestamp, genres, media_type, total_seasons) " .
                   "VALUES (:isbn, :title, :original_title, :director, :author, :coverUrl, :rating, :description, :addedTimestamp, :genres, :media_type, :total_seasons) " .
                   "ON DUPLICATE KEY UPDATE " .
                   "title = VALUES(title), original_title = VALUES(original_title), director = VALUES(director), author = VALUES(author), coverUrl = VALUES(coverUrl), " .
                   "rating = VALUES(rating), description = VALUES(description), addedTimestamp = VALUES(addedTimestamp), genres = VALUES(genres), " .
                   "media_type = VALUES(media_type), total_seasons = VALUES(total_seasons)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':isbn' => $persistenceData['isbn'],
                ':title' => $persistenceData['title'],
                ':original_title' => $persistenceData['original_title'],
                ':director' => $persistenceData['director'],
                ':author' => $persistenceData['director'], // For compatibility
                ':coverUrl' => $persistenceData['coverUrl'],
                ':rating' => $persistenceData['rating'],
                ':description' => $persistenceData['description'],
                ':addedTimestamp' => $persistenceData['addedTimestamp'],
                ':genres' => $persistenceData['genres'],
                ':media_type' => $persistenceData['media_type'],
                ':total_seasons' => $persistenceData['total_seasons']
            ]);

            $this->db->commit();
            
            $this->logInfo('Movie saved successfully', [
                'movie_id' => $persistenceData['isbn'],
                'operation' => 'save'
            ]);
            
            return $movie;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Save Error', $e, [
                'movie_data' => $movie->toArray(),
                'operation' => 'save_movie'
            ]);
            throw new RuntimeException("Could not save movie. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Generic Error during save', $e, [
                'movie_data' => $movie->toArray(),
                'operation' => 'save_movie'
            ]);
            throw new RuntimeException("An unexpected error occurred while saving movie: " . $e->getMessage(), 0, $e);
        }
    }

    public function update(Movie $movie): bool
    {
        try {
            $persistenceData = $this->mapper->toPersistence($movie);
            
            $sql = "UPDATE movie SET 
                    title = :title, 
                    original_title = :original_title, 
                    director = :director, 
                    author = :director,
                    coverUrl = :coverUrl, 
                    rating = :rating, 
                    description = :description, 
                    genres = :genres 
                    WHERE isbn = :isbn";
            
            $stmt = $this->db->prepare($sql);
            $updated = $stmt->execute([
                ':isbn' => $persistenceData['isbn'],
                ':title' => $persistenceData['title'],
                ':original_title' => $persistenceData['original_title'],
                ':director' => $persistenceData['director'],
                ':coverUrl' => $persistenceData['coverUrl'],
                ':rating' => $persistenceData['rating'],
                ':description' => $persistenceData['description'],
                ':genres' => $persistenceData['genres']
            ]);

            if ($updated) {
                $this->logInfo('Movie updated successfully', [
                    'movie_id' => $persistenceData['isbn'],
                    'operation' => 'update'
                ]);
            }
            
            return $updated;
        } catch (PDOException $e) {
            $this->logError('DB Update Error', $e, [
                'movie_data' => $movie->toArray(),
                'operation' => 'update_movie'
            ]);
            throw new RuntimeException("Could not update movie. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function delete(string $id): bool
    {
        $this->db->beginTransaction();
        try {
            // Delete related statuses first
            $stmtDeleteLinks = $this->db->prepare("DELETE FROM movie_has_statuses WHERE movie_isbn = :isbn");
            $stmtDeleteLinks->execute([':isbn' => $id]);
            
            // Delete movie
            $stmtDeleteMovie = $this->db->prepare("DELETE FROM movie WHERE isbn = :isbn");
            $stmtDeleteMovie->execute([':isbn' => $id]);
            
            $deleted = $stmtDeleteMovie->rowCount() > 0;
            $this->db->commit();
            
            if ($deleted) {
                $this->logInfo('Movie deleted successfully', [
                    'movie_id' => $id,
                    'operation' => 'delete'
                ]);
            }
            
            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Delete Error', $e, [
                'isbn' => $id,
                'operation' => 'delete_by_isbn'
            ]);
            throw new RuntimeException("Could not delete movie. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Generic Error during delete', $e, [
                'isbn' => $id,
                'operation' => 'delete_by_isbn'
            ]);
            throw new RuntimeException("An unexpected error occurred while deleting movie: " . $e->getMessage(), 0, $e);
        }
    }

    public function fetchAllowedStatuses(): array
    {
        return $this->getAllowedStatusesFromDb();
    }

    public function updateRating(string $id, float $rating): void
    {
        try {
            $sql = "UPDATE movie SET rating = :rating WHERE isbn = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':rating' => $rating,
                ':id' => $id
            ]);
            
            if ($stmt->rowCount() > 0) {
                $this->logInfo('Movie rating updated', [
                    'movie_id' => $id,
                    'rating' => $rating,
                    'operation' => 'update_rating'
                ]);
            }
        } catch (PDOException $e) {
            $this->logError('DB Update Rating Error', $e, [
                'id' => $id,
                'rating' => $rating,
                'operation' => 'update_rating'
            ]);
            throw new RuntimeException("Could not update movie rating. DB Error: " . $e->getMessage(), 0, $e);
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
