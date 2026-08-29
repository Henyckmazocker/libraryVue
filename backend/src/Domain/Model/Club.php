<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

/**
 * Un grupo de amigos consumiendo la misma cosa a la vez.
 *
 * Es la primera agrupación de PERSONAS del proyecto: `friendships` es una
 * relación de dos y no había concepto de grupo en ningún sitio. Lo que lo
 * distingue de una `MediaList` compartida es que un club tiene **un** ítem
 * activo (`ClubPick` con `finished_at IS NULL`) y un historial, y que las notas
 * de los demás se ocultan si te van por delante.
 *
 * El modelo no sabe quién es miembro: eso lo contesta
 * `ClubMemberRepositoryInterface`. La única regla que vive aquí es la del
 * dueño, porque se decide con lo que el propio objeto ya tiene.
 */
class Club
{
    private const NAME_MAX_LENGTH = 120;

    public function __construct(
        private ?int    $id,
        private int     $ownerId,
        private string  $name,
        private ?string $description = null,
        private ?string $createdAt = null
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Club name cannot be empty');
        }
        // El VARCHAR(120) de la columna truncaría en silencio; aquí es un error.
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidArgumentException('Club name cannot exceed ' . self::NAME_MAX_LENGTH . ' characters');
        }
    }

    public function getId(): ?int             { return $this->id; }
    public function getOwnerId(): int         { return $this->ownerId; }
    public function getName(): string         { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getCreatedAt(): ?string   { return $this->createdAt; }

    /**
     * Renombrar, invitar y borrar son del dueño. «Ser miembro» no basta, igual
     * que en las listas no basta con `canEdit` para renombrar.
     */
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
            'created_at'  => $this->createdAt,
        ];
    }
}
