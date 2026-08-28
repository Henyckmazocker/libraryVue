<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Social\Mappers;

use App\Domain\Model\Recommendation;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

class RecommendationDataMapper
{
    use HydrationHelpersTrait;

    public function toDomain(array $row): Recommendation
    {
        return new Recommendation(
            id:          $this->extractInt($row, 'id', null),
            senderId:    $this->extractRequiredInt($row, 'sender_id'),
            recipientId: $this->extractRequiredInt($row, 'recipient_id'),
            entityType:  $this->extractString($row, 'entity_type', 'book'),
            entityId:    $this->extractString($row, 'entity_id', ''),
            entityTitle: $this->extractString($row, 'entity_title', null),
            entityCover: $this->extractString($row, 'entity_cover', null),
            comment:     $this->extractString($row, 'comment', null),
            status:      $this->extractString($row, 'status', Recommendation::STATUS_PENDING),
            createdAt:   $this->extractString($row, 'created_at', null),
            resolvedAt:  $this->extractString($row, 'resolved_at', null)
        );
    }

    public function toDomainCollection(array $rows): array
    {
        return array_map(fn(array $row) => $this->toDomain($row), $rows);
    }

    public function toPersistence(Recommendation $recommendation): array
    {
        return [
            'sender_id'    => $recommendation->getSenderId(),
            'recipient_id' => $recommendation->getRecipientId(),
            'entity_type'  => $recommendation->getEntityType(),
            'entity_id'    => $recommendation->getEntityId(),
            'entity_title' => $recommendation->getEntityTitle(),
            'entity_cover' => $recommendation->getEntityCover(),
            'comment'      => $recommendation->getComment(),
            'status'       => $recommendation->getStatus(),
        ];
    }
}
