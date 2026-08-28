<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\Recommendation;
use InvalidArgumentException;

final readonly class SendRecommendationCommand
{
    public function __construct(
        public int     $senderId,
        public int     $recipientId,
        public string  $entityType,
        public string  $entityId,
        public ?string $entityTitle = null,
        public ?string $entityCover = null,
        public ?string $comment = null
    ) {
        if ($senderId === $recipientId) {
            throw new InvalidArgumentException('Cannot recommend an item to yourself');
        }
    }

    public static function fromArray(array $data, int $userId): self
    {
        $recipientId = (int) ($data['recipientId'] ?? $data['recipient_id'] ?? 0);
        if ($recipientId <= 0) {
            throw new InvalidArgumentException('recipientId is required');
        }

        $entityType = trim((string) ($data['entityType'] ?? $data['entity_type'] ?? ''));
        if (!in_array($entityType, Recommendation::VALID_ENTITY_TYPES, true)) {
            throw new InvalidArgumentException('entityType must be one of: ' . implode(', ', Recommendation::VALID_ENTITY_TYPES));
        }

        $entityId = trim((string) ($data['entityId'] ?? $data['entity_id'] ?? ''));
        if ($entityId === '') {
            throw new InvalidArgumentException('entityId is required');
        }

        $comment = $data['comment'] ?? null;

        return new self(
            senderId:    $userId,
            recipientId: $recipientId,
            entityType:  $entityType,
            entityId:    $entityId,
            entityTitle: self::recortar($data['entityTitle'] ?? $data['entity_title'] ?? null, 255),
            entityCover: self::recortar($data['entityCover'] ?? $data['entity_cover'] ?? null, 500),
            comment:     is_string($comment) && trim($comment) !== '' ? trim($comment) : null
        );
    }

    /**
     * Las dos columnas copiadas tienen tope en el esquema, y lo que llega es una
     * ficha de catálogo ajeno: un título largo tiene que recortarse aquí y no
     * reventar el INSERT.
     */
    private static function recortar(mixed $valor, int $maximo): ?string
    {
        if (!is_string($valor) || trim($valor) === '') {
            return null;
        }

        return mb_substr(trim($valor), 0, $maximo);
    }
}
