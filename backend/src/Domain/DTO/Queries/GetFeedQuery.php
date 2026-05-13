<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

final readonly class GetFeedQuery
{
    public function __construct(
        public int $userId,
        public int $limit  = 20,
        public int $offset = 0
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            limit:  min(50, max(1, (int) ($data['limit'] ?? 20))),
            offset: max(0, (int) ($data['offset'] ?? 0))
        );
    }
}
