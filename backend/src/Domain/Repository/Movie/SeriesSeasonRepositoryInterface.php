<?php

declare(strict_types=1);

namespace App\Domain\Repository\Movie;

interface SeriesSeasonRepositoryInterface
{
    /**
     * Insert or update a season tracking record (upsert).
     */
    public function trackSeason(
        int $userId,
        string $seriesIsbn,
        int $seasonNumber,
        string $status,
        ?string $dateViewed,
        ?float $personalRating,
        ?string $notes
    ): void;

    /**
     * Return all tracked seasons for a user+series, keyed by season_number.
     *
     * @return array<int, array{status: string, date_viewed: string|null, personal_rating: float|null, notes: string|null}>
     */
    public function getProgress(int $userId, string $seriesIsbn): array;

    /**
     * Remove a season record.
     */
    public function deleteSeason(int $userId, string $seriesIsbn, int $seasonNumber): void;
}
