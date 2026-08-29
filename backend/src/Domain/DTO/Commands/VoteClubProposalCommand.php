<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

final readonly class VoteClubProposalCommand
{
    public function __construct(
        public int $userId,
        public int $clubId,
        public int $proposalId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $clubId = (int) ($data['clubId'] ?? $data['club_id'] ?? 0);
        if ($clubId <= 0) {
            throw new InvalidArgumentException('clubId is required');
        }

        // Por `clubId` **y** `proposalId`: el club es lo que da el permiso, y
        // la propuesta se comprueba contra la ronda de ESE club. Con solo el
        // id de la propuesta habría que deducir el club desde ella, y una
        // comprobación de pertenencia que se deduce del propio dato que se
        // quiere validar no comprueba nada.
        $proposalId = (int) ($data['proposalId'] ?? $data['proposal_id'] ?? 0);
        if ($proposalId <= 0) {
            throw new InvalidArgumentException('proposalId is required');
        }

        return new self(userId: $userId, clubId: $clubId, proposalId: $proposalId);
    }
}
