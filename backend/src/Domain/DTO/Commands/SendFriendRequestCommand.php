<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

final readonly class SendFriendRequestCommand
{
    public function __construct(
        public int $requesterId,
        public int $addresseeId
    ) {
        if ($requesterId === $addresseeId) {
            throw new InvalidArgumentException('Cannot send friend request to yourself');
        }
    }

    public static function fromArray(array $data, int $userId): self
    {
        $addresseeId = (int) ($data['addresseeId'] ?? $data['addressee_id'] ?? 0);
        if ($addresseeId <= 0) {
            throw new InvalidArgumentException('addresseeId is required');
        }
        return new self($userId, $addresseeId);
    }
}
