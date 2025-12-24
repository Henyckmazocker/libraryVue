<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for updating movie rating
 */
final readonly class UpdateMovieRatingCommand
{
    public function __construct(
        public MovieIdentifier $id,
        public int $userId,
        public Rating $rating
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            id: MovieIdentifier::fromString($data['id'] ?? $data['imdbID']),
            userId: $userId,
            rating: isset($data['rating']) && (float)$data['rating'] > 0
                ? Rating::fromNullableFloat((float)$data['rating'])
                : null
        );
    }
}
