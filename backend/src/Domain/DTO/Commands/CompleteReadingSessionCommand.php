<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

readonly class CompleteReadingSessionCommand
{
    public function __construct(
        public int $sessionId,
        public int $endPage,
        public string $reason = 'completed'
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sessionId: (int)($data['sessionId'] ?? 0),
            endPage: (int)($data['endPage'] ?? 0),
            reason: $data['reason'] ?? 'completed'
        );
    }
}
