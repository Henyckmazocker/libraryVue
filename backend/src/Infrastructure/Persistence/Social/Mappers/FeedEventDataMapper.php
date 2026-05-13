<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Social\Mappers;

use App\Domain\Model\FeedEvent;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

class FeedEventDataMapper
{
    use HydrationHelpersTrait;

    public function toDomain(array $row): FeedEvent
    {
        return new FeedEvent(
            id:          $this->extractInt($row, 'id', null),
            userId:      $this->extractRequiredInt($row, 'user_id'),
            eventType:   $this->extractString($row, 'event_type'),
            entityType:  $this->extractString($row, 'entity_type', null),
            entityId:    $this->extractString($row, 'entity_id', null),
            entityTitle: $this->extractString($row, 'entity_title', null),
            entityCover: $this->extractString($row, 'entity_cover', null),
            metadata:    $this->extractJson($row, 'metadata', []),
            createdAt:   $this->extractString($row, 'created_at', null)
        );
    }

    public function toEnrichedArray(array $row): array
    {
        $event = $this->toDomain($row);
        $arr   = $event->toArray();

        // Enrich with user data joined from SELECT
        $arr['user'] = [
            'id'       => $this->extractInt($row, 'user_id', null),
            'username' => $this->extractString($row, 'username', null),
            'name'     => $this->extractString($row, 'user_name', null),
            'picture'  => $this->extractString($row, 'user_picture', null),
        ];

        return $arr;
    }

    public function toPersistence(FeedEvent $event): array
    {
        return [
            'user_id'      => $event->getUserId(),
            'event_type'   => $event->getEventType(),
            'entity_type'  => $event->getEntityType(),
            'entity_id'    => $event->getEntityId(),
            'entity_title' => $event->getEntityTitle(),
            'entity_cover' => $event->getEntityCover(),
            'metadata'     => $event->getMetadata() !== null
                ? json_encode($event->getMetadata(), JSON_UNESCAPED_UNICODE)
                : null,
        ];
    }
}
