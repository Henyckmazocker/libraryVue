<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\DTO\Commands\AddEditionNoteCommand;
use App\Domain\Model\EditionNote;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Use case for adding a note to a book edition
 */
class AddEditionNoteUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly EditionNoteRepositoryInterface $editionNoteRepository,
        private readonly UserBookEditionRepositoryInterface $userBookEditionRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute the use case
     *
     * @param AddEditionNoteCommand $command
     * @return array Note data
     */
    protected function doExecute($command): array
    {
        if (!$command instanceof AddEditionNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddEditionNoteCommand');
        }

        // Verify user owns the edition
        $userEdition = $this->userBookEditionRepository->findById($command->userEditionId);
        if (!$userEdition) {
            throw new InvalidArgumentException('Edition not found');
        }

        if ($userEdition->getUserId() !== $command->userId) {
            throw new InvalidArgumentException('You do not have permission to add notes to this edition');
        }

        // Create note entity
        $note = new EditionNote(
            userId: $command->userId,
            userEditionId: $command->userEditionId,
            pageNumber: $command->pageNumber,
            noteText: $command->noteText,
            noteType: $command->noteType,
            isPrivate: $command->isPrivate
        );

        // Save note
        $savedNote = $this->editionNoteRepository->add($note);

        $this->logger->info('Edition note added', [
            'note_id' => $savedNote->getId(),
            'user_edition_id' => $command->userEditionId,
            'page_number' => $command->pageNumber
        ]);

        return $savedNote->toArray();
    }

    protected function getLogContext(): string
    {
        return 'AddEditionNoteUseCase';
    }
}
