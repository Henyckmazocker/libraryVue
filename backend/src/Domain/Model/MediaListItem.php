<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

/**
 * Un ítem dentro de una `MediaList`, identificado por el par
 * `entityType` + `entityId`.
 *
 * La lista guarda **claves de catálogo, nunca el ítem**: quitar un ítem de una
 * lista no lo borra de la biblioteca de nadie, y un ítem puede estar en una
 * lista sin estar en la biblioteca de nadie. De ahí que el título y la portada
 * se copien aquí — la lista tiene que poder pintarse sin resolver nada contra
 * cinco catálogos distintos.
 *
 * `series` no es un tipo válido: las series se guardan con `AddMovieUseCase`,
 * así que viajan como `movie`, igual que en `feed_events`, `recommendations` y
 * `cover_file`.
 */
class MediaListItem
{
    public const VALID_ENTITY_TYPES = ['book', 'movie', 'game', 'album', 'video'];

    public function __construct(
        private ?int    $id,
        private int     $listId,
        private string  $entityType,
        private string  $entityId,
        private int     $addedBy,
        private ?string $entityTitle = null,
        private ?string $entityCover = null,
        private int     $position = 0,
        private ?string $addedAt = null
    ) {
        if (!in_array($entityType, self::VALID_ENTITY_TYPES, true)) {
            throw new InvalidArgumentException("Invalid entity type: {$entityType}");
        }
        if ($entityId === '') {
            throw new InvalidArgumentException('entityId cannot be empty');
        }
        if ($position < 0) {
            throw new InvalidArgumentException('position cannot be negative');
        }
    }

    public function getId(): ?int             { return $this->id; }
    public function getListId(): int          { return $this->listId; }
    public function getEntityType(): string   { return $this->entityType; }
    public function getEntityId(): string     { return $this->entityId; }
    public function getAddedBy(): int         { return $this->addedBy; }
    public function getEntityTitle(): ?string { return $this->entityTitle; }
    public function getEntityCover(): ?string { return $this->entityCover; }
    public function getPosition(): int        { return $this->position; }
    public function getAddedAt(): ?string     { return $this->addedAt; }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'list_id'      => $this->listId,
            'entity_type'  => $this->entityType,
            'entity_id'    => $this->entityId,
            'entity_title' => $this->entityTitle,
            'entity_cover' => $this->entityCover,
            'added_by'     => $this->addedBy,
            'position'     => $this->position,
            'added_at'     => $this->addedAt,
        ];
    }
}
