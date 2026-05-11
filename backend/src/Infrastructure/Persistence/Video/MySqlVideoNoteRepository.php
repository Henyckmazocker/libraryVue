<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Video;

use App\Domain\Repository\Video\VideoNoteRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Video Notes management
 */
final class MySqlVideoNoteRepository implements VideoNoteRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function getByVideo(int $userId, int $videoId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, video_id, note_text, note_type, is_private, created_at, updated_at
                FROM user_video_notes
                WHERE user_id = :userId AND video_id = :videoId
                ORDER BY created_at DESC
            ");
            $stmt->execute([':userId' => $userId, ':videoId' => $videoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB getByVideo Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not get video notes: ' . $e->getMessage(), 0, $e);
        }
    }

    public function add(
        int $userId,
        int $videoId,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_video_notes (user_id, video_id, note_text, note_type, is_private)
                VALUES (:userId, :videoId, :noteText, :noteType, :isPrivate)
            ");
            $stmt->execute([
                ':userId'    => $userId,
                ':videoId'   => $videoId,
                ':noteText'  => $noteText,
                ':noteType'  => $noteType,
                ':isPrivate' => (int)$isPrivate,
            ]);

            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            $this->logError('DB add Error', $e, ['user_id' => $userId, 'video_id' => $videoId]);
            throw new RuntimeException('Could not add video note: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $noteId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM user_video_notes WHERE id = :noteId AND user_id = :userId"
            );
            $stmt->execute([':noteId' => $noteId, ':userId' => $userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('DB delete Error', $e, ['note_id' => $noteId, 'user_id' => $userId]);
            throw new RuntimeException('Could not delete video note: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(
        int $noteId,
        int $userId,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_video_notes
                SET note_text  = :noteText,
                    note_type  = :noteType,
                    is_private = :isPrivate,
                    updated_at = NOW()
                WHERE id = :noteId AND user_id = :userId
            ");
            $stmt->execute([
                ':noteText'  => $noteText,
                ':noteType'  => $noteType,
                ':isPrivate' => (int)$isPrivate,
                ':noteId'    => $noteId,
                ':userId'    => $userId,
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('DB update Error', $e, ['note_id' => $noteId, 'user_id' => $userId]);
            throw new RuntimeException('Could not update video note: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findById(int $noteId, int $userId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM user_video_notes WHERE id = :noteId AND user_id = :userId"
            );
            $stmt->execute([':noteId' => $noteId, ':userId' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            $this->logError('DB findById Error', $e, ['note_id' => $noteId, 'user_id' => $userId]);
            throw new RuntimeException('Could not find video note: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
