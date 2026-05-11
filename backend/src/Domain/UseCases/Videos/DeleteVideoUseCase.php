<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\DeleteVideoCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class DeleteVideoUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly UserVideoRepositoryInterface $userVideoRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof DeleteVideoCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteVideoCommand');
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

        return $this->userVideoRepository->remove($command->userId, $video->getId());
    }

    protected function getLogContext(): string
    {
        return 'DeleteVideoUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Video removed from library successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to remove video from library';
    }
}
