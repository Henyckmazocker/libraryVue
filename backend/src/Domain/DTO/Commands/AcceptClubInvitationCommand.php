<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

final readonly class AcceptClubInvitationCommand
{
    public function __construct(
        public int $userId,
        public int $recommendationId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        // Por `recommendationId` y no por `clubId`: es lo que garantiza que
        // solo se entra en un club al que de verdad te invitaron.
        $recommendationId = (int) ($data['recommendationId'] ?? $data['recommendation_id'] ?? 0);
        if ($recommendationId <= 0) {
            throw new InvalidArgumentException('recommendationId is required');
        }

        return new self(userId: $userId, recommendationId: $recommendationId);
    }
}
