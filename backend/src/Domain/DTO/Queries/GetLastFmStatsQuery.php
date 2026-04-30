<?php
declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query DTO for retrieving Last.fm listening statistics.
 */
final readonly class GetLastFmStatsQuery
{
    /**
     * @param int    $userId         Internal user ID (to fetch lastfm_username from DB)
     * @param string $statsType      Which stats to fetch: user_info | top_albums | top_artists |
     *                               top_tracks | recent_tracks | loved_tracks |
     *                               weekly_album_chart | weekly_artist_chart | album_info
     * @param string $period         Allowed periods: overall | 7day | 1month | 3month | 6month | 12month
     * @param int    $limit          Number of items to return (max 200 for most endpoints)
     * @param string $artist         Artist name (required for album_info)
     * @param string $album          Album title  (required for album_info)
     */
    public function __construct(
        public int $userId,
        public string $statsType = 'user_info',
        public string $period = 'overall',
        public int $limit = 20,
        public string $artist = '',
        public string $album = ''
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId:    $userId,
            statsType: $data['stats_type'] ?? $data['statsType'] ?? 'user_info',
            period:    $data['period'] ?? 'overall',
            limit:     isset($data['limit']) ? min(200, max(1, (int) $data['limit'])) : 20,
            artist:    $data['artist'] ?? '',
            album:     $data['album'] ?? ''
        );
    }
}
