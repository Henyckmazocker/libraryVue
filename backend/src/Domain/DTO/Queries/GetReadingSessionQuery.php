<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

readonly class GetReadingSessionQuery
{
    public function __construct(
        public int $userId,
        public string $isbn
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            isbn: $data['isbn'] ?? ''
        );
    }
}
