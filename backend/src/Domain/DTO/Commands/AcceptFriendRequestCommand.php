<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

final readonly class AcceptFriendRequestCommand
{
    public function __construct(
        public int $friendshipId,
        public int $userId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $friendshipId = (int) ($data['friendshipId'] ?? $data['friendship_id'] ?? 0);
        if ($friendshipId <= 0) {
            throw new InvalidArgumentException('friendshipId is required');
        }
        return new self($friendshipId, $userId);
    }
}
