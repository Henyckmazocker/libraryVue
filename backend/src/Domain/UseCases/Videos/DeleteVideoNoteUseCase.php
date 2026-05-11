<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\VideoNoteRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\DeleteVideoNoteCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class DeleteVideoNoteUseCase extends AbstractUseCase
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
        if (!$command instanceof DeleteVideoNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteVideoNoteCommand');
        }

        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        return $this->videoNoteRepository->delete($command->noteId, $command->userId);
    }

    protected function getLogContext(): string
    {
        return 'DeleteVideoNoteUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Video note deleted successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to delete video note';
    }
}
