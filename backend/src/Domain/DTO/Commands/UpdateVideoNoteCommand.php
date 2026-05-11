<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for updating an existing video note
 */
final readonly class UpdateVideoNoteCommand
{
    public function __construct(
        public int $noteId,
        public int $userId,
        public string $noteText,
        public string $noteType = 'note',
        public bool $isPrivate = true
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            noteId:    (int)($data['noteId'] ?? $data['note_id'] ?? $data['id'] ?? 0),
            userId:    $userId,
            noteText:  (string)($data['noteText'] ?? $data['note_text'] ?? ''),
            noteType:  (string)($data['noteType'] ?? $data['note_type'] ?? 'note'),
            isPrivate: (bool)($data['isPrivate'] ?? $data['is_private'] ?? true)
        );
    }
}
