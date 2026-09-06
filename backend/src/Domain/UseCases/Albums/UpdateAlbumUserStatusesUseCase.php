<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\Services\JournalService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateAlbumStatusesCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateAlbumUserStatusesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserAlbumRepositoryInterface $userAlbumRepository,
        private readonly AlbumRepositoryInterface $albumRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly FeedEventService $feedEventService,
        private readonly JournalService $journalService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): void
    {
        if (!$command instanceof UpdateAlbumStatusesCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateAlbumStatusesCommand');
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

        // Validate statuses against allowed values
        $allowedStatuses = $this->albumRepository->fetchAllowedStatuses();
        foreach ($command->statuses as $status) {
            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException("Invalid status: {$status}");
            }
        }

        $estadosPrevios = $this->userAlbumRepository->getUserStatuses($command->userId, $command->albumId);

        $this->userAlbumRepository->updateStatuses(
            $command->userId,
            $command->albumId,
            $command->statuses
        );

        $album = $this->albumRepository->findById($command->albumId);
        if ($album && !empty($command->statuses)) {
            $this->feedEventService->recordStatusChanged(
                $command->userId,
                'album',
                (string) $command->albumId,
                $album->getTitle(),
                $album->getCoverUrl(),
                '',
                implode(', ', $command->statuses)
            );

            // El diario apunta SOLO en la transición no-consumido → consumido.
            // Los estados previos se leen arriba, antes de sustituirlos: este
            // caso de uso recibe el conjunto entero y por sí solo no sabe qué
            // cambió. La regla de qué estado cuenta vive en
            // `JournalEntry::CONSUMED_STATUSES`, no aquí.
            $this->journalService->recordIfConsumed(
                $command->userId,
                'album',
                (string) $command->albumId,
                $album->getTitle(),
                $album->getCoverUrl(),
                $estadosPrevios,
                $command->statuses
            );
        }
    }

    protected function getLogContext(): string
    {
        return 'UpdateAlbumUserStatusesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Album statuses updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update album statuses';
    }
}
