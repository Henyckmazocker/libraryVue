<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Game;

use App\Domain\Repository\Game\GameNoteRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Game Note management
 * Handles user-specific game notes
 */
final class MySqlGameNoteRepository implements GameNoteRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function getByGame(int $userId, int $gameId): array
    {
        try {
            $sql = 'SELECT id, note_text, note_type, is_private, created_at, updated_at
                    FROM user_game_notes
                    WHERE user_id = :userId AND game_id = :gameId
                    ORDER BY created_at DESC';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB Error getting game notes', $e, [
                'userId' => $userId,
                'gameId' => $gameId
            ]);
            throw new RuntimeException('Could not get game notes. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function add(int $userId, int $gameId, string $noteText, string $noteType = 'note', bool $isPrivate = true): int
    {
        try {
            $sql = 'INSERT INTO user_game_notes (user_id, game_id, note_text, note_type, is_private, created_at) 
                    VALUES (:userId, :gameId, :noteText, :noteType, :isPrivate, NOW())';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId,
                ':noteText' => $noteText,
                ':noteType' => $noteType,
                ':isPrivate' => $isPrivate ? 1 : 0
            ]);
            
            $noteId = (int)$this->db->lastInsertId();
            
            $this->logInfo('Game note added successfully', [
                'user_id' => $userId,
                'game_id' => $gameId,
                'note_id' => $noteId,
                'note_type' => $noteType
            ]);
            
            return $noteId;
        } catch (PDOException $e) {
            $this->logError('DB Error adding game note', $e, [
                'userId' => $userId,
                'gameId' => $gameId
            ]);
            throw new RuntimeException('Could not add game note. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $noteId, int $userId): bool
    {
        try {
            $sql = 'DELETE FROM user_game_notes WHERE id = :noteId AND user_id = :userId';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':noteId' => $noteId,
                ':userId' => $userId
            ]);
            
            $deleted = $stmt->rowCount() > 0;
            
            if ($deleted) {
                $this->logInfo('Game note deleted successfully', [
                    'user_id' => $userId,
                    'note_id' => $noteId
                ]);
            }
            
            return $deleted;
        } catch (PDOException $e) {
            $this->logError('DB Error deleting game note', $e, [
                'noteId' => $noteId,
                'userId' => $userId
            ]);
            throw new RuntimeException('Could not delete game note. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(int $noteId, int $userId, string $noteText, string $noteType = 'note', bool $isPrivate = true): bool
    {
        try {
            $sql = 'UPDATE user_game_notes 
                    SET note_text = :noteText, 
                        note_type = :noteType, 
                        is_private = :isPrivate,
                        updated_at = NOW()
                    WHERE id = :noteId AND user_id = :userId';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':noteId' => $noteId,
                ':userId' => $userId,
                ':noteText' => $noteText,
                ':noteType' => $noteType,
                ':isPrivate' => $isPrivate ? 1 : 0
            ]);
            
            $updated = $stmt->rowCount() > 0;
            
            if ($updated) {
                $this->logInfo('Game note updated successfully', [
                    'user_id' => $userId,
                    'note_id' => $noteId
                ]);
            }
            
            return $updated;
        } catch (PDOException $e) {
            $this->logError('DB Error updating game note', $e, [
                'noteId' => $noteId,
                'userId' => $userId
            ]);
            throw new RuntimeException('Could not update game note. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getById(int $noteId, int $userId): ?array
    {
        try {
            $sql = 'SELECT id, game_id, note_text, note_type, is_private, created_at, updated_at
                    FROM user_game_notes
                    WHERE id = :noteId AND user_id = :userId';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':noteId' => $noteId,
                ':userId' => $userId
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            $this->logError('DB Error getting game note by ID', $e, [
                'noteId' => $noteId,
                'userId' => $userId
            ]);
            throw new RuntimeException('Could not get game note. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
