<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

final readonly class TrackSeriesSeasonCommand
{
    private const VALID_STATUSES = ['viewed', 'partial', 'skipped'];

    public function __construct(
        public int $userId,
        public string $seriesIsbn,
        public int $seasonNumber,
        public string $status = 'viewed',
        public ?string $dateViewed = null,
        public ?float $personalRating = null,
        public ?string $notes = null,
    ) {
        if ($seasonNumber < 1) {
            throw new InvalidArgumentException('Season number must be >= 1');
        }
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                'Status must be one of: ' . implode(', ', self::VALID_STATUSES)
            );
        }
        if ($personalRating !== null) {
            if ($personalRating < 0.5 || $personalRating > 5.0 || fmod($personalRating * 2, 1) !== 0.0) {
                throw new InvalidArgumentException(
                    'Personal rating must be between 0.5 and 5.0 in 0.5 increments'
                );
            }
        }
    }

    public static function fromArray(array $data, int $userId): self
    {
        $rating = $data['personalRating'] ?? $data['personal_rating'] ?? null;
        if ($rating !== null) {
            $rating = (float) $rating;
        }

        return new self(
            userId: $userId,
            seriesIsbn: (string) ($data['seriesIsbn'] ?? $data['series_isbn'] ?? ''),
            seasonNumber: (int) ($data['seasonNumber'] ?? $data['season_number'] ?? 0),
            status: (string) ($data['status'] ?? 'viewed'),
            dateViewed: isset($data['dateViewed']) || isset($data['date_viewed'])
                ? ($data['dateViewed'] ?? $data['date_viewed']) : null,
            personalRating: $rating,
            notes: $data['notes'] ?? null,
        );
    }
}
