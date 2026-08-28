<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

/**
 * Una colección con nombre que mezcla medios.
 *
 * A diferencia de los estados (`user_book_statuses` y hermanas) y de los tags,
 * que son un conjunto cerrado y por medio, una lista agrupa lo que quiera el
 * usuario y cruza los cinco. Sus ítems viven en `MediaListItem`, identificados
 * por el par `entityType` + `entityId` que ya usan `feed_events` y
 * `recommendations`.
 *
 * El modelo NO sabe quién puede verla ni editarla: eso lo contesta
 * `ListAccess`, y vive fuera a propósito para que exista una sola copia de la
 * regla.
 */
class MediaList
{
    public const VISIBILITY_PRIVATE       = 'private';
    public const VISIBILITY_PUBLIC        = 'public';
    public const VISIBILITY_COLLABORATIVE = 'collaborative';

    public const VALID_VISIBILITIES = [
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_PUBLIC,
        self::VISIBILITY_COLLABORATIVE,
    ];

    private const NAME_MAX_LENGTH = 120;

    public function __construct(
        private ?int    $id,
        private int     $ownerId,
        private string  $name,
        private ?string $description = null,
        private string  $visibility = self::VISIBILITY_PRIVATE,
        private ?string $createdAt = null,
        private ?string $updatedAt = null
    ) {
        if (!in_array($visibility, self::VALID_VISIBILITIES, true)) {
            throw new InvalidArgumentException("Invalid list visibility: {$visibility}");
        }
        if (trim($name) === '') {
            throw new InvalidArgumentException('List name cannot be empty');
        }
        // El VARCHAR(120) de la columna truncaría en silencio; aquí es un error.
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidArgumentException('List name cannot exceed ' . self::NAME_MAX_LENGTH . ' characters');
        }
    }

    public function getId(): ?int              { return $this->id; }
    public function getOwnerId(): int          { return $this->ownerId; }
    public function getName(): string          { return $this->name; }
    public function getDescription(): ?string  { return $this->description; }
    public function getVisibility(): string    { return $this->visibility; }
    public function getCreatedAt(): ?string    { return $this->createdAt; }
    public function getUpdatedAt(): ?string    { return $this->updatedAt; }

    public function isPrivate(): bool       { return $this->visibility === self::VISIBILITY_PRIVATE; }
    public function isPublic(): bool        { return $this->visibility === self::VISIBILITY_PUBLIC; }
    public function isCollaborative(): bool { return $this->visibility === self::VISIBILITY_COLLABORATIVE; }

    public function isOwnedBy(?int $userId): bool
    {
        return $userId !== null && $this->ownerId === $userId;
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'owner_id'    => $this->ownerId,
            'name'        => $this->name,
            'description' => $this->description,
            'visibility'  => $this->visibility,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
