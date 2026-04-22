<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\Model\Album;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\AddAlbumCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class AddAlbumUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly AlbumRepositoryInterface $albumRepository,
        private readonly UserAlbumRepositoryInterface $userAlbumRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): Album
    {
        if (!$command instanceof AddAlbumCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddAlbumCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Check if album already exists in the catalogue by Spotify ID
        $existingAlbum = $this->albumRepository->findBySpotifyId($command->spotifyId->toString());

        if (!$existingAlbum) {
            // Album does not exist yet — persist it from the command data
            $album = Album::fromArray(array_merge(
                $command->toAlbumArray(),
                ['userStatuses' => $command->statuses ?: ['in-wishlist']]
            ));
            $album = $this->albumRepository->save($album);
        } else {
            $album = $existingAlbum;
        }

        // Check if user already has this album
        if ($this->userAlbumRepository->hasAlbum($command->userId, $album->getId())) {
            throw new InvalidArgumentException('You already have this album in your library.');
        }

        // Add the album to the user's library
        $this->userAlbumRepository->add(
            $command->userId,
            $album->getId(),
            $command->statuses,
            $command->userRating?->toFloat(),
            $command->personalNotes,
            null, // completedAt — not supplied at add time
            $command->listenCount,
            $command->favoriteTrack
        );

        return $album;
    }

    protected function getLogContext(): string
    {
        return 'AddAlbumUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Album added successfully to user library';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to add album to user library';
    }
}
