<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\DeleteAlbumCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class DeleteAlbumUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserAlbumRepositoryInterface $userAlbumRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof DeleteAlbumCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteAlbumCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Check if user has this album
        if (!$this->userAlbumRepository->hasAlbum($command->userId, $command->albumId)) {
            throw new InvalidArgumentException('Album not found in your library');
        }

        return $this->userAlbumRepository->remove($command->userId, $command->albumId);
    }

    protected function getLogContext(): string
    {
        return 'DeleteAlbumUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Album deleted successfully from user library';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to delete album from user library';
    }
}
