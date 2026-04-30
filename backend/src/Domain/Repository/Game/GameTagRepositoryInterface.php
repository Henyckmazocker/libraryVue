<?php
declare(strict_types=1);

namespace App\Domain\Repository\Game;

/**
 * Repository interface for User Game Tags operations
 * 
 * Manages custom tags for games per user
 */
interface GameTagRepositoryInterface
{
    /**
     * Get all tags for a user
     *
     * @param int $userId User ID
     * @return array Tags with id, name, color
     */
    public function findByUser(int $userId): array;

    /**
     * Create a new tag for user
     *
     * @param int $userId User ID
     * @param string $name Tag name
     * @param string $color Tag color (hex)
     * @return int New tag ID
     */
    public function create(int $userId, string $name, string $color = '#007bff'): int;

    /**
     * Delete a tag
     *
     * @param int $userId User ID
     * @param int $tagId Tag ID
     * @return bool Success
     */
    public function delete(int $userId, int $tagId): bool;

    /**
     * Assign tag to game
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param int $tagId Tag ID
     * @return void
     */
    public function assignToGame(int $userId, int $gameId, int $tagId): void;

    /**
     * Remove tag from game
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param int $tagId Tag ID
     * @return void
     */
    public function removeFromGame(int $userId, int $gameId, int $tagId): void;

    /**
     * Remove all tags from a specific game
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @return void
     */
    public function removeAllFromGame(int $userId, int $gameId): void;

    /**
     * Get all tags for a specific game
     *
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @return array Tag IDs
     */
    public function getGameTags(int $userId, int $gameId): array;

    /**
     * Get games with a specific tag
     *
     * @param int $userId User ID
     * @param int $tagId Tag ID
     * @return array Game IDs
     */
    public function getGamesWithTag(int $userId, int $tagId): array;
}
