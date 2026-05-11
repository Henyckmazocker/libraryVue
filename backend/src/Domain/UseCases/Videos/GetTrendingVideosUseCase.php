<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetTrendingVideosUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserVideoRepositoryInterface $userVideoRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * @param array{userId: int, limit?: int} $command
     */
    protected function doExecute($command): array
    {
        if (!is_array($command) || !isset($command['userId'])) {
            throw new InvalidArgumentException('Command must be an array with userId');
        }

        $userId = $command['userId'];
        $limit  = $command['limit'] ?? 10;

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$userId} not found");
        }

        return $this->userVideoRepository->findTrending($userId, $limit);
    }

    protected function getLogContext(): string
    {
        return 'GetTrendingVideosUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Trending videos retrieved successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to retrieve trending videos';
    }
}
