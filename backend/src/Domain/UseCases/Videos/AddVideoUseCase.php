<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Model\Video;
use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\AddVideoCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class AddVideoUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly UserVideoRepositoryInterface $userVideoRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): Video
    {
        if (!$command instanceof AddVideoCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddVideoCommand');
        }

        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Check if video already exists in the shared catalog
        $video = $this->videoRepository->findByYouTubeId($command->youtubeId->toString());

        if (!$video) {
            // Persist new video to catalog
            $video = Video::fromArray(array_merge([
                'youtube_id'      => $command->youtubeId->toString(),
                'title'           => $command->title,
                'channel_name'    => $command->channelName,
                'channel_id'      => $command->channelId,
                'cover_url'       => $command->coverUrl,
                'duration'        => $command->duration,
                'duration_seconds'=> $command->durationSeconds,
                'view_count'      => $command->viewCount,
                'like_count'      => $command->likeCount,
                'published_at'    => $command->publishedAt,
                'description'     => $command->description,
                'categories'      => $command->categories,
            ], ['userStatuses' => $command->statuses ?: ['saved']]));
            $video = $this->videoRepository->save($video);
        }

        // Prevent duplicates in user library
        if ($this->userVideoRepository->hasVideo($command->userId, $video->getId())) {
            throw new InvalidArgumentException('You already have this video in your library.');
        }

        $this->userVideoRepository->add(
            $command->userId,
            $video->getId(),
            $command->statuses,
            $command->userRating?->toFloat(),
            $command->personalNotes
        );

        return $video;
    }

    protected function getLogContext(): string
    {
        return 'AddVideoUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Video added to library successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to add video to library';
    }
}
