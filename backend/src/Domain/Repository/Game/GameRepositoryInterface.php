<?php
declare(strict_types=1);

namespace App\Domain\Repository\Game;

use App\Domain\Model\Game;

/**
 * Repository interface for Game entity CRUD operations
 * 
 * Single Responsibility: Only manages Game entity persistence
 */
interface GameRepositoryInterface
{
    /**
     * Find game by ID (RAWG API ID)
     *
     * @param int $id Game identifier
     * @return Game|null
     */
    public function findById(int $id): ?Game;

    /**
     * Find game by slug
     *
     * @param string $slug Game slug
     * @return Game|null
     */
    public function findBySlug(string $slug): ?Game;

    /**
     * Find all games with optional filters
     *
     * @param array $filters Optional filters ['title' => string, 'genre' => string, 'platform' => string, etc.]
     * @return Game[]
     */
    public function findAll(array $filters = []): array;

    /**
     * Save new game
     *
     * @param Game $game
     * @return Game Game with assigned ID
     */
    public function save(Game $game): Game;

    /**
     * Update existing game
     *
     * @param Game $game
     * @return bool Success
     */
    public function update(Game $game): bool;

    /**
     * Delete game by ID
     *
     * @param int $id Game identifier
     * @return bool Success
     */
    public function delete(int $id): bool;

    /**
     * Get allowed status names from database
     *
     * @return string[]
     */
    public function fetchAllowedStatuses(): array;

    /**
     * Update game rating
     *
     * @param int $id Game identifier
     * @param float $rating New rating value
     * @return void
     */
    public function updateRating(int $id, float $rating): void;

    /**
     * Check if game exists in database
     *
     * @param int $id Game identifier
     * @return bool
     */
    public function exists(int $id): bool;
}
