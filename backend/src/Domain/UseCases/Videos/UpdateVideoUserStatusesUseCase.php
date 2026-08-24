<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateVideoStatusesCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateVideoUserStatusesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly UserVideoRepositoryInterface $userVideoRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly FeedEventService $feedEventService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof UpdateVideoStatusesCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateVideoStatusesCommand');
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

        $this->userVideoRepository->updateStatuses(
            $command->userId,
            $video->getId(),
            $command->statuses
        );

        if (!empty($command->statuses)) {
            $this->feedEventService->recordStatusChanged(
                $command->userId,
                'video',
                $video->getYouTubeId()->toString(),
                $video->getTitle(),
                $video->getCoverUrl(),
                '',
                implode(', ', $command->statuses)
            );
        }

        return true;
    }

    protected function getLogContext(): string
    {
        return 'UpdateVideoUserStatusesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Video statuses updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update video statuses';
    }
}
