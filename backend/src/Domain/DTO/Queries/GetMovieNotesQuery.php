<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query DTO for retrieving all notes for a specific movie
 */
final readonly class GetMovieNotesQuery
{
    public function __construct(
        public int $userId,
        public string $movieIsbn,
        public ?string $noteType = null
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
            movieIsbn: (string) ($data['movieIsbn'] ?? $data['movie_isbn'] ?? $data['isbn'] ?? ''),
            noteType: $data['noteType'] ?? $data['note_type'] ?? null
        );
    }
}
