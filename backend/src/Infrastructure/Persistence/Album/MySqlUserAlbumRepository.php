<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Album;

use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Infrastructure\Persistence\Album\Mappers\AlbumDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for User-Album relationships
 * Handles user-specific album operations and statuses
 */
final class MySqlUserAlbumRepository implements UserAlbumRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE      = 'album_statuses';
    private const STATUS_LINK_TABLE = 'album_has_statuses';
    private const STATUS_COLUMN     = 'album_id';

    public function __construct(
        private readonly PDO $db,
        private readonly AlbumDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    public function findByUser(int $userId, array $filters = []): array
    {
        try {
            $sql = "
                SELECT a.*,
                       ua.added_at      AS user_added_at,
                       ua.personal_rating AS user_rating,
                       ua.personal_notes,
                       ua.listen_count,
                       ua.favorite_track,
                       ua.completed_at,
                       ua.date_started,
                       ua.date_finished,
                       GROUP_CONCAT(als.name SEPARATOR ', ') AS user_statuses
                FROM albums a
                INNER JOIN user_albums ua ON a.id = ua.album_id
                LEFT JOIN user_album_statuses uas ON a.id = uas.album_id AND uas.user_id = ua.user_id
                LEFT JOIN album_statuses als ON uas.status_id = als.id
                WHERE ua.user_id = :userId
            ";

            $params = [':userId' => $userId];

            if (!empty($filters['status'])) {
                $sql .= " AND als.name = :status";
                $params[':status'] = $filters['status'];
            }

            if (!empty($filters['title'])) {
                $sql .= " AND a.title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }

            if (!empty($filters['artist'])) {
                $sql .= " AND a.artist LIKE :artist";
                $params[':artist'] = '%' . $filters['artist'] . '%';
            }

            if (!empty($filters['genre'])) {
                $sql .= " AND JSON_CONTAINS(a.genres, :genre, '\$')";
                $params[':genre'] = '"' . $filters['genre'] . '"';
            }

            $sql .= " GROUP BY a.id, a.spotify_id, a.title, a.artist, a.artist_id, a.release_date,
                               a.release_date_precision, a.cover_url, a.genres, a.label, a.total_tracks,
                               a.album_type, a.duration_ms, a.popularity, a.external_url, a.upc, a.addedTimestamp,
                               ua.added_at, ua.personal_rating, ua.personal_notes, ua.listen_count,
                               ua.favorite_track, ua.completed_at, ua.date_started, ua.date_finished
                      ORDER BY ua.added_at DESC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('DB Error finding albums by user', $e, ['user_id' => $userId]);
            throw new RuntimeException('Could not find albums by user. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function hasAlbum(int $userId, int $albumId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM user_albums WHERE user_id = :userId AND album_id = :albumId"
            );
            $stmt->execute([':userId' => $userId, ':albumId' => $albumId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logError('DB hasAlbum Error', $e, ['user_id' => $userId, 'album_id' => $albumId]);
            throw new RuntimeException('Could not check if user has album. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function add(
        int $userId,
        int $albumId,
        array $statuses = [],
        ?float $personalRating = null,
        ?string $personalNotes = null,
        ?string $completedAt = null,
        ?int $listenCount = null,
        ?string $favoriteTrack = null
    ): void {
        try {
            $this->db->beginTransaction();

            // Verify album exists in catalogue
            $check = $this->db->prepare("SELECT id FROM albums WHERE id = :albumId");
            $check->execute([':albumId' => $albumId]);
            if (!$check->fetch()) {
                throw new RuntimeException("Album with ID {$albumId} does not exist in catalogue.");
            }

            $stmt = $this->db->prepare("
                INSERT INTO user_albums
                    (user_id, album_id, added_at, personal_rating, personal_notes, listen_count, favorite_track, completed_at)
                VALUES
                    (:userId, :albumId, NOW(), :personalRating, :personalNotes, :listenCount, :favoriteTrack, :completedAt)
                ON DUPLICATE KEY UPDATE
                    added_at       = NOW(),
                    personal_rating = COALESCE(VALUES(personal_rating), personal_rating),
                    personal_notes  = COALESCE(VALUES(personal_notes), personal_notes),
                    listen_count    = COALESCE(VALUES(listen_count), listen_count),
                    favorite_track  = COALESCE(VALUES(favorite_track), favorite_track),
                    completed_at    = COALESCE(VALUES(completed_at), completed_at)
            ");
            $stmt->execute([
                ':userId'        => $userId,
                ':albumId'       => $albumId,
                ':personalRating' => $personalRating,
                ':personalNotes'  => $personalNotes,
                ':listenCount'    => $listenCount,
                ':favoriteTrack'  => $favoriteTrack,
                ':completedAt'    => $completedAt,
            ]);

            if (!empty($statuses)) {
                $this->updateStatuses($userId, $albumId, $statuses);
            }

            $this->db->commit();

            $this->logInfo('Album added to user library', [
                'user_id'  => $userId,
                'album_id' => $albumId,
                'statuses' => $statuses,
            ]);
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB add Error', $e, ['user_id' => $userId, 'album_id' => $albumId]);
            throw new RuntimeException('Could not add album to user. DB Error: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException('Unexpected error adding album to user: ' . $e->getMessage(), 0, $e);
        }
    }

    public function remove(int $userId, int $albumId): bool
    {
        try {
            $this->db->beginTransaction();

            $stmtStatuses = $this->db->prepare(
                "DELETE FROM user_album_statuses WHERE user_id = :userId AND album_id = :albumId"
            );
            $stmtStatuses->execute([':userId' => $userId, ':albumId' => $albumId]);

            $stmt = $this->db->prepare(
                "DELETE FROM user_albums WHERE user_id = :userId AND album_id = :albumId"
            );
            $stmt->execute([':userId' => $userId, ':albumId' => $albumId]);

            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();

            if ($deleted) {
                $this->logInfo('Album removed from user library', [
                    'user_id'  => $userId,
                    'album_id' => $albumId,
                ]);
            }

            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB remove Error', $e, ['user_id' => $userId, 'album_id' => $albumId]);
            throw new RuntimeException('Could not remove album from user. DB Error: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException('Unexpected error removing album from user: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(int $userId, int $albumId, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            $updates = [];
            $params  = [':userId' => $userId, ':albumId' => $albumId];

            $fieldMap = [
                'personal_rating' => [':personalRating', 'float'],
                'personal_notes'  => [':personalNotes',  'string'],
                'listen_count'    => [':listenCount',    'int'],
                'favorite_track'  => [':favoriteTrack',  'string'],
                'completed_at'    => [':completedAt',    'string'],
                'date_started'    => [':dateStarted',    'string'],
                'date_finished'   => [':dateFinished',   'string'],
            ];

            foreach ($fieldMap as $column => [$param, $type]) {
                if (array_key_exists($column, $data)) {
                    $updates[]        = "{$column} = {$param}";
                    $value            = $data[$column];
                    if ($value !== null) {
                        settype($value, $type);
                    }
                    $params[$param] = $value;
                }
            }

            if (!empty($updates)) {
                $sql = "UPDATE user_albums SET " . implode(', ', $updates)
                    . " WHERE user_id = :userId AND album_id = :albumId";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();

            $this->logInfo('User album data updated', [
                'user_id'  => $userId,
                'album_id' => $albumId,
                'fields'   => array_keys($data),
            ]);

            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB update Error', $e, ['user_id' => $userId, 'album_id' => $albumId]);
            throw new RuntimeException('Could not update user album. DB Error: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException('Unexpected error updating user album: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updateStatuses(int $userId, int $albumId, array $statuses): void
    {
        $weStartedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $weStartedTransaction = true;
        }

        try {
            // Remove existing statuses
            $deleteStmt = $this->db->prepare(
                "DELETE FROM user_album_statuses WHERE user_id = :userId AND album_id = :albumId"
            );
            $deleteStmt->execute([':userId' => $userId, ':albumId' => $albumId]);

            // Insert new statuses
            if (!empty($statuses)) {
                $insertStmt = $this->db->prepare("
                    INSERT INTO user_album_statuses (user_id, album_id, status_id)
                    VALUES (:userId, :albumId, :statusId)
                ");
                foreach ($statuses as $statusName) {
                    $statusId = $this->getStatusId($statusName);
                    if ($statusId !== null) {
                        $insertStmt->execute([
                            ':userId'   => $userId,
                            ':albumId'  => $albumId,
                            ':statusId' => $statusId,
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
            $this->logError('DB updateStatuses Error', $e, [
                'user_id'  => $userId,
                'album_id' => $albumId,
                'statuses' => $statuses,
            ]);
            throw new RuntimeException('Could not update album statuses. DB Error: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new RuntimeException('Unexpected error updating album statuses: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updateRating(int $userId, int $albumId, float $rating): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_albums SET personal_rating = :rating
                WHERE user_id = :userId AND album_id = :albumId
            ");
            $stmt->execute([':rating' => $rating, ':userId' => $userId, ':albumId' => $albumId]);

            $this->logInfo('Album rating updated', [
                'user_id'  => $userId,
                'album_id' => $albumId,
                'rating'   => $rating,
            ]);
        } catch (PDOException $e) {
            $this->logError('DB updateRating Error', $e, ['user_id' => $userId, 'album_id' => $albumId]);
            throw new RuntimeException('Could not update album rating. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getUserStatuses(int $userId, int $albumId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT als.name
                FROM user_album_statuses uas
                JOIN album_statuses als ON uas.status_id = als.id
                WHERE uas.user_id = :userId AND uas.album_id = :albumId
                ORDER BY als.name
            ");
            $stmt->execute([':userId' => $userId, ':albumId' => $albumId]);
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
        } catch (PDOException $e) {
            $this->logError('DB getUserStatuses Error', $e, ['user_id' => $userId, 'album_id' => $albumId]);
            throw new RuntimeException('Could not get user album statuses. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function countByUser(int $userId): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_albums WHERE user_id = :userId");
            $stmt->execute([':userId' => $userId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('DB countByUser Error', $e, ['user_id' => $userId]);
            throw new RuntimeException('Could not count user albums. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getTrendingAlbums(int $limit, int $daysWindow, ?int $userId): array
    {
        try {
            $listeningStatusId = $this->getStatusId('listening');
            $recentDays = 30;

            // Build user library check if userId provided
            $userLibraryCheck = $userId !== null
                ? "EXISTS(SELECT 1 FROM user_albums ua2 WHERE ua2.user_id = {$userId} AND ua2.album_id = a.id) AS is_in_user_library,"
                : "0 AS is_in_user_library,";

            $sql = "
                SELECT
                    a.id,
                    a.spotify_id,
                    a.title,
                    a.artist,
                    a.artist_id,
                    a.release_date,
                    a.release_date_precision,
                    a.cover_url,
                    a.genres,
                    a.label,
                    a.total_tracks,
                    a.album_type,
                    a.duration_ms,
                    a.popularity,
                    a.external_url,
                    a.upc,
                    {$userLibraryCheck}
                    COUNT(DISTINCT ua.user_id)                                                  AS user_count,
                    AVG(ua.personal_rating)                                                     AS avg_rating,
                    SUM(CASE
                        WHEN ua.added_at >= DATE_SUB(NOW(), INTERVAL {$recentDays} DAY)
                        THEN 1 ELSE 0
                    END)                                                                        AS recent_adds,
                    SUM(CASE
                        WHEN uas.status_id = {$listeningStatusId}
                        THEN 1 ELSE 0
                    END)                                                                        AS listening_count,
                    MAX(ua.added_at)                                                            AS last_added,
                    -- Trending score (same formula as books/games/movies)
                    (
                        (COUNT(DISTINCT ua.user_id) * 10) +
                        (COALESCE(AVG(ua.personal_rating), 0) * 5) +
                        (SUM(CASE WHEN ua.added_at >= DATE_SUB(NOW(), INTERVAL {$recentDays} DAY) THEN 1 ELSE 0 END) * 15) +
                        (SUM(CASE WHEN uas.status_id = {$listeningStatusId} THEN 1 ELSE 0 END) * 8) -
                        (DATEDIFF(NOW(), MAX(ua.added_at)) * 0.1)
                    )                                                                           AS trending_score
                FROM albums a
                INNER JOIN user_albums ua ON a.id = ua.album_id
                LEFT JOIN user_album_statuses uas ON ua.album_id = uas.album_id AND ua.user_id = uas.user_id
                WHERE ua.added_at >= DATE_SUB(NOW(), INTERVAL {$daysWindow} DAY)
                GROUP BY a.id, a.spotify_id, a.title, a.artist, a.artist_id, a.release_date,
                         a.release_date_precision, a.cover_url, a.genres, a.label, a.total_tracks,
                         a.album_type, a.duration_ms, a.popularity, a.external_url, a.upc
                HAVING user_count >= 1
                ORDER BY trending_score DESC
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $albums  = $this->mapper->toDomainCollection($rows);
            $results = [];

            foreach ($albums as $i => $album) {
                $row      = $rows[$i];
                $albumArr = $album->toArray();
                $albumArr['addCount']         = (int)($row['user_count'] ?? 0);
                $albumArr['trendingScore']    = (float)($row['trending_score'] ?? 0);
                $albumArr['isInUserLibrary']  = (bool)($row['is_in_user_library'] ?? false);
                $results[] = $albumArr;
            }

            return $results;
        } catch (PDOException $e) {
            $this->logError('DB getTrendingAlbums Error', $e, ['limit' => $limit, 'days_window' => $daysWindow]);
            throw new RuntimeException('Could not get trending albums. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    // --- StatusManagementTrait required methods ---

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

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
