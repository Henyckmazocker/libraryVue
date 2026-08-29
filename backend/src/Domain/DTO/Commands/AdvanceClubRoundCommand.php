<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

/**
 * Las dos válvulas del dueño —`open_club_vote` y `close_club_vote`— comparten
 * command porque comparten payload: el club y nada más. Cuál de las dos es lo
 * dice la acción, y cada use case comprueba la fase que espera.
 */
final readonly class AdvanceClubRoundCommand
{
    public function __construct(
        public int $userId,
        public int $clubId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $clubId = (int) ($data['clubId'] ?? $data['club_id'] ?? 0);
        if ($clubId <= 0) {
            throw new InvalidArgumentException('clubId is required');
        }

        return new self(userId: $userId, clubId: $clubId);
    }
}
