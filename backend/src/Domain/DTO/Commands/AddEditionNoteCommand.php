<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for adding a note to a book edition
 * Encapsulates all data needed to create a new edition note
 */
final readonly class AddEditionNoteCommand
{
    public function __construct(
        public int $userId,
        public int $userEditionId,
        public int $pageNumber,
        public ?string $noteText = null,
        public string $noteType = 'progress',
        public bool $isPrivate = true
    ) {}

    /**
     * Create command from array data
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
            pageNumber: (int) ($data['pageNumber'] ?? $data['page_number'] ?? 0),
            noteText: $data['noteText'] ?? $data['note_text'] ?? null,
            noteType: $data['noteType'] ?? $data['note_type'] ?? 'progress',
            isPrivate: (bool) ($data['isPrivate'] ?? $data['is_private'] ?? true)
        );
    }
}
