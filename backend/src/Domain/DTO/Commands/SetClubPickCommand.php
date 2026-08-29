<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ClubPick;
use InvalidArgumentException;

final readonly class SetClubPickCommand
{
    public function __construct(
        public int     $userId,
        public int     $clubId,
        public string  $entityType,
        public string  $entityId,
        public ?string $entityTitle = null,
        public ?string $entityCover = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $clubId = (int) ($data['clubId'] ?? $data['club_id'] ?? 0);
        if ($clubId <= 0) {
            throw new InvalidArgumentException('clubId is required');
        }

        // Se valida aquí y no solo en el modelo para que un tipo inventado dé
        // 400 y no un 500 por ENUM truncado. `series` NO está: viaja como
        // `movie`, igual que se guarda.
        $entityType = trim((string) ($data['entityType'] ?? $data['entity_type'] ?? ''));
        if (!in_array($entityType, ClubPick::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException(
                'entityType must be one of: ' . implode(', ', ClubPick::ENTITY_TYPES)
            );
        }

        $entityId = trim((string) ($data['entityId'] ?? $data['entity_id'] ?? ''));
        if ($entityId === '') {
            throw new InvalidArgumentException('entityId is required');
        }

        $title = $data['entityTitle'] ?? $data['entity_title'] ?? null;
        $cover = $data['entityCover'] ?? $data['entity_cover'] ?? null;

        return new self(
            userId:      $userId,
            clubId:      $clubId,
            entityType:  $entityType,
            entityId:    $entityId,
            entityTitle: is_string($title) && trim($title) !== '' ? trim($title) : null,
            entityCover: is_string($cover) && trim($cover) !== '' ? trim($cover) : null
        );
    }
}
