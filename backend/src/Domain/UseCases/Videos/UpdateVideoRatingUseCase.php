<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateVideoRatingCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateVideoRatingUseCase extends AbstractUseCase
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
        if (!$command instanceof UpdateVideoRatingCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateVideoRatingCommand');
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

        $this->userVideoRepository->updateRating(
            $command->userId,
            $video->getId(),
            $command->rating->toFloat()
        );

        $this->feedEventService->recordItemRated(
            $command->userId,
            'video',
            $video->getYouTubeId()->toString(),
            $video->getTitle(),
            $video->getCoverUrl(),
            $command->rating->toFloat()
        );

        return true;
    }

    protected function getLogContext(): string
    {
        return 'UpdateVideoRatingUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Video rating updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update video rating';
    }
}
