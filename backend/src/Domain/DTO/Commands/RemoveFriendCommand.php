<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

final readonly class RemoveFriendCommand
{
    public function __construct(
        public int $userId,
        public int $friendId
    ) {
        if ($userId === $friendId) {
            throw new InvalidArgumentException('Cannot remove yourself as a friend');
        }
    }

    public static function fromArray(array $data, int $userId): self
    {
        $friendId = (int) ($data['friendId'] ?? $data['friend_id'] ?? 0);
        if ($friendId <= 0) {
            throw new InvalidArgumentException('friendId is required');
        }
        return new self($userId, $friendId);
    }
}
