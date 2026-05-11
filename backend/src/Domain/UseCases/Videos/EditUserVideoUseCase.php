<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\Video\VideoTagRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\EditUserVideoCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class EditUserVideoUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly UserVideoRepositoryInterface $userVideoRepository,
        private readonly VideoTagRepositoryInterface $videoTagRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof EditUserVideoCommand) {
            throw new InvalidArgumentException('Command must be an instance of EditUserVideoCommand');
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

        $data = [];
        if ($command->userRating !== null) {
            $data['personal_rating'] = $command->userRating->toFloat();
        }
        if ($command->personalNotes !== null) {
            $data['personal_notes'] = $command->personalNotes;
        }
        if ($command->watchCount !== null) {
            $data['watch_count'] = $command->watchCount;
        }
        if ($command->watchedAt !== null) {
            $data['watched_at'] = $command->watchedAt;
        }

        if (!empty($data)) {
            $this->userVideoRepository->update($command->userId, $video->getId(), $data);
        }

        // Update statuses only if explicitly provided (null = do not touch)
        if ($command->statuses !== null) {
            $this->userVideoRepository->updateStatuses(
                $command->userId,
                $video->getId(),
                $command->statuses
            );
        }

        // Sync tags — always replace all existing assignments
        $this->videoTagRepository->removeAllFromVideo($command->userId, $video->getId());
        foreach ($command->tags as $tagId) {
            $this->videoTagRepository->assignToVideo($command->userId, $video->getId(), (int)$tagId);
        }

        return true;
    }

    protected function getLogContext(): string
    {
        return 'EditUserVideoUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Video updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update video';
    }
}
