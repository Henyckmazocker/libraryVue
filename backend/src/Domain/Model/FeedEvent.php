<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

class FeedEvent
{
    public const TYPE_ITEM_ADDED      = 'item_added';
    public const TYPE_STATUS_CHANGED  = 'status_changed';
    public const TYPE_ITEM_RATED      = 'item_rated';
    public const TYPE_NOTES_UPDATED   = 'notes_updated';
    public const TYPE_READING_SESSION = 'reading_session';
    public const TYPE_ACHIEVEMENT     = 'achievement';

    public const ENTITY_BOOK  = 'book';
    public const ENTITY_MOVIE = 'movie';
    public const ENTITY_GAME  = 'game';
    public const ENTITY_ALBUM = 'album';

    private const VALID_TYPES = [
        self::TYPE_ITEM_ADDED,
        self::TYPE_STATUS_CHANGED,
        self::TYPE_ITEM_RATED,
        self::TYPE_NOTES_UPDATED,
        self::TYPE_READING_SESSION,
        self::TYPE_ACHIEVEMENT,
    ];

    private const VALID_ENTITY_TYPES = [
        self::ENTITY_BOOK,
        self::ENTITY_MOVIE,
        self::ENTITY_GAME,
        self::ENTITY_ALBUM,
    ];

    public function __construct(
        private ?int    $id,
        private int     $userId,
        private string  $eventType,
        private ?string $entityType,
        private ?string $entityId,
        private ?string $entityTitle,
        private ?string $entityCover,
        private ?array  $metadata,
        private ?string $createdAt = null
    ) {
        if (!in_array($eventType, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException("Invalid event type: {$eventType}");
        }
        if ($entityType !== null && !in_array($entityType, self::VALID_ENTITY_TYPES, true)) {
            throw new InvalidArgumentException("Invalid entity type: {$entityType}");
        }
    }

    public function getId(): ?int        { return $this->id; }
    public function getUserId(): int     { return $this->userId; }
    public function getEventType(): string  { return $this->eventType; }
    public function getEntityType(): ?string { return $this->entityType; }
    public function getEntityId(): ?string   { return $this->entityId; }
    public function getEntityTitle(): ?string { return $this->entityTitle; }
    public function getEntityCover(): ?string { return $this->entityCover; }
    public function getMetadata(): ?array    { return $this->metadata; }
    public function getCreatedAt(): ?string  { return $this->createdAt; }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->userId,
            'event_type'   => $this->eventType,
            'entity_type'  => $this->entityType,
            'entity_id'    => $this->entityId,
            'entity_title' => $this->entityTitle,
            'entity_cover' => $this->entityCover,
            'metadata'     => $this->metadata,
            'created_at'   => $this->createdAt,
        ];
    }
}
