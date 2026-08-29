<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club\Mappers;

use App\Domain\Model\ClubProposal;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

class ClubProposalDataMapper
{
    use HydrationHelpersTrait;

    public function toDomain(array $row): ClubProposal
    {
        return new ClubProposal(
            id:          $this->extractInt($row, 'id', null),
            roundId:     $this->extractRequiredInt($row, 'round_id'),
            userId:      $this->extractRequiredInt($row, 'user_id'),
            entityType:  $this->extractString($row, 'entity_type', ''),
            entityId:    $this->extractString($row, 'entity_id', ''),
            entityTitle: $this->extractString($row, 'entity_title', null),
            entityCover: $this->extractString($row, 'entity_cover', null),
            createdAt:   $this->extractString($row, 'created_at', null)
        );
    }

    /** @return ClubProposal[] */
    public function toDomainCollection(array $rows): array
    {
        return array_map(fn (array $row) => $this->toDomain($row), $rows);
    }

    public function toPersistence(ClubProposal $proposal): array
    {
        return [
            'round_id'     => $proposal->getRoundId(),
            'user_id'      => $proposal->getUserId(),
            'entity_type'  => $proposal->getEntityType(),
            'entity_id'    => $proposal->getEntityId(),
            'entity_title' => $proposal->getEntityTitle(),
            'entity_cover' => $proposal->getEntityCover(),
        ];
    }
}
