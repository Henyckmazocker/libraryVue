<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Video;

use App\Domain\Repository\Video\VideoTagRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Video Tag management
 */
final class MySqlVideoTagRepository implements VideoTagRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function findByUser(int $userId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, name, color FROM user_video_tags WHERE user_id = :userId ORDER BY name"
            );
            $stmt->execute([':userId' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB findByUser Error', $e, ['user_id' => $userId]);
            throw new RuntimeException('Could not get user video tags: ' . $e->getMessage(), 0, $e);
        }
    }

    public function create(int $userId, string $name, string $color = '#c0392b'): int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO user_video_tags (user_id, name, color) VALUES (:userId, :name, :color)"
            );
            $stmt->execute([':userId' => $userId, ':name' => $name, ':color' => $color]);

            $tagId = (int)$this->db->lastInsertId();
            $this->logInfo('Video tag created', ['user_id' => $userId, 'tag_id' => $tagId, 'name' => $name]);
            return $tagId;
        } catch (PDOException $e) {
            $this->logError('DB create Error', $e, ['user_id' => $userId, 'name' => $name]);
            throw new RuntimeException('Could not create video tag: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $userId, int $tagId): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "DELETE FROM user_video_tag_assignments WHERE user_id = :userId AND tag_id = :tagId"
            )->execute([':userId' => $userId, ':tagId' => $tagId]);

            $stmt = $this->db->prepare(
                "DELETE FROM user_video_tags WHERE id = :tagId AND user_id = :userId"
            );
            $stmt->execute([':tagId' => $tagId, ':userId' => $userId]);
            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();
            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB delete Error', $e, ['user_id' => $userId, 'tag_id' => $tagId]);
            throw new RuntimeException('Could not delete video tag: ' . $e->getMessage(), 0, $e);
        }
    }

    public function assignToVideo(int $userId, int $videoId, int $tagId): void
    {
        try {
            $verify = $this->db->prepare(
                "SELECT id FROM user_video_tags WHERE id = :tagId AND user_id = :userId"
            );
            $verify->execute([':tagId' => $tagId, ':userId' => $userId]);
            if (!$verify->fetch()) {
                throw new RuntimeException("Tag {$tagId} does not belong to user {$userId}.");
            }

            $this->db->prepare(
                "INSERT IGNORE INTO user_video_tag_assignments (user_id, video_id, tag_id) VALUES (:userId, :videoId, :tagId)"
            )->execute([':userId' => $userId, ':videoId' => $videoId, ':tagId' => $tagId]);
        } catch (PDOException $e) {
            $this->logError('DB assignToVideo Error', $e, ['user_id' => $userId, 'video_id' => $videoId, 'tag_id' => $tagId]);
            throw new RuntimeException('Could not assign tag to video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function removeFromVideo(int $userId, int $videoId, int $tagId): void
    {
        try {
            $this->db->prepare(
                "DELETE FROM user_video_tag_assignments WHERE user_id = :userId AND video_id = :videoId AND tag_id = :tagId"
            )->execute([':userId' => $userId, ':videoId' => $videoId, ':tagId' => $tagId]);
        } catch (PDOException $e) {
            $this->logError('DB removeFromVideo Error', $e, ['user_id' => $userId, 'video_id' => $videoId, 'tag_id' => $tagId]);
            throw new RuntimeException('Could not remove tag from video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function removeAllFromVideo(int $userId, int $videoId): void
    {
        try {
            $this->db->prepare(
                "DELETE FROM user_video_tag_assignments WHERE user_id = :userId AND video_id = :videoId"
            )->execute([':userId' => $userId, ':videoId' => $videoId]);
        } catch (PDOException $e) {
            $this->logError('DB removeAllFromVideo Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not remove all tags from video: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getVideoTags(int $userId, int $videoId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT t.id, t.name, t.color
                FROM user_video_tag_assignments a
                INNER JOIN user_video_tags t ON a.tag_id = t.id
                WHERE a.user_id = :userId AND a.video_id = :videoId
                ORDER BY t.name
            ");
            $stmt->execute([':userId' => $userId, ':videoId' => $videoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB getVideoTags Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not get video tags: ' . $e->getMessage(), 0, $e);
        }
    }

    public function syncVideoTags(int $userId, int $videoId, array $tagIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "DELETE FROM user_video_tag_assignments WHERE user_id = :userId AND video_id = :videoId"
            )->execute([':userId' => $userId, ':videoId' => $videoId]);

            if (!empty($tagIds)) {
                $insert = $this->db->prepare(
                    "INSERT IGNORE INTO user_video_tag_assignments (user_id, video_id, tag_id) VALUES (:userId, :videoId, :tagId)"
                );
                foreach ($tagIds as $tagId) {
                    $insert->execute([':userId' => $userId, ':videoId' => $videoId, ':tagId' => (int)$tagId]);
                }
            }

            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB syncVideoTags Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not sync video tags: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException('Unexpected error syncing video tags: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
