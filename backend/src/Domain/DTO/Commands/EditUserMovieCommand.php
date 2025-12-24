<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for editing user's movie data
 */
final readonly class EditUserMovieCommand
{
    /**
     * @param MovieIdentifier $id
     * @param int $userId
     * @param Rating|null $userRating
     * @param array $statuses
     * @param array $tags Array of tag IDs
     */
    public function __construct(
        public MovieIdentifier $id,
        public int $userId,
        public ?Rating $userRating = null,
        public array $statuses = [],
        public array $tags = []
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            id: MovieIdentifier::fromString($data['id'] ?? $data['isbn'] ?? $data['imdbID']),
            userId: $userId,
            userRating: isset($data['user_rating']) && is_numeric($data['user_rating']) && (float)$data['user_rating'] > 0
                ? Rating::fromNullableFloat((float)$data['user_rating'])
                : null,
            statuses: $data['statuses'] ?? [],
            tags: $data['tags'] ?? []
        );
    }
}
