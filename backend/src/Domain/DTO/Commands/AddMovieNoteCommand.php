<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for adding a note to a movie
 * Encapsulates all data needed to create a new movie note
 */
final readonly class AddMovieNoteCommand
{
    public function __construct(
        public int $userId,
        public string $movieIsbn,
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
            userId: $userId,
            movieIsbn: (string) ($data['movieIsbn'] ?? $data['movie_isbn'] ?? $data['isbn'] ?? ''),
            noteText: (string) ($data['noteText'] ?? $data['note_text'] ?? ''),
            noteType: (string) ($data['noteType'] ?? $data['note_type'] ?? 'note'),
            isPrivate: (bool) ($data['isPrivate'] ?? $data['is_private'] ?? true)
        );
    }
}
