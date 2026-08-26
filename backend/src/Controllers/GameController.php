<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\DTO\Commands\AddGameNoteCommand;
use App\Domain\UseCases\Games\AddGameNoteUseCase;
use App\Domain\UseCases\Games\AddGameUseCase;
use App\Domain\UseCases\Games\DeleteGameUseCase;
use App\Domain\UseCases\Games\UpdateGameRatingUseCase;
use App\Domain\UseCases\Games\UpdateGameUserStatusesUseCase;
use App\Domain\UseCases\Games\GetGamesUseCase;
use App\Domain\UseCases\Games\GetGameAllowedStatusesUseCase;
use App\Domain\UseCases\Games\EditUserGameUseCase;
use App\Domain\UseCases\Games\GetTrendingGamesUseCase;
use App\Domain\DTO\Commands\AddGameCommand;
use App\Domain\DTO\Commands\DeleteGameCommand;
use App\Domain\DTO\Commands\UpdateGameRatingCommand;
use App\Domain\DTO\Commands\UpdateGameStatusesCommand;
use App\Domain\DTO\Commands\EditUserGameCommand;
use App\Domain\DTO\Queries\GetTrendingGamesQuery;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Domain\Repository\Game\GameTagRepositoryInterface;
use App\Domain\Repository\Game\GameNoteRepositoryInterface;
use App\Domain\Services\IGDBService;

class GameController extends BaseController implements Contracts\GameControllerInterface
{
    private AddGameUseCase $addGameUseCase;
    private DeleteGameUseCase $deleteGameUseCase;
    private UpdateGameRatingUseCase $updateGameRatingUseCase;
    private UpdateGameUserStatusesUseCase $updateGameUserStatusesUseCase;
    private GetGamesUseCase $getGamesUseCase;
    private GetGameAllowedStatusesUseCase $getGameAllowedStatusesUseCase;
    private AuthMiddleware $authMiddleware;
    private EditUserGameUseCase $editUserGameUseCase;
    private GameTagRepositoryInterface $gameTagRepository;
    private GameNoteRepositoryInterface $gameNoteRepository;
    private AddGameNoteUseCase $addGameNoteUseCase;
    private IGDBService $igdbService;
    private GetTrendingGamesUseCase $getTrendingGamesUseCase;

    public function __construct(
        AddGameUseCase $addGameUseCase,
        DeleteGameUseCase $deleteGameUseCase,
        UpdateGameRatingUseCase $updateGameRatingUseCase,
        UpdateGameUserStatusesUseCase $updateGameUserStatusesUseCase,
        GetGamesUseCase $getGamesUseCase,
        GetGameAllowedStatusesUseCase $getGameAllowedStatusesUseCase,
        AuthMiddleware $authMiddleware,
        EditUserGameUseCase $editUserGameUseCase,
        GameTagRepositoryInterface $gameTagRepository,
        GameNoteRepositoryInterface $gameNoteRepository,
        AddGameNoteUseCase $addGameNoteUseCase,
        IGDBService $igdbService,
        GetTrendingGamesUseCase $getTrendingGamesUseCase
    ) {
        $this->addGameUseCase = $addGameUseCase;
        $this->deleteGameUseCase = $deleteGameUseCase;
        $this->updateGameRatingUseCase = $updateGameRatingUseCase;
        $this->updateGameUserStatusesUseCase = $updateGameUserStatusesUseCase;
        $this->getGamesUseCase = $getGamesUseCase;
        $this->getGameAllowedStatusesUseCase = $getGameAllowedStatusesUseCase;
        $this->editUserGameUseCase = $editUserGameUseCase;
        $this->authMiddleware = $authMiddleware;
        $this->gameTagRepository = $gameTagRepository;
        $this->gameNoteRepository = $gameNoteRepository;
        $this->addGameNoteUseCase = $addGameNoteUseCase;
        $this->igdbService = $igdbService;
        $this->getTrendingGamesUseCase = $getTrendingGamesUseCase;
    }

