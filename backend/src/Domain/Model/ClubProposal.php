<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

/**
 * Lo que un miembro propone para la ronda en curso. Una por persona y ronda, y
 * eso lo impone el `UNIQUE (round_id, user_id)` del esquema y no el use case:
 * es lo único que aguanta dos pestañas enviando a la vez.
 *
 * `entityTitle` y `entityCover` van copiados, igual que en `ClubPick`. Con una
 * diferencia que importa al pintarla: la propuesta es un ítem que **no está en
 * la biblioteca de nadie**, así que su portada es de catálogo
 * (`CoverService.catalogCoverUrl()`), no la local.
 */
class ClubProposal
{
    /**
     * Los mismos cinco de `ClubPick`, y se referencian en vez de reescribirse:
     * una propuesta acaba siendo un `club_pick`, así que dos listas que puedan
     * divergir dejarían proponer algo que luego no se puede elegir. **`series`
     * no está**: viaja como `'movie'`.
     */
    public const ENTITY_TYPES = ClubPick::ENTITY_TYPES;

    public function __construct(
        private ?int    $id,
        private int     $roundId,
        private int     $userId,
        private string  $entityType,
        private string  $entityId,
        private ?string $entityTitle = null,
        private ?string $entityCover = null,
        private ?string $createdAt = null
    ) {
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException('Invalid entity type: ' . $entityType);
        }
        if (trim($entityId) === '') {
            throw new InvalidArgumentException('Entity id cannot be empty');
        }
    }

    public function getId(): ?int             { return $this->id; }
    public function getRoundId(): int         { return $this->roundId; }
    public function getUserId(): int          { return $this->userId; }
    public function getEntityType(): string   { return $this->entityType; }
    public function getEntityId(): string     { return $this->entityId; }
    public function getEntityTitle(): ?string { return $this->entityTitle; }
    public function getEntityCover(): ?string { return $this->entityCover; }
    public function getCreatedAt(): ?string   { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'round_id'     => $this->roundId,
            'user_id'      => $this->userId,
            'entity_type'  => $this->entityType,
            'entity_id'    => $this->entityId,
            'entity_title' => $this->entityTitle,
            'entity_cover' => $this->entityCover,
            'created_at'   => $this->createdAt,
        ];
    }
}
