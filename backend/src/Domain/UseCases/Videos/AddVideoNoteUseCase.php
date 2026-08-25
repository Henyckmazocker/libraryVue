<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\Video\VideoNoteRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\AddVideoNoteCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class AddVideoNoteUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly UserVideoRepositoryInterface $userVideoRepository,
        private readonly VideoNoteRepositoryInterface $videoNoteRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly FeedEventService $feedEvents,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): int
    {
        if (!$command instanceof AddVideoNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddVideoNoteCommand');
        }

        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        $video = $this->videoRepository->findByYouTubeId($command->youtubeId);
        if (!$video) {
            throw new InvalidArgumentException("Video not found");
        }

        if (!$this->userVideoRepository->hasVideo($command->userId, $video->getId())) {
            throw new InvalidArgumentException("Video not found in your library");
        }

        $noteId = $this->videoNoteRepository->add(
            $command->userId,
            $video->getId(),
            $command->noteText,
            $command->noteType,
            $command->isPrivate
        );

        // Solo las notas públicas se publican. La guarda va aquí y no en
        // `FeedEventService`, que se traga sus errores por diseño: esconder ahí
        // una regla de privacidad convertiría un fallo silencioso en un escape
        // de privacidad silencioso.
        if (!$command->isPrivate) {
            $this->feedEvents->recordNotesUpdated(
                userId: $command->userId,
                entityType: 'video',
                // El id del feed es el de YouTube, como en `AddVideoUseCase`:
                // el id interno no significa nada fuera de esta base.
                entityId: (string) $video->getYouTubeId(),
                title: $video->getTitle(),
                cover: $video->getCoverUrl(),
                noteText: $command->noteText,
                noteType: $command->noteType
            );
        }

        return $noteId;
    }

    protected function getLogContext(): string
    {
        return 'AddVideoNoteUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Video note added successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to add video note';
    }
}
