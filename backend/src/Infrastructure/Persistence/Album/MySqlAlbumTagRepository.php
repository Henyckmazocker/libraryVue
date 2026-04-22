<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Album;

use App\Domain\Repository\Album\AlbumTagRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Album Tag management
 * Handles user-specific album tags
 */
final class MySqlAlbumTagRepository implements AlbumTagRepositoryInterface
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
                'SELECT id, name, color FROM user_album_tags WHERE user_id = :userId ORDER BY name'
            );
            $stmt->execute([':userId' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB findByUser Error', $e, ['user_id' => $userId]);
            throw new RuntimeException('Could not get user album tags. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function create(int $userId, string $name, string $color = '#007bff'): int
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO user_album_tags (user_id, name, color) VALUES (:userId, :name, :color)'
            );
            $stmt->execute([':userId' => $userId, ':name' => $name, ':color' => $color]);

            $tagId = (int)$this->db->lastInsertId();

            $this->logInfo('Album tag created', ['user_id' => $userId, 'tag_id' => $tagId, 'name' => $name]);

            return $tagId;
        } catch (PDOException $e) {
            $this->logError('DB create Error', $e, ['user_id' => $userId, 'name' => $name]);
            throw new RuntimeException('Could not create album tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $userId, int $tagId): bool
    {
        $this->db->beginTransaction();
        try {
            // Remove all assignments for this tag
            $stmtAssign = $this->db->prepare(
                'DELETE FROM user_album_tag_assignments WHERE user_id = :userId AND tag_id = :tagId'
            );
            $stmtAssign->execute([':userId' => $userId, ':tagId' => $tagId]);

            // Delete the tag
            $stmt = $this->db->prepare(
                'DELETE FROM user_album_tags WHERE id = :tagId AND user_id = :userId'
            );
            $stmt->execute([':tagId' => $tagId, ':userId' => $userId]);

            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();

            if ($deleted) {
                $this->logInfo('Album tag deleted', ['user_id' => $userId, 'tag_id' => $tagId]);
            }

            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB delete Error', $e, ['user_id' => $userId, 'tag_id' => $tagId]);
            throw new RuntimeException('Could not delete album tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function assignToAlbum(int $userId, int $albumId, int $tagId): void
    {
        try {
            // Verify tag ownership
            $verify = $this->db->prepare(
                'SELECT id FROM user_album_tags WHERE id = :tagId AND user_id = :userId'
            );
            $verify->execute([':tagId' => $tagId, ':userId' => $userId]);
            if (!$verify->fetch()) {
                throw new RuntimeException("Tag {$tagId} does not belong to user {$userId}.");
            }

            $stmt = $this->db->prepare(
                'INSERT IGNORE INTO user_album_tag_assignments (user_id, album_id, tag_id) VALUES (:userId, :albumId, :tagId)'
            );
            $stmt->execute([':userId' => $userId, ':albumId' => $albumId, ':tagId' => $tagId]);
        } catch (PDOException $e) {
            $this->logError('DB assignToAlbum Error', $e, [
                'user_id'  => $userId,
                'album_id' => $albumId,
                'tag_id'   => $tagId,
            ]);
            throw new RuntimeException('Could not assign tag to album. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function removeFromAlbum(int $userId, int $albumId, int $tagId): void
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM user_album_tag_assignments WHERE user_id = :userId AND album_id = :albumId AND tag_id = :tagId'
            );
            $stmt->execute([':userId' => $userId, ':albumId' => $albumId, ':tagId' => $tagId]);
        } catch (PDOException $e) {
            $this->logError('DB removeFromAlbum Error', $e, [
                'user_id'  => $userId,
                'album_id' => $albumId,
                'tag_id'   => $tagId,
            ]);
            throw new RuntimeException('Could not remove tag from album. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function removeAllFromAlbum(int $userId, int $albumId): void
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM user_album_tag_assignments WHERE user_id = :userId AND album_id = :albumId'
            );
            $stmt->execute([':userId' => $userId, ':albumId' => $albumId]);
        } catch (PDOException $e) {
            $this->logError('DB removeAllFromAlbum Error', $e, [
                'user_id'  => $userId,
                'album_id' => $albumId,
            ]);
            throw new RuntimeException('Could not remove all tags from album. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getAlbumTags(int $userId, int $albumId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT t.id, t.name, t.color
                FROM user_album_tag_assignments a
                INNER JOIN user_album_tags t ON a.tag_id = t.id
                WHERE a.user_id = :userId AND a.album_id = :albumId
                ORDER BY t.name
            ");
            $stmt->execute([':userId' => $userId, ':albumId' => $albumId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB getAlbumTags Error', $e, ['user_id' => $userId, 'album_id' => $albumId]);
            throw new RuntimeException('Could not get album tags. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getAlbumsByTag(int $userId, int $tagId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT album_id FROM user_album_tag_assignments
                WHERE user_id = :userId AND tag_id = :tagId
            ");
            $stmt->execute([':userId' => $userId, ':tagId' => $tagId]);
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'album_id');
        } catch (PDOException $e) {
            $this->logError('DB getAlbumsByTag Error', $e, ['user_id' => $userId, 'tag_id' => $tagId]);
            throw new RuntimeException('Could not get albums by tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function syncAlbumTags(int $userId, int $albumId, array $tagIds): void
    {
        $this->db->beginTransaction();
        try {
            // Remove all existing assignments
            $deleteStmt = $this->db->prepare(
                'DELETE FROM user_album_tag_assignments WHERE user_id = :userId AND album_id = :albumId'
            );
            $deleteStmt->execute([':userId' => $userId, ':albumId' => $albumId]);

            // Insert new assignments
            if (!empty($tagIds)) {
                $insertStmt = $this->db->prepare(
                    'INSERT IGNORE INTO user_album_tag_assignments (user_id, album_id, tag_id) VALUES (:userId, :albumId, :tagId)'
                );
                foreach ($tagIds as $tagId) {
                    $insertStmt->execute([
                        ':userId'  => $userId,
                        ':albumId' => $albumId,
                        ':tagId'   => (int)$tagId,
                    ]);
                }
            }

            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB syncAlbumTags Error', $e, ['user_id' => $userId, 'album_id' => $albumId]);
            throw new RuntimeException('Could not sync album tags. DB Error: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException('Unexpected error syncing album tags: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