    /**
     * Add a new game to user's library
     * 
     * @param AddGameCommand $command Command containing game data and user ID
     * @return array Success response with game data
     */
    public function addGame(AddGameCommand $command): array
    {
        $addedGame = $this->addGameUseCase->execute($command);
        return $this->successResponse('Game added: ' . $addedGame->getTitle(), $addedGame->toArray(), 201);
    }

    /**
     * Delete a game from user's library
     * 
     * @param DeleteGameCommand $command Command containing user ID and game ID
     * @return array Success response
     */
    public function deleteGame(DeleteGameCommand $command): array
    {
        $this->deleteGameUseCase->execute($command);
        return $this->successResponse('Game removed from your library: ' . $command->gameId);
    }

    /**
     * Update game rating
     * 
     * @param UpdateGameRatingCommand $command Command containing user ID, game ID, and rating
     * @return array Success response
     */
    public function updateGameRating(UpdateGameRatingCommand $command): array
    {
        $this->updateGameRatingUseCase->execute($command);
        return $this->successResponse('Game rating updated successfully.');
    }

    /**
     * Update game user statuses
     * 
     * @param UpdateGameStatusesCommand $command Command containing user ID, game ID, and statuses
     * @return array Success response
     */
    public function updateGameUserStatuses(UpdateGameStatusesCommand $command): array
    {
        $this->updateGameUserStatusesUseCase->execute($command);
        return $this->successResponse('User statuses updated for Game ID ' . $command->gameId);
    }

    /**
     * Get allowed game statuses
     * 
     * @return array Success response with allowed statuses
     */
    public function getGameAllowedStatuses(): array
    {
        $statuses = $this->getGameAllowedStatusesUseCase->execute([]);
        return $this->successResponse('Allowed game statuses retrieved.', $statuses);
    }

    /**
     * Get user's games
     * 
     * @param array $query Query containing user ID and optional filters
     * @return array Success response with games data
     */
    public function getGames(array $query): array
    {
        $games = $this->getGamesUseCase->execute($query);
        
        // Convert Game domain objects to arrays for JSON response
        $gamesArray = array_map(function($game) {
            return $game->toArray();
        }, $games);
        
        return $this->successResponse('Games data retrieved.', $gamesArray);
    }

    /**
     * Edit all aspects of a user_game: main data, tags, and notes
     * 
     * @param EditUserGameCommand $command Command containing all edit data
     * @return array Success response
     */
    public function editUserGame(EditUserGameCommand $command): array
    {
        $this->editUserGameUseCase->execute($command);
        return $this->successResponse('User game updated successfully.');
    }

