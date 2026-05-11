<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetVideosUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserVideoRepositoryInterface $userVideoRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * @param array{userId: int, filters?: array} $command
     */
    protected function doExecute($command): array
    {
        if (!is_array($command) || !isset($command['userId'])) {
            throw new InvalidArgumentException('Command must be an array with userId');
        }

        $userId  = $command['userId'];
        $filters = $command['filters'] ?? [];

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$userId} not found");
        }

        return $this->userVideoRepository->findByUser($userId, $filters);
    }

    protected function getLogContext(): string
    {
        return 'GetVideosUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'User videos retrieved successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to retrieve user videos';
    }
}
