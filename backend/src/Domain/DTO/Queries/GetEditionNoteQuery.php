<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query DTO for retrieving a single edition note by ID
 */
final readonly class GetEditionNoteQuery
{
    public function __construct(
        public int $noteId,
        public int $userId
    ) {}

    /**
     * Create query from array data
     *
     * @param array $data Input data
     * @param int $userId Current user ID
     * @return self
     */
    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            noteId: (int) ($data['noteId'] ?? $data['note_id'] ?? $data['id'] ?? 0),
            userId: $userId
        );
    }
}
