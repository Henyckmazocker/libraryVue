<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for updating an edition note
 * Encapsulates all data needed to update an existing note
 */
final readonly class UpdateEditionNoteCommand
{
    public function __construct(
        public int $noteId,
        public int $userId,
        public ?int $pageNumber = null,
        public ?string $noteText = null,
        public ?string $noteType = null,
        public ?bool $isPrivate = null
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
            noteId: (int) ($data['noteId'] ?? $data['note_id'] ?? $data['id'] ?? 0),
            userId: $userId,
            pageNumber: isset($data['pageNumber']) ? (int) $data['pageNumber'] : (isset($data['page_number']) ? (int) $data['page_number'] : null),
            noteText: $data['noteText'] ?? $data['note_text'] ?? null,
            noteType: $data['noteType'] ?? $data['note_type'] ?? null,
            isPrivate: isset($data['isPrivate']) ? (bool) $data['isPrivate'] : (isset($data['is_private']) ? (bool) $data['is_private'] : null)
        );
    }
}
