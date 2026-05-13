<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Games;

use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateGameRatingCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateGameRatingUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserGameRepositoryInterface $userGameRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly GameRepositoryInterface $gameRepository,
        private readonly FeedEventService $feedEventService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): void
    {
        if (!$command instanceof UpdateGameRatingCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateGameRatingCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Check if user has this game
        if (!$this->userGameRepository->hasGame($command->userId, $command->gameId)) {
            throw new InvalidArgumentException('Game not found in your library');
        }

        // Update rating
        $this->userGameRepository->updateRating(
            $command->userId,
            $command->gameId,
            $command->rating->toFloat()
        );

        $game = $this->gameRepository->findById($command->gameId);
        if ($game) {
            $this->feedEventService->recordItemRated(
                $command->userId,
                'game',
                (string) $command->gameId,
                $game->getTitle(),
                $game->getCoverUrl(),
                $command->rating->toFloat()
            );
        }
    }

    protected function getLogContext(): string
    {
        return 'UpdateGameRatingUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Game rating updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update game rating';
    }
}
