<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Game;

use App\Domain\Repository\Game\GameTagRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Game Tag management
 * Handles user-specific game tags
 */
final class MySqlGameTagRepository implements GameTagRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function findByUser(int $userId): array
    {
        try {
            $sql = 'SELECT id, name, color FROM user_game_tags WHERE user_id = :userId ORDER BY name';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB Error getting user game tags', $e, ['userId' => $userId]);
            throw new RuntimeException('Could not get user game tags. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findByGame(int $userId, int $gameId): array
    {
        try {
            $sql = 'SELECT t.id, t.name, t.color 
                    FROM user_game_tag_assignments a
                    INNER JOIN user_game_tags t ON a.tag_id = t.id
                    WHERE a.user_id = :userId AND a.game_id = :gameId
                    ORDER BY t.name';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB Error getting game tags', $e, [
                'userId' => $userId,
                'gameId' => $gameId
            ]);
            throw new RuntimeException('Could not get game tags. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function create(int $userId, string $name, string $color = '#007bff'): int
    {
        try {
            $sql = 'INSERT INTO user_game_tags (user_id, name, color) VALUES (:userId, :name, :color)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':name' => $name,
                ':color' => $color
            ]);
            
            $tagId = (int)$this->db->lastInsertId();
            
            $this->logInfo('Game tag created successfully', [
                'user_id' => $userId,
                'tag_id' => $tagId,
                'name' => $name
            ]);
            
            return $tagId;
        } catch (PDOException $e) {
            $this->logError('DB Error creating game tag', $e, [
                'user_id' => $userId,
                'name' => $name
            ]);
            throw new RuntimeException('Could not create game tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $userId, int $tagId): bool
    {
        $this->db->beginTransaction();
        try {
            // First, remove all tag assignments for this user and tag
            $stmtAssignments = $this->db->prepare('DELETE FROM user_game_tag_assignments WHERE user_id = :userId AND tag_id = :tagId');
            $stmtAssignments->execute([
                ':userId' => $userId,
                ':tagId' => $tagId
            ]);

            // Then, delete the tag itself
            $stmt = $this->db->prepare('DELETE FROM user_game_tags WHERE id = :tagId AND user_id = :userId');
            $stmt->execute([
                ':tagId' => $tagId,
                ':userId' => $userId
            ]);
            
            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();

            if ($deleted) {
                $this->logInfo('Game tag deleted successfully', [
                    'user_id' => $userId,
                    'tag_id' => $tagId
                ]);
            }
            
            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error deleting game tag', $e, [
                'user_id' => $userId,
                'tag_id' => $tagId
            ]);
            throw new RuntimeException('Could not delete game tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function assignToGame(int $userId, int $gameId, int $tagId): void
    {
        try {
            // First verify that the tag belongs to this user
            $verifyStmt = $this->db->prepare('SELECT id FROM user_game_tags WHERE id = :tagId AND user_id = :userId');
            $verifyStmt->execute([
                ':tagId' => $tagId,
                ':userId' => $userId
            ]);
            
            if (!$verifyStmt->fetch()) {
                throw new RuntimeException("Tag does not belong to user or does not exist. tagId=$tagId, userId=$userId");
            }

            $sql = 'INSERT IGNORE INTO user_game_tag_assignments (user_id, game_id, tag_id) VALUES (:userId, :gameId, :tagId)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId,
                ':tagId' => $tagId
            ]);
            
            $this->logInfo('Tag assigned to game successfully', [
                'user_id' => $userId,
                'game_id' => $gameId,
                'tag_id' => $tagId
            ]);
        } catch (PDOException $e) {
            $this->logError('DB Error assigning tag to game', $e, [
                'user_id' => $userId,
                'game_id' => $gameId,
                'tag_id' => $tagId
            ]);
            throw new RuntimeException('Could not assign tag to game. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function removeFromGame(int $userId, int $gameId, int $tagId): void
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM user_game_tag_assignments WHERE user_id = :userId AND game_id = :gameId AND tag_id = :tagId');
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId,
                ':tagId' => $tagId
            ]);
            
            $removed = $stmt->rowCount() > 0;
            
            if ($removed) {
                $this->logInfo('Tag removed from game successfully', [
                    'user_id' => $userId,
                    'game_id' => $gameId,
                    'tag_id' => $tagId
                ]);
            }
        } catch (PDOException $e) {
            $this->logError('DB Error removing tag from game', $e, [
                'user_id' => $userId,
                'game_id' => $gameId,
                'tag_id' => $tagId
            ]);
            throw new RuntimeException('Could not remove tag from game. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function removeAllFromGame(int $userId, int $gameId): void
    {
        try {
            $sql = 'DELETE FROM user_game_tag_assignments WHERE user_id = :userId AND game_id = :gameId';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId
            ]);
        } catch (PDOException $e) {
            $this->logError('DB Error removing all tags from game', $e, [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);
            throw new RuntimeException('Could not remove all tags from game. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getGameTags(int $userId, int $gameId): array
    {
        return $this->findByGame($userId, $gameId);
    }

    public function getGamesWithTag(int $userId, int $tagId): array
    {
        try {
            $sql = 'SELECT game_id FROM user_game_tag_assignments WHERE user_id = :userId AND tag_id = :tagId';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':tagId' => $tagId
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (PDOException $e) {
            $this->logError('DB Error getting games with tag', $e, [
                'user_id' => $userId,
                'tag_id' => $tagId
            ]);
            throw new RuntimeException('Could not get games with tag. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
