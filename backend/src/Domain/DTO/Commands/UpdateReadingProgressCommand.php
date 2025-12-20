<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

readonly class UpdateReadingProgressCommand
{
    public function __construct(
        public int $userId,
        public string $isbn,
        public int $currentPage,
        public ?int $sessionId = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            isbn: $data['isbn'] ?? '',
            currentPage: (int)($data['currentPage'] ?? 0),
            sessionId: isset($data['sessionId']) ? (int)$data['sessionId'] : null
        );
    }
}
