<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\MovieIdentifier;

/**
 * Command DTO for deleting a movie from user's library
 */
final readonly class DeleteMovieCommand
{
    public function __construct(
        public MovieIdentifier $id,
        public int $userId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            id: MovieIdentifier::fromString($data['id'] ?? $data['isbn'] ?? $data['imdbID']),
            userId: $userId
        );
    }
}
