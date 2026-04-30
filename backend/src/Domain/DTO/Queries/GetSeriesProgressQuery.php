<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

final readonly class GetSeriesProgressQuery
{
    public function __construct(
        public int $userId,
        public string $seriesIsbn,
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            seriesIsbn: (string) ($data['seriesIsbn'] ?? $data['series_isbn'] ?? ''),
        );
    }
}
