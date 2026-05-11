<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Repository\Video\VideoNoteRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetVideoNotesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly VideoNoteRepositoryInterface $videoNoteRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * @param array{userId: int, youtubeId: string} $command
     */
    protected function doExecute($command): array
    {
        if (!is_array($command) || !isset($command['userId'], $command['youtubeId'])) {
            throw new InvalidArgumentException('Command must be an array with userId and youtubeId');
        }

        $user = $this->userRepository->findById($command['userId']);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command['userId']} not found");
        }

        $video = $this->videoRepository->findByYouTubeId($command['youtubeId']);
        if (!$video) {
            throw new InvalidArgumentException("Video not found");
        }

        return $this->videoNoteRepository->getByVideo($command['userId'], $video->getId());
    }

    protected function getLogContext(): string
    {
        return 'GetVideoNotesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Video notes retrieved successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to retrieve video notes';
    }
}
