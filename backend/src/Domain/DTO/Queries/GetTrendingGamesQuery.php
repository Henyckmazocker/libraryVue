<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query DTO for getting trending games
 */
final readonly class GetTrendingGamesQuery
{
    public function __construct(
        public int $limit = 20,
        public int $daysWindow = 90,  // Ventana de tiempo para considerar trending (días)
        public ?int $userId = null  // User ID to check ownership
    ) {}

    public static function create(int $limit = 20, int $daysWindow = 90, ?int $userId = null): self
    {
        return new self($limit, $daysWindow, $userId);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            limit: $data['limit'] ?? 20,
            daysWindow: $data['daysWindow'] ?? 90,
            userId: $data['userId'] ?? null
        );
    }
}
