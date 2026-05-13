<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateAlbumRatingCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateAlbumRatingUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserAlbumRepositoryInterface $userAlbumRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AlbumRepositoryInterface $albumRepository,
        private readonly FeedEventService $feedEventService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): void
    {
        if (!$command instanceof UpdateAlbumRatingCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateAlbumRatingCommand');
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

        $this->userAlbumRepository->updateRating(
            $command->userId,
            $command->albumId,
            $command->rating->toFloat()
        );

        $album = $this->albumRepository->findById($command->albumId);
        if ($album) {
            $this->feedEventService->recordItemRated(
                $command->userId,
                'album',
                (string) $command->albumId,
                $album->getTitle(),
                $album->getCoverUrl(),
                $command->rating->toFloat()
            );
        }
    }

    protected function getLogContext(): string
    {
        return 'UpdateAlbumRatingUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Album rating updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update album rating';
    }
}
