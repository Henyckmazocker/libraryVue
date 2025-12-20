<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

readonly class CreateReadingSessionCommand
{
    public function __construct(
        public int $userId,
        public string $isbn,
        public ?int $startPage = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            isbn: $data['isbn'] ?? '',
            startPage: $data['startPage'] ?? null
        );
    }
}
