<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

/**
 * El ítem que un club está consumiendo, o uno que ya terminó.
 *
 * **Lo activo es `finishedAt === null`**, y no hay bandera que lo diga: un
 * booleano y una fecha afirmando lo mismo se desincronizan, y el que quedara
 * mal sería el que decide si el club puede elegir otro. La regla «solo uno
 * activo por club» la impone `SetClubPickUseCase` y no el esquema, porque MySQL
 * no tiene índices parciales.
 *
 * `entityTitle` y `entityCover` van copiados, igual que en `feed_events`,
 * `recommendations` y `media_list_item`: la pantalla del club se pinta sin
 * resolver nada contra cinco catálogos.
 */
class ClubPick
{
    /**
     * Los cinco del ENUM de la columna. **`series` no está**, y no es un
     * olvido: una serie se guarda con `AddMovieUseCase` y viaja como `'movie'`,
     * igual que en `feed_events`.
     */
    public const ENTITY_TYPES = ['book', 'movie', 'game', 'album', 'video'];

    public function __construct(
        private ?int    $id,
        private int     $clubId,
        private string  $entityType,
        private string  $entityId,
        private ?string $entityTitle = null,
        private ?string $entityCover = null,
        private ?string $startedAt = null,
        private ?string $finishedAt = null
    ) {
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException('Invalid entity type: ' . $entityType);
        }
        if (trim($entityId) === '') {
            throw new InvalidArgumentException('Entity id cannot be empty');
        }
    }

    public function getId(): ?int             { return $this->id; }
    public function getClubId(): int          { return $this->clubId; }
    public function getEntityType(): string   { return $this->entityType; }
    public function getEntityId(): string     { return $this->entityId; }
    public function getEntityTitle(): ?string { return $this->entityTitle; }
    public function getEntityCover(): ?string { return $this->entityCover; }
    public function getStartedAt(): ?string   { return $this->startedAt; }
    public function getFinishedAt(): ?string  { return $this->finishedAt; }

    public function isActive(): bool
    {
        return $this->finishedAt === null;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'club_id'      => $this->clubId,
            'entity_type'  => $this->entityType,
            'entity_id'    => $this->entityId,
            'entity_title' => $this->entityTitle,
            'entity_cover' => $this->entityCover,
            'started_at'   => $this->startedAt,
            'finished_at'  => $this->finishedAt,
        ];
    }
}
