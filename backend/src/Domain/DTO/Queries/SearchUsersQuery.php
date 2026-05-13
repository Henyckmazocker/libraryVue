<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

use InvalidArgumentException;

final readonly class SearchUsersQuery
{
    public function __construct(
        public string $searchTerm,
        public int    $currentUserId,
        public int    $limit = 10
    ) {
        if (strlen(trim($searchTerm)) < 2) {
            throw new InvalidArgumentException('Search term must be at least 2 characters');
        }
    }

    public static function fromArray(array $data, int $userId): self
    {
        $term = trim((string) ($data['term'] ?? $data['searchTerm'] ?? $data['search_term'] ?? ''));
        return new self(
            searchTerm:    $term,
            currentUserId: $userId,
            limit:         min(20, max(1, (int) ($data['limit'] ?? 10)))
        );
    }
}
