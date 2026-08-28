<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MediaList\Mappers;

use App\Domain\Model\MediaList;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

class MediaListDataMapper
{
    use HydrationHelpersTrait;

    public function toDomain(array $row): MediaList
    {
        return new MediaList(
            id:          $this->extractInt($row, 'id', null),
            ownerId:     $this->extractRequiredInt($row, 'owner_id'),
            name:        $this->extractString($row, 'name', ''),
            description: $this->extractString($row, 'description', null),
            visibility:  $this->extractString($row, 'visibility', MediaList::VISIBILITY_PRIVATE),
            createdAt:   $this->extractString($row, 'created_at', null),
            updatedAt:   $this->extractString($row, 'updated_at', null)
        );
    }

    /** @return MediaList[] */
    public function toDomainCollection(array $rows): array
    {
        return array_map(fn (array $row) => $this->toDomain($row), $rows);
    }

    public function toPersistence(MediaList $list): array
    {
        return [
            'owner_id'    => $list->getOwnerId(),
            'name'        => $list->getName(),
            'description' => $list->getDescription(),
            'visibility'  => $list->getVisibility(),
        ];
    }
}
