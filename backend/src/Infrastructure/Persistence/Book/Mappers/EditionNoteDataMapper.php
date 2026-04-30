<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book\Mappers;

use App\Domain\Model\EditionNote;
use App\Domain\Model\ValueObjects\Timestamp;

/**
 * Maps EditionNote entity between Domain and Persistence layers
 */
final class EditionNoteDataMapper
{
    /**
     * Convert database row to EditionNote domain entity
     *
     * @param array $dbRow Database row
     * @return EditionNote
     */
    public function toDomain(array $dbRow): EditionNote
    {
        $note = new EditionNote(
            userId: (int) $dbRow['user_id'],
            userEditionId: (int) $dbRow['user_edition_id'],
            pageNumber: (int) $dbRow['page_number'],
            noteText: $dbRow['note_text'] ?? null,
            noteType: $dbRow['note_type'] ?? 'progress',
            isPrivate: (bool) ($dbRow['is_private'] ?? true),
            id: isset($dbRow['id']) ? (int) $dbRow['id'] : null
        );

        // Set timestamps from database
        if (isset($dbRow['created_at'])) {
            $note->setCreatedAt(Timestamp::fromString($dbRow['created_at']));
        }

        if (isset($dbRow['updated_at'])) {
            $note->setUpdatedAt(Timestamp::fromString($dbRow['updated_at']));
        }

        return $note;
    }

    /**
     * Convert array of database rows to array of EditionNote domain entities
     *
     * @param array $dbRows Array of database rows
     * @return EditionNote[]
     */
    public function toDomainCollection(array $dbRows): array
    {
        return array_map(
            fn(array $row) => $this->toDomain($row),
            $dbRows
        );
    }

    /**
     * Convert EditionNote domain entity to database row
     *
     * @param EditionNote $note Edition note entity
     * @return array
     */
    public function toDatabase(EditionNote $note): array
    {
        $data = [
            'user_id' => $note->getUserId(),
            'user_edition_id' => $note->getUserEditionId(),
            'page_number' => $note->getPageNumber(),
            'note_text' => $note->getNoteText(),
            'note_type' => $note->getNoteType(),
            'is_private' => $note->isPrivate() ? 1 : 0,
        ];

        // Include ID if it exists (for updates)
        if ($note->getId() !== null) {
            $data['id'] = $note->getId();
        }

        return $data;
    }
}
