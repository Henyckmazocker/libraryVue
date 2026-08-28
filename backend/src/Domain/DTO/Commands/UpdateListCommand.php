<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\MediaList;
use InvalidArgumentException;

/**
 * Los tres campos son opcionales por separado: `null` significa «no lo toques»,
 * que es distinto de «ponlo a vacío». Por eso `description` lleva su propio
 * `descriptionProvided` — es el único de los tres que puede quedar en `NULL` a
 * propósito, y sin la bandera «bórrala» y «no la toques» serían el mismo valor.
 */
final readonly class UpdateListCommand
{
    public function __construct(
        public int     $userId,
        public int     $listId,
        public ?string $name = null,
        public ?string $description = null,
        public bool    $descriptionProvided = false,
        public ?string $visibility = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $listId = (int) ($data['listId'] ?? $data['list_id'] ?? 0);
        if ($listId <= 0) {
            throw new InvalidArgumentException('listId is required');
        }

        $name = null;
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new InvalidArgumentException('name cannot be empty');
            }
        }

        $visibility = null;
        if (array_key_exists('visibility', $data)) {
            $visibility = trim((string) $data['visibility']);
            if (!in_array($visibility, MediaList::VALID_VISIBILITIES, true)) {
                throw new InvalidArgumentException(
                    'visibility must be one of: ' . implode(', ', MediaList::VALID_VISIBILITIES)
                );
            }
        }

        $descriptionProvided = array_key_exists('description', $data);
        $description = null;
        if ($descriptionProvided) {
            $bruta = $data['description'];
            $description = is_string($bruta) && trim($bruta) !== '' ? trim($bruta) : null;
        }

        return new self(
            userId:              $userId,
            listId:              $listId,
            name:                $name,
            description:         $description,
            descriptionProvided: $descriptionProvided,
            visibility:          $visibility
        );
    }
}
