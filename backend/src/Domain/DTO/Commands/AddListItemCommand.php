<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\MediaListItem;
use InvalidArgumentException;

final readonly class AddListItemCommand
{
    public function __construct(
        public int     $userId,
        public int     $listId,
        public string  $entityType,
        public string  $entityId,
        public ?string $entityTitle = null,
        public ?string $entityCover = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $listId = (int) ($data['listId'] ?? $data['list_id'] ?? 0);
        if ($listId <= 0) {
            throw new InvalidArgumentException('listId is required');
        }

        $entityType = trim((string) ($data['entityType'] ?? $data['entity_type'] ?? ''));
        if (!in_array($entityType, MediaListItem::VALID_ENTITY_TYPES, true)) {
            throw new InvalidArgumentException(
                'entityType must be one of: ' . implode(', ', MediaListItem::VALID_ENTITY_TYPES)
            );
        }

        $entityId = trim((string) ($data['entityId'] ?? $data['entity_id'] ?? ''));
        if ($entityId === '') {
            throw new InvalidArgumentException('entityId is required');
        }

        return new self(
            userId:      $userId,
            listId:      $listId,
            entityType:  $entityType,
            entityId:    $entityId,
            entityTitle: self::recortar($data['entityTitle'] ?? $data['entity_title'] ?? null, 255),
            entityCover: self::recortar($data['entityCover'] ?? $data['entity_cover'] ?? null, 500)
        );
    }

    /**
     * Las dos columnas copiadas tienen tope en el esquema, y lo que llega es una
     * ficha de catálogo ajeno: un título largo se recorta aquí en vez de
     * reventar el INSERT. Mismo criterio que `SendRecommendationCommand`.
     */
    private static function recortar(mixed $valor, int $maximo): ?string
    {
        if (!is_string($valor) || trim($valor) === '') {
            return null;
        }

        return mb_substr(trim($valor), 0, $maximo);
    }
}
