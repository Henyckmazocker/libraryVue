<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\MediaList;
use InvalidArgumentException;

final readonly class CreateListCommand
{
    public function __construct(
        public int     $ownerId,
        public string  $name,
        public ?string $description = null,
        public string  $visibility = MediaList::VISIBILITY_PRIVATE
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('name is required');
        }

        $visibility = trim((string) ($data['visibility'] ?? MediaList::VISIBILITY_PRIVATE));
        if (!in_array($visibility, MediaList::VALID_VISIBILITIES, true)) {
            throw new InvalidArgumentException(
                'visibility must be one of: ' . implode(', ', MediaList::VALID_VISIBILITIES)
            );
        }

        $description = $data['description'] ?? null;

        return new self(
            ownerId:     $userId,
            // El modelo rechaza lo que pase de 120; recortar aquí en silencio
            // le cambiaría el nombre a la lista sin avisar.
            name:        $name,
            description: is_string($description) && trim($description) !== '' ? trim($description) : null,
            visibility:  $visibility
        );
    }
}
