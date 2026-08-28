<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MediaList\Mappers;

use App\Domain\Model\MediaListItem;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

class MediaListItemDataMapper
{
    use HydrationHelpersTrait;

    public function toDomain(array $row): MediaListItem
    {
        return new MediaListItem(
            id:          $this->extractInt($row, 'id', null),
            listId:      $this->extractRequiredInt($row, 'list_id'),
            entityType:  $this->extractString($row, 'entity_type', 'book'),
            entityId:    $this->extractString($row, 'entity_id', ''),
            addedBy:     $this->extractRequiredInt($row, 'added_by'),
            entityTitle: $this->extractString($row, 'entity_title', null),
            entityCover: $this->extractString($row, 'entity_cover', null),
            position:    $this->extractInt($row, 'position', 0) ?? 0,
            addedAt:     $this->extractString($row, 'added_at', null)
        );
    }

    /** @return MediaListItem[] */
    public function toDomainCollection(array $rows): array
    {
        return array_map(fn (array $row) => $this->toDomain($row), $rows);
    }

    public function toPersistence(MediaListItem $item): array
    {
        return [
            'list_id'      => $item->getListId(),
            'entity_type'  => $item->getEntityType(),
            'entity_id'    => $item->getEntityId(),
            'entity_title' => $item->getEntityTitle(),
            'entity_cover' => $item->getEntityCover(),
            'added_by'     => $item->getAddedBy(),
            'position'     => $item->getPosition(),
        ];
    }
}
