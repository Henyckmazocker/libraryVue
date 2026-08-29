<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

final readonly class FinishClubPickCommand
{
    public function __construct(
        public int $userId,
        public int $clubId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        // Por `clubId` y no por `pickId`: el activo es uno solo, y pedirlo por
        // su id dejaría cerrar uno del historial por error.
        $clubId = (int) ($data['clubId'] ?? $data['club_id'] ?? 0);
        if ($clubId <= 0) {
            throw new InvalidArgumentException('clubId is required');
        }

        return new self(userId: $userId, clubId: $clubId);
    }
}
