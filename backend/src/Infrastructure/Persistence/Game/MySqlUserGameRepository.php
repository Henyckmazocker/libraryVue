<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Game;

use App\Domain\Model\Game;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Infrastructure\Persistence\Game\Mappers\GameDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for User-Game relationships
 * Handles user-specific game operations and statuses
 */
final class MySqlUserGameRepository implements UserGameRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE = 'game_statuses';
    private const STATUS_LINK_TABLE = 'game_has_statuses';
    private const STATUS_COLUMN = 'game_id';

    public function __construct(
        private readonly PDO $db,
        private readonly GameDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    public function findByUser(int $userId, array $filters = []): array
    {
        try {
            $userId = (int) $userId;
            
            $sql = "
                SELECT g.*, ug.added_at as user_added_at, ug.personal_rating as user_rating,
                       ug.personal_notes, ug.hours_played, ug.platform_played, ug.completed_at,
                       ug.date_started, ug.date_finished,
                       iof.id AS ownership_format_id, iof.value AS ownership_format_value, iof.label AS ownership_format_label,
                       GROUP_CONCAT(gs.name SEPARATOR ', ') as user_statuses
                FROM games g
                INNER JOIN user_games ug ON g.id = ug.game_id
                LEFT JOIN user_game_statuses ugs ON g.id = ugs.game_id AND ugs.user_id = ug.user_id
                LEFT JOIN game_statuses gs ON ugs.status_id = gs.id
                LEFT JOIN item_owned_formats iof ON iof.id = ug.ownership_format_id
                WHERE ug.user_id = :userId
            ";

            $params = [':userId' => $userId];

            if (isset($filters['status']) && !empty($filters['status'])) {
                $sql .= " AND gs.name = :status";
                $params[':status'] = $filters['status'];
            }

            if (isset($filters['title']) && !empty($filters['title'])) {
                $sql .= " AND g.title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }

            if (isset($filters['genre']) && !empty($filters['genre'])) {
                $sql .= " AND JSON_CONTAINS(g.genres, :genre, '$')";
                $params[':genre'] = '"' . $filters['genre'] . '"';
            }

            if (isset($filters['platform']) && !empty($filters['platform'])) {
                $sql .= " AND JSON_CONTAINS(g.platforms, :platform, '$')";
                $params[':platform'] = '"' . $filters['platform'] . '"';
            }

            $sql .= " GROUP BY g.id, g.slug, g.title, g.release_date, g.developer, g.publisher, g.rating, g.coverUrl, g.backgroundUrl, g.description, g.platforms, g.genres, g.esrb_rating, g.playtime, g.metacritic_score, g.addedTimestamp, ug.added_at, ug.personal_rating, ug.personal_notes, ug.hours_played, ug.platform_played, ug.completed_at, ug.date_started, ug.date_finished, iof.id, iof.value, iof.label ORDER BY ug.added_at DESC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $gamesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->mapper->toDomainCollection($gamesData);

        } catch (PDOException $e) {
            $this->logError('DB Error finding games by user', $e, ['user_id' => $userId]);
            throw new RuntimeException("Could not find games by user. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function hasGame(int $userId, int $gameId): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM user_games WHERE user_id = :userId AND game_id = :gameId";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId
            ]);
            
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logError('DB Error checking user game', $e, [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);
            throw new RuntimeException("Could not check if user has game. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function add(
        int $userId,
        int $gameId,
        array $statuses = [],
        ?float $personalRating = null,
        ?string $personalNotes = null,
        ?string $completedAt = null,
        ?float $hoursPlayed = null,
        ?string $platformPlayed = null,
        ?string $dateStarted = null,
        ?string $dateFinished = null,
        ?int $ownershipFormatId = null
    ): void
    {
        try {
            $userId = (int) $userId;
            
            $this->db->beginTransaction();

            // Check if game exists
            $checkGame = $this->db->prepare("SELECT id FROM games WHERE id = :gameId");
            $checkGame->execute([':gameId' => $gameId]);
            
            if (!$checkGame->fetch()) {
                throw new RuntimeException("Game with ID {$gameId} does not exist. Please add the game first.");
            }

            // Add relationship between user and game
            $stmt = $this->db->prepare("
                INSERT INTO user_games (user_id, game_id, added_at, personal_rating, personal_notes, hours_played, platform_played, completed_at, date_started, date_finished, ownership_format_id) 
                VALUES (:userId, :gameId, NOW(), :personalRating, :personalNotes, :hoursPlayed, :platformPlayed, :completedAt, :dateStarted, :dateFinished, :ownershipFormatId)
                ON DUPLICATE KEY UPDATE 
                    added_at = NOW(),
                    personal_rating = COALESCE(VALUES(personal_rating), personal_rating),
                    personal_notes = COALESCE(VALUES(personal_notes), personal_notes),
                    hours_played = COALESCE(VALUES(hours_played), hours_played),
                    platform_played = COALESCE(VALUES(platform_played), platform_played),
                    completed_at = COALESCE(VALUES(completed_at), completed_at),
                    date_started = COALESCE(VALUES(date_started), date_started),
                    date_finished = COALESCE(VALUES(date_finished), date_finished),
                    ownership_format_id = COALESCE(VALUES(ownership_format_id), ownership_format_id)
            ");
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId,
                ':personalRating' => $personalRating,
                ':personalNotes' => $personalNotes,
                ':hoursPlayed' => $hoursPlayed,
                ':platformPlayed' => $platformPlayed,
                ':completedAt' => $completedAt,
                ':dateStarted' => $dateStarted,
                ':dateFinished' => $dateFinished,
                ':ownershipFormatId' => $ownershipFormatId
            ]);

            // Add statuses if provided
            if (!empty($statuses)) {
                $this->updateStatuses($userId, $gameId, $statuses);
            }

            $this->db->commit();
            
            $this->logInfo('Game added to user successfully', [
                'user_id' => $userId,
                'game_id' => $gameId,
                'statuses' => $statuses,
                'personal_rating' => $personalRating,
                'personal_notes' => $personalNotes,
                'hours_played' => $hoursPlayed,
                'platform_played' => $platformPlayed,
                'completed_at' => $completedAt,
                'date_started' => $dateStarted,
                'date_finished' => $dateFinished
            ]);
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error adding game to user', $e, [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);
            throw new RuntimeException("Could not add game to user. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Error adding game to user', $e, [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);
            throw new RuntimeException("An unexpected error occurred while adding game to user: " . $e->getMessage(), 0, $e);
        }
    }

    public function remove(int $userId, int $gameId): bool
    {
        try {
            $this->db->beginTransaction();

            // Remove user-specific statuses
            $stmtStatuses = $this->db->prepare("DELETE FROM user_game_statuses WHERE user_id = :userId AND game_id = :gameId");
            $stmtStatuses->execute([
                ':userId' => $userId,
                ':gameId' => $gameId
            ]);

            // Remove user-game relationship
            $stmt = $this->db->prepare("DELETE FROM user_games WHERE user_id = :userId AND game_id = :gameId");
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId
            ]);

            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();
            
            if ($deleted) {
                $this->logInfo('Game removed from user successfully', [
                    'user_id' => $userId,
                    'game_id' => $gameId
                ]);
            }
            
            return $deleted;

        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error removing game from user', $e, [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);
            throw new RuntimeException("Could not remove game from user. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Error removing game from user', $e, [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);
            throw new RuntimeException("An unexpected error occurred while removing game from user: " . $e->getMessage(), 0, $e);
        }
    }

    public function update(int $userId, int $gameId, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            $updates = [];
            $params = [':userId' => $userId, ':gameId' => $gameId];

            if (isset($data['personal_rating'])) {
                $updates[] = "personal_rating = :personalRating";
                $params[':personalRating'] = $data['personal_rating'] !== null ? (float) $data['personal_rating'] : null;
            }

            if (isset($data['personal_notes'])) {
                $updates[] = "personal_notes = :personalNotes";
                $params[':personalNotes'] = $data['personal_notes'];
            }

            if (isset($data['hours_played'])) {
                $updates[] = "hours_played = :hoursPlayed";
                $params[':hoursPlayed'] = $data['hours_played'] !== null ? (float) $data['hours_played'] : null;
            }

            if (isset($data['platform_played'])) {
                $updates[] = "platform_played = :platformPlayed";
                $params[':platformPlayed'] = $data['platform_played'];
            }

            if (isset($data['completed_at'])) {
                $updates[] = "completed_at = :completedAt";
                $params[':completedAt'] = $data['completed_at'];
            }

            if (isset($data['date_started'])) {
                $updates[] = "date_started = :dateStarted";
                $params[':dateStarted'] = $data['date_started'];
            }

            if (isset($data['date_finished'])) {
                $updates[] = "date_finished = :dateFinished";
                $params[':dateFinished'] = $data['date_finished'];
            }

            if (array_key_exists('ownership_format_id', $data)) {
                $updates[] = "ownership_format_id = :ownershipFormatId";
                $params[':ownershipFormatId'] = $data['ownership_format_id'] !== null ? (int) $data['ownership_format_id'] : null;
            }

            if (!empty($updates)) {
                $sql = "UPDATE user_games SET " . implode(', ', $updates);
                $sql .= " WHERE user_id = :userId AND game_id = :gameId";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            
            $this->logInfo('User game data updated successfully', [
                'user_id' => $userId,
                'game_id' => $gameId,
                'data' => $data
            ]);
            
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Error updating user game', $e, [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);
            throw new RuntimeException("Could not update user game. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Error updating user game', $e, [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);
            throw new RuntimeException("An unexpected error occurred while updating user game: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateStatuses(int $userId, int $gameId, array $statuses): void
    {
        $weStartedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $weStartedTransaction = true;
        }
        
        try {
            $userId = (int) $userId;

            // Remove existing statuses for this user-game combination
            $deleteStmt = $this->db->prepare("DELETE FROM user_game_statuses WHERE user_id = :userId AND game_id = :gameId");
            $deleteStmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId
            ]);

            // Add new statuses
            if (!empty($statuses)) {
                $insertStmt = $this->db->prepare("
                    INSERT INTO user_game_statuses (user_id, game_id, status_id) 
                    VALUES (:userId, :gameId, :statusId)
                ");

                foreach ($statuses as $statusName) {
                    $statusId = $this->getStatusId($statusName);
                    if ($statusId !== null) {
                        $insertStmt->execute([
                            ':userId' => $userId,
                            ':gameId' => $gameId,
                            ':statusId' => $statusId
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
            $this->logError('DB Error updating user game statuses', $e, [
                'game_id' => $gameId,
                'statuses' => $statuses,
                'user_id' => $userId
            ]);
            throw new RuntimeException("Could not update user game statuses. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error updating user game statuses', $e, [
                'game_id' => $gameId,
                'statuses' => $statuses,
                'user_id' => $userId
            ]);
            throw new RuntimeException("An unexpected error occurred while updating user game statuses: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateRating(int $userId, int $gameId, float $rating): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_games 
                SET personal_rating = :rating 
                WHERE user_id = :userId AND game_id = :gameId
            ");
            
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId,
                ':rating' => $rating
            ]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException("No user-game relationship found to update rating. userId=$userId, gameId=$gameId");
            }
            
            $this->logInfo('User game rating updated', [
                'user_id' => $userId,
                'game_id' => $gameId,
                'rating' => $rating
            ]);

        } catch (PDOException $e) {
            $this->logError('DB Error updating user game rating', $e, [
                'user_id' => $userId,
                'game_id' => $gameId,
                'rating' => $rating
            ]);
            throw new RuntimeException("Could not update user game rating. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateHoursPlayed(int $userId, int $gameId, float $hoursPlayed): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_games 
                SET hours_played = :hoursPlayed 
                WHERE user_id = :userId AND game_id = :gameId
            ");
            
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId,
                ':hoursPlayed' => $hoursPlayed
            ]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException("No user-game relationship found to update hours played. userId=$userId, gameId=$gameId");
            }
            
            $this->logInfo('User game hours played updated', [
                'user_id' => $userId,
                'game_id' => $gameId,
                'hours_played' => $hoursPlayed
            ]);

        } catch (PDOException $e) {
            $this->logError('DB Error updating user game hours played', $e, [
                'user_id' => $userId,
                'game_id' => $gameId,
                'hours_played' => $hoursPlayed
            ]);
            throw new RuntimeException("Could not update user game hours played. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserStatuses(int $userId, int $gameId): array
    {
        try {
            $sql = "
                SELECT gs.name 
                FROM game_statuses gs
                INNER JOIN user_game_statuses ugs ON gs.id = ugs.status_id
                WHERE ugs.user_id = :userId AND ugs.game_id = :gameId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':gameId' => $gameId
            ]);

            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        } catch (PDOException $e) {
            $this->logError('DB Error getting user game statuses', $e, [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);
            throw new RuntimeException("Could not get user game statuses. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function countByUser(int $userId, array $filters = []): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM user_games WHERE user_id = :userId";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            
            return (int)$stmt->fetchColumn();

        } catch (PDOException $e) {
            $this->logError('DB Error counting user games', $e, ['user_id' => $userId]);
            throw new RuntimeException("Could not count user games. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function getTrendingGames(int $limit = 20, int $daysWindow = 90, ?int $userId = null): array
    {
        try {
            $playingStatusId = $this->getStatusId('playing');
            $recentDays = 30;
            
            // Build user library check if userId provided
            $userLibraryCheck = $userId 
                ? "EXISTS(SELECT 1 FROM user_games ug2 WHERE ug2.game_id = g.id AND ug2.user_id = {$userId}) as is_in_user_library,"
                : "0 as is_in_user_library,";
            
            // Use string interpolation for INTERVAL and repeated parameters
            // Values are type-hinted as int, so they're safe
            $sql = "
                SELECT 
                    g.id as igdbId,
                    g.id as gameId,
                    g.title as title,
                    g.title as name,
                    g.release_date as releaseDate,
                    g.release_date as released,
                    g.coverUrl,
                    g.backgroundUrl as background_image,
                    g.description,
                    g.platforms,
                    g.genres,
                    g.developer as developers,
                    g.publisher as publishers,
                    g.rating,
                    {$userLibraryCheck}
                    COUNT(DISTINCT ug.user_id) as user_count,
                    AVG(ug.personal_rating) as avg_rating,
                    SUM(CASE 
                        WHEN ug.added_at >= DATE_SUB(NOW(), INTERVAL {$recentDays} DAY) 
                        THEN 1 ELSE 0 
                    END) as recent_adds,
                    SUM(CASE 
                        WHEN ugs.status_id = {$playingStatusId}
                        THEN 1 ELSE 0 
                    END) as playing_count,
                    MAX(ug.added_at) as last_added,
                    -- Trending score calculation
                    (
                        (COUNT(DISTINCT ug.user_id) * 10) +
                        (COALESCE(AVG(ug.personal_rating), 0) * 5) +
                        (SUM(CASE WHEN ug.added_at >= DATE_SUB(NOW(), INTERVAL {$recentDays} DAY) THEN 1 ELSE 0 END) * 15) +
                        (SUM(CASE WHEN ugs.status_id = {$playingStatusId} THEN 1 ELSE 0 END) * 8) -
                        (DATEDIFF(NOW(), MAX(ug.added_at)) * 0.1)
                    ) as trending_score
                FROM user_games ug
                INNER JOIN games g ON ug.game_id = g.id
                LEFT JOIN user_game_statuses ugs ON g.id = ugs.game_id AND ug.user_id = ugs.user_id
                WHERE ug.added_at >= DATE_SUB(NOW(), INTERVAL {$daysWindow} DAY)
                GROUP BY g.id, g.title, g.release_date, g.coverUrl, g.backgroundUrl, 
                         g.description, g.platforms, g.genres, g.developer, g.publisher, g.rating
                HAVING user_count >= 1
                ORDER BY trending_score DESC
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logDebug('Trending games fetched', [
                'count' => count($results),
                'limit' => $limit,
                'daysWindow' => $daysWindow
            ]);

            return $results;

        } catch (PDOException $e) {
            $this->logError('Error getting trending games', $e, [
                'limit' => $limit,
                'daysWindow' => $daysWindow
            ]);
            throw new RuntimeException("Could not get trending games: " . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

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
