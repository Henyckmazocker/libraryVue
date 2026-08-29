<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club\Mappers;

use App\Domain\Model\Club;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

class ClubDataMapper
{
    use HydrationHelpersTrait;

    public function toDomain(array $row): Club
    {
        return new Club(
            id:          $this->extractInt($row, 'id', null),
            ownerId:     $this->extractRequiredInt($row, 'owner_id'),
            name:        $this->extractString($row, 'name', ''),
            description: $this->extractString($row, 'description', null),
            createdAt:   $this->extractString($row, 'created_at', null)
        );
    }

    /** @return Club[] */
    public function toDomainCollection(array $rows): array
    {
        return array_map(fn (array $row) => $this->toDomain($row), $rows);
    }

    public function toPersistence(Club $club): array
    {
        return [
            'owner_id'    => $club->getOwnerId(),
            'name'        => $club->getName(),
            'description' => $club->getDescription(),
        ];
    }
}
