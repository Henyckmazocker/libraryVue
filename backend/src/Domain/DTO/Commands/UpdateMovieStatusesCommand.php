<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\MovieIdentifier;

/**
 * Command DTO for updating movie user statuses
 */
final readonly class UpdateMovieStatusesCommand
{
    public function __construct(
        public MovieIdentifier $id,
        public int $userId,
        public array $statuses
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            id: MovieIdentifier::fromString($data['id'] ?? $data['isbn'] ?? $data['imdbID']),
            userId: $userId,
            statuses: $data['statuses'] ?? []
        );
    }
}
