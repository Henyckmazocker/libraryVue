<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for deleting a video note
 */
final readonly class DeleteVideoNoteCommand
{
    public function __construct(
        public int $noteId,
        public int $userId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            noteId: (int)($data['noteId'] ?? $data['note_id'] ?? $data['id'] ?? 0),
            userId: $userId
        );
    }
}
