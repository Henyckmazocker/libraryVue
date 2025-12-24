<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\User;

use App\Domain\Repository\User\UserMovieRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation of User-Movie relationship repository
 */
class MySqlUserMovieRepository implements UserMovieRepositoryInterface
{
    use LoggableTrait;

    private const TABLE_USER_MOVIES = 'user_movies';
    private const TABLE_MOVIES = 'movie';
    private const TABLE_USER_MOVIE_STATUSES = 'user_movie_statuses';
    private const TABLE_MOVIE_STATUSES = 'movie_statuses';

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
                SELECT m.*, um.added_at as user_added_at,
                       um.personal_rating, um.personal_notes, um.consumed_at,
                       GROUP_CONCAT(ms.name SEPARATOR ', ') as user_statuses
                FROM " . self::TABLE_MOVIES . " m
                INNER JOIN " . self::TABLE_USER_MOVIES . " um ON m.isbn = um.movie_isbn
                LEFT JOIN " . self::TABLE_USER_MOVIE_STATUSES . " ums 
                    ON m.isbn = ums.movie_isbn AND ums.user_id = :userId
                LEFT JOIN " . self::TABLE_MOVIE_STATUSES . " ms ON ums.status_id = ms.id
                WHERE um.user_id = :userId
            ";

            $params = [':userId' => $userId];

            // Apply status filter
            if (isset($filters['status']) && !empty($filters['status'])) {
                $sql .= " AND ms.name = :status";
                $params[':status'] = $filters['status'];
            }

            // Apply title filter
            if (isset($filters['title']) && !empty($filters['title'])) {
                $sql .= " AND m.title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }

            // Apply genre filter
            if (isset($filters['genre']) && !empty($filters['genre'])) {
                $sql .= " AND m.genre LIKE :genre";
                $params[':genre'] = '%' . $filters['genre'] . '%';
            }

            $sql .= " GROUP BY m.isbn, m.title, m.original_title, m.director, m.author, m.rating, m.coverUrl, m.description, m.addedTimestamp, m.genres, um.added_at, um.personal_rating, um.personal_notes, um.consumed_at ORDER BY um.added_at DESC";

            $stmt = $this->database->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logInfo('Retrieved user movies', [
                'user_id' => $userId,
                'count' => count($movies),
                'filters' => $filters
            ]);

            return $movies;
        } catch (PDOException $e) {
            $this->logError('Failed to get user movies', $e, [
                'user_id' => $userId,
                'filters' => $filters
            ]);
            throw new RuntimeException(
                "Could not get user movies. DB Error: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function hasMovie(int $userId, string $movieId): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM " . self::TABLE_USER_MOVIES . " 
                    WHERE user_id = :userId AND movie_isbn = :movieId";
            
            $stmt = $this->database->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieId
            ]);
            
            $exists = $stmt->fetchColumn() > 0;

            $this->logDebug('Checked user movie existence', [
                'user_id' => $userId,
                'movie_id' => $movieId,
                'exists' => $exists
            ]);

            return $exists;
        } catch (PDOException $e) {
            $this->logError('Failed to check user movie', $e, [
                'user_id' => $userId,
                'movie_id' => $movieId
            ]);
            throw new RuntimeException(
                "Could not check user movie. DB Error: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function add(
        int $userId,
        string $movieIsbn,
        ?float $personalRating = null,
        ?string $personalNotes = null,
        ?string $consumedAt = null
    ): void {
        try {
            $sql = "INSERT INTO " . self::TABLE_USER_MOVIES . " 
                    (user_id, movie_isbn, personal_rating, personal_notes, consumed_at, added_at) 
                    VALUES (:userId, :movieIsbn, :personalRating, :personalNotes, :consumedAt, NOW())";
            
            $stmt = $this->database->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':movieIsbn' => $movieIsbn,
                ':personalRating' => $personalRating,
                ':personalNotes' => $personalNotes,
                ':consumedAt' => $consumedAt
            ]);

            $this->logInfo('Added movie to user library', [
                'user_id' => $userId,
                'movie_isbn' => $movieIsbn,
                'personal_rating' => $personalRating
            ]);
        } catch (PDOException $e) {
            $this->logError('Failed to add user movie', $e, [
                'user_id' => $userId,
                'movie_isbn' => $movieIsbn
            ]);
            throw new RuntimeException(
                "Could not add user movie. DB Error: " . $e->getMessage(),
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
            $sql = "SELECT COUNT(*) FROM " . self::TABLE_USER_MOVIES . " 
                    WHERE user_id = :userId";
            
            $stmt = $this->database->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            
            $count = (int) $stmt->fetchColumn();

            $this->logDebug('Counted user movies', [
                'user_id' => $userId,
                'count' => $count
            ]);

            return $count;
        } catch (PDOException $e) {
            $this->logError('Failed to count user movies', $e, [
                'user_id' => $userId
            ]);
            throw new RuntimeException(
                "Could not count user movies. DB Error: " . $e->getMessage(),
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
                SELECT ms.name as status, COUNT(*) as count
                FROM " . self::TABLE_USER_MOVIES . " um
                LEFT JOIN " . self::TABLE_USER_MOVIE_STATUSES . " ums 
                    ON um.movie_isbn = ums.movie_isbn AND um.user_id = ums.user_id
                LEFT JOIN " . self::TABLE_MOVIE_STATUSES . " ms ON ums.status_id = ms.id
                WHERE um.user_id = :userId
                GROUP BY ms.name
            ";
            
            $stmt = $this->database->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logDebug('Counted user movies by status', [
                'user_id' => $userId,
                'results' => $results
            ]);

            return $results;
        } catch (PDOException $e) {
            $this->logError('Failed to count user movies by status', $e, [
                'user_id' => $userId
            ]);
            throw new RuntimeException(
                "Could not count user movies by status. DB Error: " . $e->getMessage(),
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
