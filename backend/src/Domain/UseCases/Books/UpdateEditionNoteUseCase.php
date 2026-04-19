<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\DTO\Commands\UpdateEditionNoteCommand;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Use case for updating an edition note
 */
class UpdateEditionNoteUseCase extends AbstractUseCase
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
     * @param UpdateEditionNoteCommand $command
     * @return array Updated note data
     */
    protected function doExecute($command): array
    {
        if (!$command instanceof UpdateEditionNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateEditionNoteCommand');
        }

        // Find existing note and verify ownership
        $note = $this->editionNoteRepository->findById($command->noteId, $command->userId);
        if (!$note) {
            throw new InvalidArgumentException('Note not found or you do not have permission to edit it');
        }

        // Update fields if provided
        if ($command->pageNumber !== null) {
            $note->setPageNumber($command->pageNumber);
        }

        if ($command->noteText !== null) {
            $note->setNoteText($command->noteText);
        }

        if ($command->noteType !== null) {
            $note->setNoteType($command->noteType);
        }

        if ($command->isPrivate !== null) {
            $note->setIsPrivate($command->isPrivate);
        }

        // Save updated note
        $updatedNote = $this->editionNoteRepository->update($note);

        $this->logger->info('Edition note updated', [
            'note_id' => $updatedNote->getId(),
            'user_edition_id' => $updatedNote->getUserEditionId()
        ]);

        return $updatedNote->toArray();
    }

    protected function getLogContext(): string
    {
        return 'UpdateEditionNoteUseCase';
    }
}
