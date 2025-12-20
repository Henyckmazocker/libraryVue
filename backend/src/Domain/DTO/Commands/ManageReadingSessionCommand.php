<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

readonly class ManageReadingSessionCommand
{
    public function __construct(
        public int $sessionId
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sessionId: (int)($data['sessionId'] ?? 0)
        );
    }
}
