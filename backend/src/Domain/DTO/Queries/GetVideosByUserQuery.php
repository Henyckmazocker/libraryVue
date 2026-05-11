<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query DTO for fetching a user's video library
 */
final readonly class GetVideosByUserQuery
{
    public function __construct(
        public int $userId,
        public ?string $status = null,
        public ?string $sortBy = null,
        public string $sortOrder = 'asc'
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId:    $userId,
            status:    $data['status'] ?? null,
            sortBy:    $data['sortBy'] ?? $data['sort_by'] ?? null,
            sortOrder: $data['sortOrder'] ?? $data['sort_order'] ?? 'asc'
        );
    }
}
