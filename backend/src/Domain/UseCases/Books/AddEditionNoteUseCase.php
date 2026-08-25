<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\DTO\Commands\AddEditionNoteCommand;
use App\Domain\Model\EditionNote;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Services\FeedEventService;
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
        // `UserBookEdition` solo guarda el id de la edición: ni título ni
        // portada. El evento del feed los necesita.
        private readonly EditionRepositoryInterface $editionRepository,
        private readonly FeedEventService $feedEvents,
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

        $this->publishToFeed($command, $userEdition->getEditionId());

        return $savedNote->toArray();
    }

    /**
     * Publica la nota en el feed, **solo si es pública**.
     *
     * La guarda vive aquí y no en `FeedEventService`: ese servicio se traga sus
     * propios errores por diseño, así que esconder ahí una regla de privacidad
     * convertiría un fallo silencioso en un escape de privacidad silencioso.
     *
     * El `entity_id` es el **ISBN**, no el id interno de la edición, para que
     * case con el que usa `recordItemAdded` en `AddBookUseCase:195`.
     */
    private function publishToFeed(AddEditionNoteCommand $command, int $editionId): void
    {
        if ($command->isPrivate) {
            return;
        }

        $edition = $this->editionRepository->findById($editionId);
        if ($edition === null) {
            return;
        }

        $this->feedEvents->recordNotesUpdated(
            userId: $command->userId,
            entityType: 'book',
            // ISBN-13 primero y ISBN-10 de respaldo: es lo que hay.
            entityId: (string) ($edition->getIsbn13() ?? $edition->getIsbn10()),
            title: $edition->getTitle(),
            cover: $edition->getCoverUrlMedium() ?? $edition->getCoverUrlSmall(),
            noteText: $command->noteText,
            noteType: $command->noteType
        );
    }

    protected function getLogContext(): string
    {
        return 'AddEditionNoteUseCase';
    }
}
