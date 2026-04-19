<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\DTO\Commands\DeleteMovieNoteCommand;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Use case for deleting a movie note
 */
class DeleteMovieNoteUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MovieNoteRepositoryInterface $movieNoteRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute the use case
     *
     * @param DeleteMovieNoteCommand $command
     * @return bool Success status
     */
    protected function doExecute($command): bool
    {
        if (!$command instanceof DeleteMovieNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteMovieNoteCommand');
        }

        // Delete note
        $deleted = $this->movieNoteRepository->delete($command->noteId, $command->userId);

        if (!$deleted) {
            throw new InvalidArgumentException('Note not found or you do not have permission to delete it');
        }

        $this->logger->info('Movie note deleted', [
            'note_id' => $command->noteId
        ]);

        return true;
    }

    protected function getLogContext(): string
    {
        return 'DeleteMovieNoteUseCase';
    }
}
