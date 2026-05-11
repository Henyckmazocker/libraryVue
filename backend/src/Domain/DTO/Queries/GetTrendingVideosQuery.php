<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query DTO for fetching trending videos for a user
 */
final readonly class GetTrendingVideosQuery
{
    public function __construct(
        public int $userId,
        public int $limit = 10
    ) {}
}
