<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\FeedEvent;
use InvalidArgumentException;

final readonly class CreateFeedEventCommand
{
    public function __construct(
        public int     $userId,
        public string  $eventType,
        public ?string $entityType  = null,
        public ?string $entityId    = null,
        public ?string $entityTitle = null,
        public ?string $entityCover = null,
        public ?array  $metadata    = null
    ) {
        $validTypes = [
            FeedEvent::TYPE_ITEM_ADDED,
            FeedEvent::TYPE_STATUS_CHANGED,
            FeedEvent::TYPE_ITEM_RATED,
            FeedEvent::TYPE_NOTES_UPDATED,
            FeedEvent::TYPE_READING_SESSION,
            FeedEvent::TYPE_ACHIEVEMENT,
        ];
        if (!in_array($eventType, $validTypes, true)) {
            throw new InvalidArgumentException("Invalid event type: {$eventType}");
        }
    }
}
