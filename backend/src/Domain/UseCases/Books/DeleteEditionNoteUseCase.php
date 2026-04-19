<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\DTO\Commands\DeleteEditionNoteCommand;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Use case for deleting an edition note
 */
class DeleteEditionNoteUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly EditionNoteRepositoryInterface $editionNoteRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute the use case
     *
     * @param DeleteEditionNoteCommand $command
     * @return array Success response
     */
    protected function doExecute($command): array
    {
        if (!$command instanceof DeleteEditionNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteEditionNoteCommand');
        }

        // Verify note exists and belongs to user
        $note = $this->editionNoteRepository->findById($command->noteId, $command->userId);
        if (!$note) {
            throw new InvalidArgumentException('Note not found or you do not have permission to delete it');
        }

        // Delete note
        $success = $this->editionNoteRepository->delete($command->noteId, $command->userId);

        if (!$success) {
            throw new InvalidArgumentException('Failed to delete note');
        }

        $this->logger->info('Edition note deleted', [
            'note_id' => $command->noteId,
            'user_id' => $command->userId
        ]);

        return [
            'success' => true,
            'message' => 'Note deleted successfully'
        ];
    }

    protected function getLogContext(): string
    {
        return 'DeleteEditionNoteUseCase';
    }
}