    /**
     * Get all tags for user's games
     * 
     * @param int $userId User ID
     * @return array Success response with tags data
     */
    public function getUserGameTags(int $userId): array
    {
        try {
            $tags = $this->gameTagRepository->findByUser($userId);
            return $this->successResponse('Tags retrieved successfully', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving tags: ' . $e->getMessage());
        }
    }

    /**
     * Create a new tag for user's games
     * 
     * @param int $userId User ID
     * @param string $name Tag name
     * @param string $color Tag color (hex format)
     * @return array Success response with created tag
     */
    public function createUserGameTag(int $userId, string $name, string $color = '#1976d2'): array
    {
        try {
            $tagId = $this->gameTagRepository->create($userId, $name, $color);
            $newTag = ['id' => $tagId, 'name' => $name, 'color' => $color];
            return $this->successResponse('Tag created successfully', $newTag);
        } catch (\Exception $e) {
            return $this->errorResponse('Error creating tag: ' . $e->getMessage());
        }
    }

    /**
     * Get tags for a specific game
     * 
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @return array Success response with game's tags
     */
    public function getGameTags(int $userId, int $gameId): array
    {
        try {
            $tags = $this->gameTagRepository->findByGame($userId, $gameId);
            return $this->successResponse('Game tags retrieved successfully', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving game tags: ' . $e->getMessage());
        }
    }

    /**
     * Delete a tag from user's games
     * 
     * @param int $userId User ID
     * @param int $tagId Tag ID
     * @return array Success response
     */
    public function deleteUserGameTag(int $userId, int $tagId): array
    {
        try {
            $deleted = $this->gameTagRepository->delete($userId, $tagId);
            if ($deleted) {
                return $this->successResponse('Tag deleted successfully');
            }
            return $this->errorResponse('Tag not found or could not be deleted', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error deleting tag: ' . $e->getMessage());
        }
    }

    /**
     * Assign a tag to a game
     * 
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param int $tagId Tag ID
     * @return array Success response
     */
    public function assignTagToGame(int $userId, int $gameId, int $tagId): array
    {
        try {
            $this->gameTagRepository->assignToGame($userId, $gameId, $tagId);
            return $this->successResponse('Tag assigned to game successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Error assigning tag to game: ' . $e->getMessage());
        }
    }

    /**
     * Remove a tag from a game
     * 
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param int $tagId Tag ID
     * @return array Success response
     */
    public function removeTagFromGame(int $userId, int $gameId, int $tagId): array
    {
        try {
            $removed = $this->gameTagRepository->removeFromGame($userId, $gameId, $tagId);
            if ($removed) {
                return $this->successResponse('Tag removed from game successfully');
            }
            return $this->errorResponse('Tag assignment not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error removing tag from game: ' . $e->getMessage());
        }
    }

    /**
     * Update tags for a game (remove all and assign new ones)
     * 
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param array $tagIds Array of tag IDs to assign
     * @return array Success response
     */
    public function updateGameTags(int $userId, int $gameId, array $tagIds): array
    {
        try {
            // Get current tags and remove them all
            $currentTags = $this->gameTagRepository->getGameTags($userId, $gameId);
            foreach ($currentTags as $tag) {
                $this->gameTagRepository->removeFromGame($userId, $gameId, $tag['id']);
            }
            
            // Assign new tags
            foreach ($tagIds as $tagId) {
                $this->gameTagRepository->assignToGame($userId, $gameId, (int)$tagId);
            }
            
            return $this->successResponse('Game tags updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Error updating game tags: ' . $e->getMessage());
        }
    }

    /**
     * Get IGDB API configuration (Client ID only - safe to expose to frontend)
     * 
     * @return array Response with IGDB Client ID
     */
    public function getIGDBConfig(): array
    {
        try {
            $clientId = $this->igdbService->getClientId();
            
            if (empty($clientId)) {
                return $this->externalServiceError('IGDB');
            }

            return $this->successResponse('IGDB configuration retrieved', [
                'clientId' => $clientId
            ]);
        } catch (\Exception $e) {
            return $this->externalServiceError('IGDB');
        }
    }

    /**
     * Get IGDB Access Token
     * Generates a new token using client credentials flow
     * 
     * @return array Response with access token and expiration
     */
    public function getIGDBToken(): array
    {
        try {
            $tokenInfo = $this->igdbService->getTokenInfo();

            return $this->successResponse('IGDB token retrieved', $tokenInfo);
        } catch (\Exception $e) {
            return $this->externalServiceError('IGDB');
        }
    }

    /**
     * Search games in IGDB (proxy endpoint to avoid CORS issues)
     * 
     * @param array $data Request data with 'query' and optional 'limit'
     * @return array Response with search results
     */
    public function searchIGDBGames(array $data): array
    {
        try {
            $query = $data['query'] ?? '';
            $limit = isset($data['limit']) ? (int)$data['limit'] : 20;

            if (empty($query)) {
                return $this->errorResponse('Query parameter is required', 400);
            }

            $result = $this->igdbService->searchGamesResilient($query, $limit);
            $games  = $result['data'];

            return $this->successResponse('Games found', [
                'games' => $games,
                'count' => count($games),
                'stale' => $result['stale'],
                'cached_at' => $result['cached_at'] ? date('c', $result['cached_at']) : null
            ]);
        } catch (\Exception $e) {
            return $this->externalServiceError('IGDB');
        }
    }

    /**
     * Get game by IGDB ID (proxy endpoint)
     * 
     * @param array $data Request data with 'gameId'
     * @return array Response with game data
     */
    public function getIGDBGameById(array $data): array
    {
        try {
            $gameId = isset($data['gameId']) ? (int)$data['gameId'] : 0;

            if ($gameId <= 0) {
                return $this->errorResponse('Valid gameId parameter is required', 400);
            }

            $game = $this->igdbService->getGameById($gameId);

            if ($game === null) {
                return $this->errorResponse('Game not found', 404);
            }

            return $this->successResponse('Game found', ['game' => $game]);
        } catch (\Exception $e) {
            return $this->externalServiceError('IGDB');
        }
    }

    /**
     * Get detailed game information from IGDB including screenshots (proxy endpoint)
     * 
     * @param array $data Request data with 'gameId'
     * @return array Response with detailed game data and screenshots
     */
    public function getIGDBGameDetails(array $data): array
    {
        try {
            $gameId = isset($data['gameId']) ? (int)$data['gameId'] : 0;

            if ($gameId <= 0) {
                return $this->errorResponse('Valid gameId parameter is required', 400);
            }

            $gameDetails = $this->igdbService->getGameDetails($gameId);

            if ($gameDetails === null) {
                return $this->errorResponse('Game not found', 404);
            }

            return $this->successResponse('Game details retrieved', $gameDetails);
        } catch (\Exception $e) {
            return $this->externalServiceError('IGDB');
        }
    }

    /**
     * Get notes for a specific game
     * 
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @return array Success response with game notes
     */
    public function getGameNotes(int $userId, int $gameId): array
    {
        try {
            $notes = $this->gameNoteRepository->getByGame($userId, $gameId);
            return $this->successResponse('Game notes retrieved successfully', $notes);
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving game notes: ' . $e->getMessage());
        }
    }

    /**
     * Add a note to a game
     * 
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param string $noteText Note content
     * @param string $noteType Note type ('note', 'highlight', etc.)
     * @param bool $isPrivate Privacy flag
     * @return array Success response with created note ID
     */
    /**
     * Añadir una nota a un game.
     *
     * Pasa por su use case desde el 2026-08-25. Antes hablaba directamente
     * con el repositorio, que era la razón por la que games no tenía dónde
     * poner la guarda de privacidad del feed.
     */
    public function addGameNote(AddGameNoteCommand $command): array
    {
        $note = $this->addGameNoteUseCase->execute($command);

        return $this->successResponse('Game note added successfully', ['note' => $note]);
    }

    /**
     * Update a game note
     * 
     * @param int $noteId Note ID
     * @param int $userId User ID
     * @param string $noteText New note content
     * @param string $noteType New note type
     * @param bool $isPrivate New privacy flag
     * @return array Success response
     */
    public function updateGameNote(
        int $noteId,
        int $userId,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): array {
        try {
            $updated = $this->gameNoteRepository->update($noteId, $userId, $noteText, $noteType, $isPrivate);
            if ($updated) {
                return $this->successResponse('Game note updated successfully');
            }
            return $this->errorResponse('Note not found or not authorized', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error updating game note: ' . $e->getMessage());
        }
    }

    /**
     * Delete a game note
     * 
     * @param int $noteId Note ID
     * @param int $userId User ID
     * @return array Success response
     */
    public function deleteGameNote(int $noteId, int $userId): array
    {
        try {
            $deleted = $this->gameNoteRepository->delete($noteId, $userId);
            if ($deleted) {
                return $this->successResponse('Game note deleted successfully');
            }
            return $this->errorResponse('Note not found or not authorized', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error deleting game note: ' . $e->getMessage());
        }
    }

    /**
     * Get trending games from local database
     * 
     * @param GetTrendingGamesQuery $query Query with limit and daysWindow
     * @return array Response with trending games
     */
    public function getTrendingGames(GetTrendingGamesQuery $query): array
    {
        // Get authenticated user ID from session
        $userId = $_SESSION['user_data']['id'] ?? null;
        
        // Create query with userId
        $queryWithUser = GetTrendingGamesQuery::create(
            $query->limit,
            $query->daysWindow,
            $userId
        );
        
        $trendingGames = $this->getTrendingGamesUseCase->execute($queryWithUser);
        return $this->successResponse('Trending games retrieved.', $trendingGames);
    }
}
