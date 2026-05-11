<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Video;

use App\Domain\Model\Video;
use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Video\Mappers\VideoDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Video catalogue repository
 * Handles shared video records (not user-specific)
 */
final class MySqlVideoRepository implements VideoRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly VideoDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    public function findById(int $id): ?Video
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM videos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->mapper->toDomain($row) : null;
        } catch (PDOException $e) {
            $this->logError('DB findById Error', $e, ['id' => $id]);
            throw new RuntimeException('Could not find video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findByYouTubeId(string $youtubeId): ?Video
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM videos WHERE youtube_id = :youtubeId");
            $stmt->execute([':youtubeId' => $youtubeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->mapper->toDomain($row) : null;
        } catch (PDOException $e) {
            $this->logError('DB findByYouTubeId Error', $e, ['youtube_id' => $youtubeId]);
            throw new RuntimeException('Could not find video by YouTube ID: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findAll(array $filters = []): array
    {
        try {
            $sql    = "SELECT * FROM videos";
            $params = [];
            $conds  = [];

            if (!empty($filters['title'])) {
                $conds[]         = "title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }
            if (!empty($filters['channel'])) {
                $conds[]            = "channel_name LIKE :channel";
                $params[':channel'] = '%' . $filters['channel'] . '%';
            }

            if ($conds) {
                $sql .= ' WHERE ' . implode(' AND ', $conds);
            }
            $sql .= ' ORDER BY title ASC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('DB findAll Error', $e, ['filters' => $filters]);
            throw new RuntimeException('Could not fetch videos: ' . $e->getMessage(), 0, $e);
        }
    }

    public function save(Video $video): Video
    {
        $this->db->beginTransaction();
        try {
            $p = $this->mapper->toPersistence($video);

            $stmt = $this->db->prepare("
                INSERT INTO videos
                    (youtube_id, title, channel_name, channel_id, cover_url, duration, duration_seconds,
                     view_count, like_count, published_at, description, categories)
                VALUES
                    (:youtube_id, :title, :channel_name, :channel_id, :cover_url, :duration, :duration_seconds,
                     :view_count, :like_count, :published_at, :description, :categories)
                ON DUPLICATE KEY UPDATE
                    title            = VALUES(title),
                    channel_name     = VALUES(channel_name),
                    channel_id       = VALUES(channel_id),
                    cover_url        = VALUES(cover_url),
                    duration         = VALUES(duration),
                    duration_seconds = VALUES(duration_seconds),
                    view_count       = VALUES(view_count),
                    like_count       = VALUES(like_count),
                    published_at     = VALUES(published_at),
                    description      = VALUES(description),
                    categories       = VALUES(categories)
            ");
            $stmt->execute([
                ':youtube_id'       => $p['youtube_id'],
                ':title'            => $p['title'],
                ':channel_name'     => $p['channel_name'],
                ':channel_id'       => $p['channel_id'],
                ':cover_url'        => $p['cover_url'],
                ':duration'         => $p['duration'],
                ':duration_seconds' => $p['duration_seconds'],
                ':view_count'       => $p['view_count'],
                ':like_count'       => $p['like_count'],
                ':published_at'     => $p['published_at'],
                ':description'      => $p['description'],
                ':categories'       => $p['categories'],
            ]);

            // Fetch the record to return a fully-hydrated entity with DB-assigned ID
            $id  = (int)$this->db->lastInsertId();
            $row = $this->db->query("SELECT * FROM videos WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);

            $this->db->commit();
            return $this->mapper->toDomain($row);
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB save Error', $e);
            throw new RuntimeException('Could not save video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(Video $video): bool
    {
        try {
            $p    = $this->mapper->toPersistence($video);
            $stmt = $this->db->prepare("
                UPDATE videos
                SET title            = :title,
                    channel_name     = :channel_name,
                    channel_id       = :channel_id,
                    cover_url        = :cover_url,
                    duration         = :duration,
                    duration_seconds = :duration_seconds,
                    view_count       = :view_count,
                    like_count       = :like_count,
                    published_at     = :published_at,
                    description      = :description,
                    categories       = :categories
                WHERE id = :id
            ");
            $stmt->execute(array_merge($p, [':id' => $video->getId()]));
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('DB update Error', $e, ['id' => $video->getId()]);
            throw new RuntimeException('Could not update video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM videos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('DB delete Error', $e, ['id' => $id]);
            throw new RuntimeException('Could not delete video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function fetchAllowedStatuses(): array
    {
        try {
            $stmt = $this->db->query("SELECT id, name FROM video_statuses ORDER BY id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB fetchAllowedStatuses Error', $e);
            throw new RuntimeException('Could not fetch video statuses: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updateRating(int $id, float $rating): void
    {
        try {
            $stmt = $this->db->prepare("UPDATE videos SET rating = :rating WHERE id = :id");
            $stmt->execute([':rating' => $rating, ':id' => $id]);
        } catch (PDOException $e) {
            $this->logError('DB updateRating Error', $e, ['id' => $id]);
            throw new RuntimeException('Could not update video rating: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
