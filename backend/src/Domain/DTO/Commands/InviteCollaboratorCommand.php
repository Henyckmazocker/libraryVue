<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

final readonly class InviteCollaboratorCommand
{
    public function __construct(
        public int $userId,
        public int $listId,
        public int $inviteeId
    ) {
        if ($userId === $inviteeId) {
            throw new InvalidArgumentException('Cannot invite yourself to your own list');
        }
    }

    public static function fromArray(array $data, int $userId): self
    {
        $listId = (int) ($data['listId'] ?? $data['list_id'] ?? 0);
        if ($listId <= 0) {
            throw new InvalidArgumentException('listId is required');
        }

        $inviteeId = (int) ($data['userId'] ?? $data['user_id'] ?? 0);
        if ($inviteeId <= 0) {
            throw new InvalidArgumentException('userId is required');
        }

        return new self(userId: $userId, listId: $listId, inviteeId: $inviteeId);
    }
}
