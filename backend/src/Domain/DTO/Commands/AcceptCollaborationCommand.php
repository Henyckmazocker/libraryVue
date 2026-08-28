<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

/**
 * La invitación se identifica por su fila del buzón, no por `listId`: es lo que
 * garantiza que solo se puede aceptar algo que de verdad te mandaron. Con
 * `listId` bastaría con adivinar un número para colarse en una lista ajena.
 */
final readonly class AcceptCollaborationCommand
{
    public function __construct(
        public int $userId,
        public int $recommendationId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $recommendationId = (int) ($data['recommendationId'] ?? $data['recommendation_id'] ?? 0);
        if ($recommendationId <= 0) {
            throw new InvalidArgumentException('recommendationId is required');
        }

        return new self(userId: $userId, recommendationId: $recommendationId);
    }
}
