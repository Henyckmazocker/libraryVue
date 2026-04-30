<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Games;

use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateGameStatusesCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateGameUserStatusesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserGameRepositoryInterface $userGameRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly GameRepositoryInterface $gameRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): void
    {
        if (!$command instanceof UpdateGameStatusesCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateGameStatusesCommand');
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

        // Validate statuses against allowed statuses
        $allowedStatuses = $this->gameRepository->fetchAllowedStatuses();
        foreach ($command->statuses as $status) {
            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException("Invalid status: {$status}");
            }
        }

        // Update statuses
        $this->userGameRepository->updateStatuses(
            $command->userId,
            $command->gameId,
            $command->statuses
        );
    }

    protected function getLogContext(): string
    {
        return 'UpdateGameUserStatusesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Game statuses updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update game statuses';
    }
}
