<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query DTO for retrieving all notes for a specific user edition
 */
final readonly class GetEditionNotesQuery
{
    public function __construct(
        public int $userId,
        public int $userEditionId,
        public ?string $noteType = null,
        public ?int $pageNumber = null
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
            userId: $userId,
            userEditionId: (int) ($data['userEditionId'] ?? $data['user_edition_id'] ?? 0),
            noteType: $data['noteType'] ?? $data['note_type'] ?? null,
            pageNumber: isset($data['pageNumber']) ? (int) $data['pageNumber'] : (isset($data['page_number']) ? (int) $data['page_number'] : null)
        );
    }
}
