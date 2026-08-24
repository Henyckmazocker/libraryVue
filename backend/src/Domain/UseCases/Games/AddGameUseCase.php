<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Games;

use App\Domain\Model\Game;
use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Services\CoverService;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\AddGameCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class AddGameUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly GameRepositoryInterface $gameRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserGameRepositoryInterface $userGameRepository,
        private readonly FeedEventService $feedEventService,
        private readonly CoverService $coverService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): Game
    {
        if (!$command instanceof AddGameCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddGameCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Check if user already has this game
        if ($this->userGameRepository->hasGame($command->userId, $command->id->toInt())) {
            throw new InvalidArgumentException('You already have this game in your library.');
        }

        // Check if game exists in the system
        $existingGame = $this->gameRepository->findById($command->id->toInt());
        
        if (!$existingGame) {
            // Game doesn't exist, create it first.
            // userStatuses must be non-empty for Game::fromArray(); use the command's statuses
            // or fall back to a placeholder — actual user statuses are stored in user_game_statuses.
            $gameData = $command->toArray();
            if (empty($gameData['userStatuses'])) {
                $gameData['userStatuses'] = ['library'];
            }
            $game = Game::fromArray($gameData);
            $this->gameRepository->save($game);
        } else {
            // Game exists, use existing game data
            $game = $existingGame;
        }

        // Add the game to user's library with their specific statuses
        $this->userGameRepository->add(
            $command->userId, 
            $command->id->toInt(), 
            $command->statuses,
            $command->userRating?->toFloat(),
            $command->personalNotes, // Personal notes from frontend
            null, // completedAt - not provided in AddGameCommand
            $command->hoursPlayed,
            $command->platformPlayed,
            $command->dateStarted,
            $command->dateFinished,
            $command->ownershipFormatId
        );

        $this->feedEventService->recordItemAdded(
            $command->userId,
            'game',
            (string) $command->id->toInt(),
            $game->getTitle(),
            $game->getCoverUrl()
        );

        // Copia local de la portada: registra la fila ahora (sin red) y deja la
        // descarga para después de la respuesta. Un fallo aquí nunca afecta al
        // guardado; lo pendiente lo recoge `bin/mirror covers:backfill`.
        $this->coverService->recordCover(
            'game',
            (string) $command->id->toInt(),
            $game->getCoverUrl()
        );

        return $game;
    }

    protected function getLogContext(): string
    {
        return 'AddGameUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Game added successfully to user library';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to add game to user library';
    }
}
