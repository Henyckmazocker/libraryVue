<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\DTO\Queries\GetEditionNotesQuery;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Use case for retrieving all notes for a user edition
 */
class GetEditionNotesUseCase extends AbstractUseCase
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
     * @param GetEditionNotesQuery $query
     * @return array Array of notes
     */
    protected function doExecute($query): array
    {
        if (!$query instanceof GetEditionNotesQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetEditionNotesQuery');
        }

        // Verify user owns the edition
        $userEdition = $this->userBookEditionRepository->findById($query->userEditionId);
        if (!$userEdition) {
            throw new InvalidArgumentException('Edition not found');
        }

        if ($userEdition->getUserId() !== $query->userId) {
            throw new InvalidArgumentException('You do not have permission to view notes for this edition');
        }

        // Get notes
        $notes = $this->editionNoteRepository->findByUserEdition(
            userId: $query->userId,
            userEditionId: $query->userEditionId,
            noteType: $query->noteType,
            pageNumber: $query->pageNumber
        );

        $this->logger->debug('Retrieved edition notes', [
            'user_edition_id' => $query->userEditionId,
            'count' => count($notes)
        ]);

        // Convert notes to arrays
        return array_map(fn($note) => $note->toArray(), $notes);
    }

    protected function getLogContext(): string
    {
        return 'GetEditionNotesUseCase';
    }
}
