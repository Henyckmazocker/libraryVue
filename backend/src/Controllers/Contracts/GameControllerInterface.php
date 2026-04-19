<?php

declare(strict_types=1);

namespace App\Controllers\Contracts;

use App\Domain\DTO\Commands\AddGameCommand;
use App\Domain\DTO\Commands\DeleteGameCommand;
use App\Domain\DTO\Commands\UpdateGameRatingCommand;
use App\Domain\DTO\Commands\UpdateGameStatusesCommand;
use App\Domain\DTO\Commands\EditUserGameCommand;
use App\Domain\DTO\Queries\GetTrendingGamesQuery;

/**
 * Interface for GameController
 * Defines contract for game-related operations
 */
interface GameControllerInterface
{
    /**
     * Add a new game to user's library
     */
    public function addGame(AddGameCommand $command): array;

    /**
     * Delete a game from user's library
     */
    public function deleteGame(DeleteGameCommand $command): array;

    /**
     * Update game rating
     */
    public function updateGameRating(UpdateGameRatingCommand $command): array;

    /**
     * Update game user statuses
     */
    public function updateGameUserStatuses(UpdateGameStatusesCommand $command): array;

    /**
     * Edit user game data
     */
    public function editUserGame(EditUserGameCommand $command): array;

    /**
     * Get allowed statuses for games
     */
    public function getGameAllowedStatuses(): array;

    /**
     * Get user's games
     */
    public function getGames(array $params): array;

    /**
     * Get trending games
     */
    public function getTrendingGames(GetTrendingGamesQuery $query): array;

    /**
     * Get user's game tags
     */
    public function getUserGameTags(int $userId): array;

    /**
     * Create a new game tag
     */
    public function createUserGameTag(int $userId, string $name, string $color): array;

    /**
     * Delete a game tag
     */
    public function deleteUserGameTag(int $userId, int $tagId): array;

    /**
     * Get tags for a specific game
     */
    public function getGameTags(int $userId, int $gameId): array;

    /**
     * Assign tag to game
     */
    public function assignTagToGame(int $userId, int $gameId, int $tagId): array;

    /**
     * Remove tag from game
     */
    public function removeTagFromGame(int $userId, int $gameId, int $tagId): array;

    /**
     * Get notes for a game
     */
    public function getGameNotes(int $userId, int $gameId): array;

    /**
     * Add note to game
     */
    public function addGameNote(int $userId, int $gameId, string $noteText, string $noteType): array;

    /**
     * Update game note
     */
    public function updateGameNote(int $userId, int $noteId, string $noteText): array;

    /**
     * Delete game note
     */
    public function deleteGameNote(int $userId, int $noteId): array;

    /**
     * Get IGDB configuration
     */
    public function getIGDBConfig(): array;

    /**
     * Get IGDB token
     */
    public function getIGDBToken(): array;

    /**
     * Search games in IGDB
     */
    public function searchIGDBGames(array $data): array;

    /**
     * Get IGDB game by ID
     */
    public function getIGDBGameById(array $data): array;

    /**
     * Get IGDB game details
     */
    public function getIGDBGameDetails(array $data): array;
}
