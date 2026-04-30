<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for updating a movie note
 * Encapsulates all data needed to update an existing note
 */
final readonly class UpdateMovieNoteCommand
{
    public function __construct(
        public int $noteId,
        public int $userId,
        public string $noteText,
        public string $noteType = 'note',
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
            noteId: (int) ($data['noteId'] ?? $data['note_id'] ?? $data['id'] ?? 0),
            userId: $userId,
            noteText: (string) ($data['noteText'] ?? $data['note_text'] ?? ''),
            noteType: (string) ($data['noteType'] ?? $data['note_type'] ?? 'note'),
            isPrivate: (bool) ($data['isPrivate'] ?? $data['is_private'] ?? true)
        );
    }
}
