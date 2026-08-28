<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

use InvalidArgumentException;

final readonly class GetListQuery
{
    public function __construct(
        public int $userId,
        public int $listId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $listId = (int) ($data['listId'] ?? $data['list_id'] ?? 0);
        if ($listId <= 0) {
            throw new InvalidArgumentException('listId is required');
        }

        return new self(userId: $userId, listId: $listId);
    }
}
