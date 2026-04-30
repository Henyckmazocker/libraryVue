<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Repository\Movie\MovieTagRepositoryInterface;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\EditUserMovieCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class EditUserMovieUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserMovieRepositoryInterface $userMovieRepository,
        private readonly MovieTagRepositoryInterface $movieTagRepository,
        private readonly MovieNoteRepositoryInterface $movieNoteRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): void
    {
        if (!$command instanceof EditUserMovieCommand) {
            throw new InvalidArgumentException('Command must be an instance of EditUserMovieCommand');
        }

        $movieId = $command->id->toString();
        $userId = $command->userId;

        // Update rating if provided
        if ($command->userRating !== null) {
            $updateData = ['personal_rating' => $command->userRating->toFloat()];
            $this->userMovieRepository->edit($userId, $movieId, $updateData);
        }

        // Update ownership format if provided
        if ($command->ownershipFormatId !== null) {
            $this->userMovieRepository->edit($userId, $movieId, ['ownership_format_id' => $command->ownershipFormatId]);
        }

        // Update statuses (allow clearing all statuses with empty array)
        if ($command->statuses !== null) {
            $this->userMovieRepository->updateStatuses($userId, $movieId, $command->statuses);
        }

        // Remove all existing tags
        $this->movieTagRepository->removeAll($userId, $movieId);

        // Add new tags
        foreach ($command->tags as $tag) {
            if (is_numeric($tag)) {
                $this->movieTagRepository->assign($userId, $movieId, (int)$tag);
            }
        }
    }

    protected function getLogContext(): string
    {
        return 'EditUserMovieUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'User movie edited successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to edit user movie';
    }
}
