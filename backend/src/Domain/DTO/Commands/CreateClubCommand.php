<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

final readonly class CreateClubCommand
{
    public function __construct(
        public int     $ownerId,
        public string  $name,
        public ?string $description = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('name is required');
        }

        $description = $data['description'] ?? null;

        return new self(
            ownerId: $userId,
            // El modelo rechaza lo que pase de 120; recortar aquí en silencio
            // le cambiaría el nombre al club sin avisar.
            name:        $name,
            description: is_string($description) && trim($description) !== '' ? trim($description) : null
        );
    }
}
