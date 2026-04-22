<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetAlbumsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserAlbumRepositoryInterface $userAlbumRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
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

        return $this->userAlbumRepository->findByUser($userId, $filters);
    }

    protected function getLogContext(): string
    {
        return 'GetAlbumsUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'User albums retrieved successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to retrieve user albums';
    }
}
