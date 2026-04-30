<?php
declare(strict_types=1);

namespace App\Domain\Repository\Game;

/**
 * Repository interface for User-Game relationship operations
 * 
 * Manages the many-to-many relationship between users and games
 */
interface UserGameRepositoryInterface
{
    /**
     * Add game to user's library
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param array $statuses Initial statuses
     * @param float|null $personalRating User's rating (0.5-5.0)
     * @param string|null $personalNotes User's notes
     * @param string|null $completedAt Date when game was completed
     * @param float|null $hoursPlayed Hours played
     * @param string|null $platformPlayed Platform user played on
     * @return void
     */
    public function add(
        int $userId,
        int $gameId,
        array $statuses = [],
        ?float $personalRating = null,
        ?string $personalNotes = null,
        ?string $completedAt = null,
        ?float $hoursPlayed = null,
        ?string $platformPlayed = null
    ): void;

    /**
     * Remove game from user's library
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @return bool Success
     */
    public function remove(int $userId, int $gameId): bool;

    /**
     * Check if user has a specific game
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @return bool
     */
    public function hasGame(int $userId, int $gameId): bool;

    /**
     * Get all games for a user
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @return array Games with user-specific data
     */
    public function findByUser(int $userId, array $filters = []): array;

    /**
     * Update user's game data
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param array $data Fields to update
     * @return bool Success
     */
    public function update(int $userId, int $gameId, array $data): bool;

    /**
     * Update user's game statuses
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param array $statuses New statuses
     * @return void
     */
    public function updateStatuses(int $userId, int $gameId, array $statuses): void;

    /**
     * Update user's rating for a game
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param float $rating New rating (0.5-5.0)
     * @return void
     */
    public function updateRating(int $userId, int $gameId, float $rating): void;

    /**
     * Update hours played
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param float $hoursPlayed Hours played
     * @return void
     */
    public function updateHoursPlayed(int $userId, int $gameId, float $hoursPlayed): void;

    /**
     * Get user's statuses for a specific game
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @return array Status names
     */
    public function getUserStatuses(int $userId, int $gameId): array;

    /**
     * Get games count for user
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @return int
     */
    public function countByUser(int $userId, array $filters = []): int;

    /**
     * Get trending games based on user activity and ratings
     *
     * @param int $limit Maximum number of games to return
     * @param int $daysWindow Time window in days
     * @param int|null $userId Optional user ID to check ownership
     * @return array Trending games with scores
     */
    public function getTrendingGames(int $limit = 20, int $daysWindow = 90, ?int $userId = null): array;
}
