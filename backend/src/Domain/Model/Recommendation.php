<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;
use LogicException;

/**
 * Un ítem que un usuario le manda a un amigo, con o sin comentario.
 *
 * La identidad del ítem es el par `entityType` + `entityId`, el mismo que usa
 * `feed_events`: no hay una recomendación por medio. `series` no es un tipo
 * válido — las series se guardan con `AddMovieUseCase`, así que viajan como
 * `movie`.
 */
class Recommendation
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ADDED     = 'added';
    public const STATUS_DISMISSED = 'dismissed';

    private const VALID_STATUSES = [self::STATUS_PENDING, self::STATUS_ADDED, self::STATUS_DISMISSED];

    /**
     * Los cinco medios. Es lo que `send_recommendation` acepta.
     */
    public const MEDIA_ENTITY_TYPES = ['book', 'movie', 'game', 'album', 'video'];

    /** Una invitación a colaborar en una lista, que entra por el mismo buzón. */
    public const ENTITY_LIST = 'list';

    /**
     * Lo que la COLUMNA acepta, que es más que lo que se puede recomendar.
     *
     * La separación no es cosmética: si `send_recommendation` validara contra
     * esta constante, se podría «recomendar» una lista como si fuera un ítem y
     * la bandeja intentaría darla de alta en la biblioteca con el `enrich` de un
     * medio que no existe.
     */
    public const VALID_ENTITY_TYPES = [...self::MEDIA_ENTITY_TYPES, self::ENTITY_LIST];

    public function __construct(
        private ?int    $id,
        private int     $senderId,
        private int     $recipientId,
        private string  $entityType,
        private string  $entityId,
        private ?string $entityTitle = null,
        private ?string $entityCover = null,
        private ?string $comment = null,
        private string  $status = self::STATUS_PENDING,
        private ?string $createdAt = null,
        private ?string $resolvedAt = null
    ) {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid recommendation status: {$status}");
        }
        if (!in_array($entityType, self::VALID_ENTITY_TYPES, true)) {
            throw new InvalidArgumentException("Invalid entity type: {$entityType}");
        }
        if ($entityId === '') {
            throw new InvalidArgumentException('entityId cannot be empty');
        }
        if ($senderId === $recipientId) {
            throw new InvalidArgumentException('A user cannot recommend an item to themselves');
        }
    }

    public function getId(): ?int            { return $this->id; }
    public function getSenderId(): int       { return $this->senderId; }
    public function getRecipientId(): int    { return $this->recipientId; }
    public function getEntityType(): string  { return $this->entityType; }
    public function getEntityId(): string    { return $this->entityId; }
    public function getEntityTitle(): ?string { return $this->entityTitle; }
    public function getEntityCover(): ?string { return $this->entityCover; }
    public function getComment(): ?string    { return $this->comment; }
    public function getStatus(): string      { return $this->status; }
    public function getCreatedAt(): ?string   { return $this->createdAt; }
    public function getResolvedAt(): ?string  { return $this->resolvedAt; }

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }

    /** Una invitación a colaborar no es una recomendación de un ítem. */
    public function isListInvitation(): bool { return $this->entityType === self::ENTITY_LIST; }

    /**
     * Marca la recomendación como atendida. Solo desde `pending`: resolver dos
     * veces es una carrera entre dos pestañas, no una operación válida.
     */
    public function resolve(string $status): void
    {
        if (!in_array($status, [self::STATUS_ADDED, self::STATUS_DISMISSED], true)) {
            throw new InvalidArgumentException("Cannot resolve a recommendation as: {$status}");
        }
        if ($this->status !== self::STATUS_PENDING) {
            throw new LogicException('Only pending recommendations can be resolved');
        }

        $this->status = $status;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'sender_id'    => $this->senderId,
            'recipient_id' => $this->recipientId,
            'entity_type'  => $this->entityType,
            'entity_id'    => $this->entityId,
            'entity_title' => $this->entityTitle,
            'entity_cover' => $this->entityCover,
            'comment'      => $this->comment,
            'status'       => $this->status,
            'created_at'   => $this->createdAt,
            'resolved_at'  => $this->resolvedAt,
        ];
    }
}
