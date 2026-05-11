<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\Video\VideoNoteRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
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

        return $this->videoNoteRepository->add(
            $command->userId,
            $video->getId(),
            $command->noteText,
            $command->noteType,
            $command->isPrivate
        );
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
