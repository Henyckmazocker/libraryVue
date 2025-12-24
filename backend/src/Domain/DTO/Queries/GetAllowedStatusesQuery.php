<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query DTO for getting allowed statuses
 * Can be used for both books and movies
 */
final readonly class GetAllowedStatusesQuery
{
    public function __construct(
        public string $entityType // 'book' or 'movie'
    ) {
        if (!in_array($entityType, ['book', 'movie'])) {
            throw new \InvalidArgumentException("Entity type must be 'book' or 'movie'");
        }
    }

    public static function forBooks(): self
    {
        return new self('book');
    }

    public static function forMovies(): self
    {
        return new self('movie');
    }
}
