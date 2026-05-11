<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\VideoNoteRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateVideoNoteCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateVideoNoteUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly VideoNoteRepositoryInterface $videoNoteRepository,
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof UpdateVideoNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateVideoNoteCommand');
        }

        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        return $this->videoNoteRepository->update(
            $command->noteId,
            $command->userId,
            $command->noteText,
            $command->noteType,
            $command->isPrivate
        );
    }

    protected function getLogContext(): string
    {
        return 'UpdateVideoNoteUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Video note updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update video note';
    }
}
