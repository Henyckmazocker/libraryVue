<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Album;

use App\Domain\Repository\Album\AlbumNoteRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Album Note management
 * Handles user-specific album notes
 */
final class MySqlAlbumNoteRepository implements AlbumNoteRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function getByAlbum(int $userId, int $albumId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT id, note_text, note_type, is_private, created_at, updated_at
                 FROM user_album_notes
                 WHERE user_id = :userId AND album_id = :albumId
                 ORDER BY created_at DESC'
            );
            $stmt->execute([':userId' => $userId, ':albumId' => $albumId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB getByAlbum Error', $e, ['user_id' => $userId, 'album_id' => $albumId]);
            throw new RuntimeException('Could not get album notes. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function add(
        int $userId,
        int $albumId,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): int {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO user_album_notes (user_id, album_id, note_text, note_type, is_private, created_at)
                 VALUES (:userId, :albumId, :noteText, :noteType, :isPrivate, NOW())'
            );
            $stmt->execute([
                ':userId'    => $userId,
                ':albumId'   => $albumId,
                ':noteText'  => $noteText,
                ':noteType'  => $noteType,
                ':isPrivate' => $isPrivate ? 1 : 0,
            ]);

            $noteId = (int)$this->db->lastInsertId();

            $this->logInfo('Album note added', [
                'user_id'   => $userId,
                'album_id'  => $albumId,
                'note_id'   => $noteId,
                'note_type' => $noteType,
            ]);

            return $noteId;
        } catch (PDOException $e) {
            $this->logError('DB add Error', $e, ['user_id' => $userId, 'album_id' => $albumId]);
            throw new RuntimeException('Could not add album note. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $noteId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM user_album_notes WHERE id = :noteId AND user_id = :userId'
            );
            $stmt->execute([':noteId' => $noteId, ':userId' => $userId]);

            $deleted = $stmt->rowCount() > 0;

            if ($deleted) {
                $this->logInfo('Album note deleted', ['user_id' => $userId, 'note_id' => $noteId]);
            }

            return $deleted;
        } catch (PDOException $e) {
            $this->logError('DB delete Error', $e, ['note_id' => $noteId, 'user_id' => $userId]);
            throw new RuntimeException('Could not delete album note. DB Error: ' . $e->getMessage(), 0, $e);
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
            $stmt = $this->db->prepare(
                'UPDATE user_album_notes
                 SET note_text  = :noteText,
                     note_type  = :noteType,
                     is_private = :isPrivate,
                     updated_at = NOW()
                 WHERE id = :noteId AND user_id = :userId'
            );
            $stmt->execute([
                ':noteId'    => $noteId,
                ':userId'    => $userId,
                ':noteText'  => $noteText,
                ':noteType'  => $noteType,
                ':isPrivate' => $isPrivate ? 1 : 0,
            ]);

            $updated = $stmt->rowCount() > 0;

            if ($updated) {
                $this->logInfo('Album note updated', ['user_id' => $userId, 'note_id' => $noteId]);
            }

            return $updated;
        } catch (PDOException $e) {
            $this->logError('DB update Error', $e, ['note_id' => $noteId, 'user_id' => $userId]);
            throw new RuntimeException('Could not update album note. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getById(int $noteId, int $userId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT id, note_text, note_type, is_private, created_at, updated_at
                 FROM user_album_notes
                 WHERE id = :noteId AND user_id = :userId'
            );
            $stmt->execute([':noteId' => $noteId, ':userId' => $userId]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            $this->logError('DB getById Error', $e, ['note_id' => $noteId, 'user_id' => $userId]);
            throw new RuntimeException('Could not get album note. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
