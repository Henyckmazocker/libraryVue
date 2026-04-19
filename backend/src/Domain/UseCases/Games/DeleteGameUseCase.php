<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Games;

use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\DeleteGameCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class DeleteGameUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserGameRepositoryInterface $userGameRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof DeleteGameCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteGameCommand');
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

        // Remove game from user's library
        return $this->userGameRepository->remove($command->userId, $command->gameId);
    }

    protected function getLogContext(): string
    {
        return 'DeleteGameUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Game deleted successfully from user library';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to delete game from user library';
    }
}
