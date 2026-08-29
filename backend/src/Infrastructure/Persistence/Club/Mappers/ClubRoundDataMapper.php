<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club\Mappers;

use App\Domain\Model\ClubRound;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

class ClubRoundDataMapper
{
    use HydrationHelpersTrait;

    public function toDomain(array $row): ClubRound
    {
        return new ClubRound(
            id:                $this->extractInt($row, 'id', null),
            clubId:            $this->extractRequiredInt($row, 'club_id'),
            phase:             $this->extractString($row, 'phase', ClubRound::PHASE_PROPOSING),
            ballot:            $this->extractInt($row, 'ballot', 1),
            winningProposalId: $this->extractInt($row, 'winning_proposal_id', null),
            createdAt:         $this->extractString($row, 'created_at', null),
            closedAt:          $this->extractString($row, 'closed_at', null)
        );
    }

    /** @return ClubRound[] */
    public function toDomainCollection(array $rows): array
    {
        return array_map(fn (array $row) => $this->toDomain($row), $rows);
    }
}
