<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\DTO\Queries\GetEditionNoteQuery;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Use case for retrieving a single edition note by ID
 */
class GetEditionNoteUseCase extends AbstractUseCase
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
     * @param GetEditionNoteQuery $query
     * @return array Note data
     */
    protected function doExecute($query): array
    {
        if (!$query instanceof GetEditionNoteQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetEditionNoteQuery');
        }

        // Find note
        $note = $this->editionNoteRepository->findById($query->noteId, $query->userId);
        if (!$note) {
            throw new InvalidArgumentException('Note not found or you do not have permission to view it');
        }

        $this->logger->debug('Retrieved edition note', [
            'note_id' => $query->noteId
        ]);

        return $note->toArray();
    }

    protected function getLogContext(): string
    {
        return 'GetEditionNoteUseCase';
    }
}
