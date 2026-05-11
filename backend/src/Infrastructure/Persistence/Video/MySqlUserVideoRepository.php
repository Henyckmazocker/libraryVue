<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Video;

use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Infrastructure\Persistence\Video\Mappers\VideoDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for User-Video relationships
 */
final class MySqlUserVideoRepository implements UserVideoRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE      = 'video_statuses';
    private const STATUS_LINK_TABLE = 'user_video_statuses';
    private const STATUS_COLUMN     = 'video_id';

    public function __construct(
        private readonly PDO $db,
        private readonly VideoDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    public function findByUser(int $userId, array $filters = []): array
    {
        try {
            $sql = "
                SELECT v.*,
                       uv.added_at        AS user_added_at,
                       uv.personal_rating AS user_rating,
                       uv.personal_notes,
                       uv.watch_count,
                       uv.watched_at,
                       GROUP_CONCAT(vs.name SEPARATOR ', ') AS user_statuses
                FROM videos v
                INNER JOIN user_videos uv ON v.id = uv.video_id
                LEFT JOIN user_video_statuses uvs ON uv.video_id = uvs.video_id AND uvs.user_id = uv.user_id
                LEFT JOIN video_statuses vs ON uvs.status_id = vs.id
                WHERE uv.user_id = :userId
            ";

            $params = [':userId' => $userId];

            if (!empty($filters['status'])) {
                $sql .= " AND vs.name = :status";
                $params[':status'] = $filters['status'];
            }
            if (!empty($filters['title'])) {
                $sql .= " AND v.title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }
            if (!empty($filters['channel'])) {
                $sql .= " AND v.channel_name LIKE :channel";
                $params[':channel'] = '%' . $filters['channel'] . '%';
            }

            $sql .= " GROUP BY v.id, uv.user_id, uv.added_at, uv.personal_rating, uv.personal_notes,
                               uv.watch_count, uv.watched_at
                      ORDER BY uv.added_at DESC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('DB findByUser Error', $e, ['user_id' => $userId]);
            throw new RuntimeException('Could not find user videos: ' . $e->getMessage(), 0, $e);
        }
    }

    public function hasVideo(int $userId, int $videoId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM user_videos WHERE user_id = :userId AND video_id = :videoId"
            );
            $stmt->execute([':userId' => $userId, ':videoId' => $videoId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logError('DB hasVideo Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not check user video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function add(
        int $userId,
        int $videoId,
        array $statuses = [],
        ?float $personalRating = null,
        ?string $personalNotes = null,
        ?string $watchedAt = null,
        ?int $watchCount = null
    ): void {
        try {
            $this->db->beginTransaction();

            $check = $this->db->prepare("SELECT id FROM videos WHERE id = :videoId");
            $check->execute([':videoId' => $videoId]);
            if (!$check->fetch()) {
                throw new RuntimeException("Video with ID {$videoId} does not exist in catalogue.");
            }

            $stmt = $this->db->prepare("
                INSERT INTO user_videos
                    (user_id, video_id, added_at, personal_rating, personal_notes, watch_count, watched_at)
                VALUES
                    (:userId, :videoId, NOW(), :personalRating, :personalNotes, :watchCount, :watchedAt)
                ON DUPLICATE KEY UPDATE
                    added_at       = NOW(),
                    personal_rating = COALESCE(VALUES(personal_rating), personal_rating),
                    personal_notes  = COALESCE(VALUES(personal_notes), personal_notes),
                    watch_count     = COALESCE(VALUES(watch_count), watch_count),
                    watched_at      = COALESCE(VALUES(watched_at), watched_at)
            ");
            $stmt->execute([
                ':userId'        => $userId,
                ':videoId'       => $videoId,
                ':personalRating' => $personalRating,
                ':personalNotes'  => $personalNotes,
                ':watchCount'     => $watchCount,
                ':watchedAt'      => $watchedAt,
            ]);

            if (!empty($statuses)) {
                $this->updateStatuses($userId, $videoId, $statuses);
            }

            $this->db->commit();

            $this->logInfo('Video added to user library', ['user_id' => $userId, 'video_id' => $videoId]);
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB add Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not add video: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException('Unexpected error adding video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function remove(int $userId, int $videoId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM user_videos WHERE user_id = :userId AND video_id = :videoId"
            );
            $stmt->execute([':userId' => $userId, ':videoId' => $videoId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('DB remove Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not remove video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(int $userId, int $videoId, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        try {
            $setClauses = [];
            $params = [':userId' => $userId, ':videoId' => $videoId];

            $allowedFields = ['personal_rating', 'personal_notes', 'watch_count', 'watched_at'];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $setClauses[]          = "{$field} = :{$field}";
                    $params[":{$field}"]   = $data[$field];
                }
            }

            if (empty($setClauses)) {
                return true;
            }

            $sql = "UPDATE user_videos SET " . implode(', ', $setClauses)
                 . " WHERE user_id = :userId AND video_id = :videoId";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('DB update Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not update user video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updateStatuses(int $userId, int $videoId, array $statuses): void
    {
        try {
            $this->db->prepare(
                "DELETE FROM user_video_statuses WHERE user_id = :userId AND video_id = :videoId"
            )->execute([':userId' => $userId, ':videoId' => $videoId]);

            if (empty($statuses)) {
                return;
            }

            $insert = $this->db->prepare("
                INSERT INTO user_video_statuses (user_id, video_id, status_id)
                SELECT :userId, :videoId, id FROM video_statuses WHERE name = :statusName
            ");

            foreach ($statuses as $statusName) {
                $insert->execute([
                    ':userId'     => $userId,
                    ':videoId'    => $videoId,
                    ':statusName' => $statusName,
                ]);
            }
        } catch (PDOException $e) {
            $this->logError('DB updateStatuses Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not update video statuses: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updateRating(int $userId, int $videoId, float $rating): void
    {
        try {
            $this->db->prepare(
                "UPDATE user_videos SET personal_rating = :rating WHERE user_id = :userId AND video_id = :videoId"
            )->execute([':rating' => $rating, ':userId' => $userId, ':videoId' => $videoId]);
        } catch (PDOException $e) {
            $this->logError('DB updateRating Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not update video rating: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updateWatchCount(int $userId, int $videoId, int $count): void
    {
        try {
            $this->db->prepare(
                "UPDATE user_videos SET watch_count = :count WHERE user_id = :userId AND video_id = :videoId"
            )->execute([':count' => $count, ':userId' => $userId, ':videoId' => $videoId]);
        } catch (PDOException $e) {
            $this->logError('DB updateWatchCount Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not update watch count: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findTrending(int $userId, int $limit = 10): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT v.*,
                       uv.added_at        AS user_added_at,
                       uv.personal_rating AS user_rating,
                       uv.personal_notes,
                       uv.watch_count,
                       uv.watched_at,
                       GROUP_CONCAT(vs.name SEPARATOR ', ') AS user_statuses
                FROM videos v
                INNER JOIN user_videos uv ON v.id = uv.video_id
                LEFT JOIN user_video_statuses uvs ON uv.video_id = uvs.video_id AND uvs.user_id = uv.user_id
                LEFT JOIN video_statuses vs ON uvs.status_id = vs.id
                WHERE uv.user_id = :userId
                GROUP BY v.id, uv.user_id, uv.added_at, uv.personal_rating, uv.personal_notes,
                         uv.watch_count, uv.watched_at
                ORDER BY uv.added_at DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->execute();

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('DB findTrending Error', $e, ['user_id' => $userId]);
            throw new RuntimeException('Could not find trending videos: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
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
}
