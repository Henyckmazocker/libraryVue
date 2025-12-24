<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

readonly class GetUserReadingStatsQuery
{
    public function __construct(
        public int $userId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(userId: $userId);
    }
}
