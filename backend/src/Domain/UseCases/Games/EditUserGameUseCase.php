<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Games;

use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\Game\GameTagRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\EditUserGameCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class EditUserGameUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserGameRepositoryInterface $userGameRepository,
        private readonly GameTagRepositoryInterface $gameTagRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof EditUserGameCommand) {
            throw new InvalidArgumentException('Command must be an instance of EditUserGameCommand');
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

        // Update user's game data
        $result = $this->userGameRepository->update(
            $command->userId,
            $command->gameId,
            $command->toArray()
        );
        
        // Update statuses if provided
        if ($command->statuses !== null) {
            $this->userGameRepository->updateStatuses(
                $command->userId,
                $command->gameId,
                $command->statuses
            );
        }
        
        // Update tags - always remove all existing and re-assign (same pattern as books/movies)
        $this->gameTagRepository->removeAllFromGame($command->userId, $command->gameId);

        // Add new tags
        foreach ($command->tags as $tag) {
            if (is_numeric($tag)) {
                $this->gameTagRepository->assignToGame($command->userId, $command->gameId, (int)$tag);
            }
        }
        
        return $result;
    }

    protected function getLogContext(): string
    {
        return 'EditUserGameUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Game data updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update game data';
    }
}
