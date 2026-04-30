<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\DTO\Commands\UpdateMovieNoteCommand;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Use case for updating a movie note
 */
class UpdateMovieNoteUseCase extends AbstractUseCase
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
     * @param UpdateMovieNoteCommand $command
     * @return bool Success status
     */
    protected function doExecute($command): bool
    {
        if (!$command instanceof UpdateMovieNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateMovieNoteCommand');
        }

        // Update note
        $updated = $this->movieNoteRepository->update(
            noteId: $command->noteId,
            userId: $command->userId,
            noteText: $command->noteText,
            noteType: $command->noteType,
            isPrivate: $command->isPrivate
        );

        if (!$updated) {
            throw new InvalidArgumentException('Note not found or you do not have permission to update it');
        }

        $this->logger->info('Movie note updated', [
            'note_id' => $command->noteId
        ]);

        return true;
    }

    protected function getLogContext(): string
    {
        return 'UpdateMovieNoteUseCase';
    }
}
