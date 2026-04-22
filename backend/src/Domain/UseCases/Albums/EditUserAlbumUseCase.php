<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\Album\AlbumTagRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\EditUserAlbumCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class EditUserAlbumUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserAlbumRepositoryInterface $userAlbumRepository,
        private readonly AlbumTagRepositoryInterface $albumTagRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof EditUserAlbumCommand) {
            throw new InvalidArgumentException('Command must be an instance of EditUserAlbumCommand');
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

        // Update user-album data
        $result = $this->userAlbumRepository->update(
            $command->userId,
            $command->albumId,
            $command->toArray()
        );

        // Update statuses only if explicitly provided (null = do not touch)
        if ($command->statuses !== null) {
            $this->userAlbumRepository->updateStatuses(
                $command->userId,
                $command->albumId,
                $command->statuses
            );
        }

        // Sync tags — always replace all existing assignments
        $this->albumTagRepository->removeAllFromAlbum($command->userId, $command->albumId);
        foreach ($command->tags as $tag) {
            if (is_numeric($tag)) {
                $this->albumTagRepository->assignToAlbum($command->userId, $command->albumId, (int)$tag);
            }
        }

        return $result;
    }

    protected function getLogContext(): string
    {
        return 'EditUserAlbumUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Album data updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update album data';
    }
}
