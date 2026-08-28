<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

final readonly class RemoveListItemCommand
{
    public function __construct(
        public int $userId,
        public int $listId,
        public int $itemId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $listId = (int) ($data['listId'] ?? $data['list_id'] ?? 0);
        if ($listId <= 0) {
            throw new InvalidArgumentException('listId is required');
        }

        $itemId = (int) ($data['itemId'] ?? $data['item_id'] ?? 0);
        if ($itemId <= 0) {
            throw new InvalidArgumentException('itemId is required');
        }

        return new self(userId: $userId, listId: $listId, itemId: $itemId);
    }
}
