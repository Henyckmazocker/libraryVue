<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club\Mappers;

use App\Domain\Model\ClubPick;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

class ClubPickDataMapper
{
    use HydrationHelpersTrait;

    public function toDomain(array $row): ClubPick
    {
        return new ClubPick(
            id:          $this->extractInt($row, 'id', null),
            clubId:      $this->extractRequiredInt($row, 'club_id'),
            entityType:  $this->extractString($row, 'entity_type', ''),
            entityId:    $this->extractString($row, 'entity_id', ''),
            entityTitle: $this->extractString($row, 'entity_title', null),
            entityCover: $this->extractString($row, 'entity_cover', null),
            startedAt:   $this->extractString($row, 'started_at', null),
            finishedAt:  $this->extractString($row, 'finished_at', null)
        );
    }

    /** @return ClubPick[] */
    public function toDomainCollection(array $rows): array
    {
        return array_map(fn (array $row) => $this->toDomain($row), $rows);
    }

    public function toPersistence(ClubPick $pick): array
    {
        return [
            'club_id'      => $pick->getClubId(),
            'entity_type'  => $pick->getEntityType(),
            'entity_id'    => $pick->getEntityId(),
            'entity_title' => $pick->getEntityTitle(),
            'entity_cover' => $pick->getEntityCover(),
        ];
    }
}
