<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Games;

use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetGamesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserGameRepositoryInterface $userGameRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute the use case
     * 
     * @param array{userId: int, filters?: array} $command
     * @return array
     */
    protected function doExecute($command): array
    {
        if (!is_array($command) || !isset($command['userId'])) {
            throw new InvalidArgumentException('Command must be an array with userId');
        }

        $userId = $command['userId'];
        $filters = $command['filters'] ?? [];

        // Validate user exists
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$userId} not found");
        }

        // Get user's games with filters
        return $this->userGameRepository->findByUser($userId, $filters);
    }

    protected function getLogContext(): string
    {
        return 'GetGamesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'User games retrieved successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to retrieve user games';
    }
}
