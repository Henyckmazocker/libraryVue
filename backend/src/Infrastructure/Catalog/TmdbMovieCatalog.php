<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Domain\Repository\Catalog\MovieCatalogInterface;
use App\Domain\Services\TmdbService;
use PDO;

/**
 * Catalog served from TMDB, in OMDb's shape
 *
 * The network half of the pair. It exists so FallbackMovieCatalog can treat
 * "local" and "remote" as the same thing, and so the mapping from TMDB's
 * payload to the keys the frontend reads lives in exactly one place.
 *
 * What it fetches is written to library_mirror.tmdb_title with a cached_at, and
 * read back from there while it is fresh. That table is not an optimisation:
 * TMDB's terms allow caching for at most 6 months, and without a column saying
 * when each row was fetched there is no way to honour that — nor to know what
 * is on disk. The window here is 5 months, the same margin mirror-sync.sh
 * --purge uses.
 */
class TmdbMovieCatalog implements MovieCatalogInterface
{
    /** Kept below TMDB's 6-month ceiling, with room for a late purge */
    private const FRESH_FOR = '5 MONTH';

    private TmdbService $tmdb;
    private PDO $mirror;

    public function __construct(TmdbService $tmdb, PDO $mirror)
    {
        $this->tmdb   = $tmdb;
        $this->mirror = $mirror;
    }

    /**
     * TMDB is never used to search
     *
     * Search is the whole point of the mirror: 1.28 M titles answering in
     * milliseconds, offline. Falling back to the network here would reintroduce
     * exactly the dependency this replaced — and TMDB's search is no better at
     * Spanish release titles than title.akas is.
     */
    public function search(string $query, string $type = '', int $limit = 20): array
    {
        return [];
    }

    public function findByImdbId(string $imdbId): ?array
    {
        $cached = $this->readCached($imdbId);
        if ($cached !== null) {
            return $cached;
        }

        $found = $this->tmdb->findByImdbId($imdbId);
        if ($found === null) {
            return null;
        }

        $details = $this->tmdb->details($found['tmdb_id'], $found['media_type']);
        $isTv    = $found['media_type'] === 'tv';

        $record = [
            'Title'        => $found['title'],
            'Year'         => $this->year($details, $isTv),
            'Runtime'      => $this->runtime($details, $isTv),
            'Genre'        => $this->genres($details),
            'Director'     => $this->director($details),
            'Plot'         => $found['overview'],
            'Poster'       => $this->poster($found['poster_path']),
            'imdbRating'   => null,   // IMDb's rating comes from the mirror, not from here
            'imdbID'       => $imdbId,
            'Type'         => $isTv ? 'series' : 'movie',
            'totalSeasons' => $isTv && isset($details['number_of_seasons'])
                ? (string) $details['number_of_seasons']
                : null,
        ];

        $this->store($imdbId, $found, $record);

        return $record;
    }

    /**
     * The stored copy, if there is one and it is still within the allowed window
     */
    private function readCached(string $imdbId): ?array
    {
        $stmt = $this->mirror->prepare(
            'SELECT media_type, title_es, overview_es, poster_path, director, total_seasons
             FROM tmdb_title
             WHERE tconst = :tconst AND cached_at > NOW() - INTERVAL ' . self::FRESH_FOR
        );
        $stmt->execute([':tconst' => $imdbId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $isTv = $row['media_type'] === 'tv';

        // Only the fields TMDB is the source for. Runtime, Genre and Year come
        // from the mirror and the decorator keeps its own; filling them from
        // here would be inventing data this table does not hold.
        return [
            'Title'        => $row['title_es'],
            'Year'         => null,
            'Runtime'      => null,
            'Genre'        => null,
            'Director'     => $row['director'],
            'Plot'         => $row['overview_es'],
            'Poster'       => $this->poster($row['poster_path']),
            'imdbRating'   => null,
            'imdbID'       => $imdbId,
            'Type'         => $isTv ? 'series' : 'movie',
            'totalSeasons' => $row['total_seasons'] !== null ? (string) $row['total_seasons'] : null,
        ];
    }

    /**
     * Write the row, refreshing cached_at, so the 6-month rule can be honoured
     */
    private function store(string $imdbId, array $found, array $record): void
    {
        $stmt = $this->mirror->prepare(
            'INSERT INTO tmdb_title
                 (tconst, tmdb_id, media_type, title_es, overview_es, poster_path,
                  director, total_seasons, cached_at)
             VALUES (:tconst, :tmdb_id, :media_type, :title_es, :overview_es, :poster_path,
                     :director, :total_seasons, NOW())
             ON DUPLICATE KEY UPDATE
                 tmdb_id       = VALUES(tmdb_id),
                 media_type    = VALUES(media_type),
                 title_es      = VALUES(title_es),
                 overview_es   = VALUES(overview_es),
                 poster_path   = VALUES(poster_path),
                 director      = VALUES(director),
                 total_seasons = VALUES(total_seasons),
                 cached_at     = NOW()'
        );

        $stmt->execute([
            ':tconst'        => $imdbId,
            ':tmdb_id'       => $found['tmdb_id'],
            ':media_type'    => $found['media_type'],
            ':title_es'      => $record['Title'],
            ':overview_es'   => $record['Plot'],
            ':poster_path'   => $found['poster_path'],
            ':director'      => $record['Director'],
            ':total_seasons' => $record['totalSeasons'],
        ]);
    }

    public function seasonEpisodes(string $imdbId, int $season): array
    {
        $found = $this->tmdb->findByImdbId($imdbId);
        if ($found === null || $found['media_type'] !== 'tv') {
            return [];
        }

        return array_map(
            static fn (array $ep): array => [
                'Title'      => $ep['name'] ?? null,
                'Episode'    => isset($ep['episode_number']) ? (string) $ep['episode_number'] : null,
                'imdbID'     => null,   // TMDB does not return the episode's IMDb id here
                'imdbRating' => isset($ep['vote_average']) ? (string) round((float) $ep['vote_average'], 1) : null,
                'Released'   => ($ep['air_date'] ?? '') !== '' ? $ep['air_date'] : null,
            ],
            $this->tmdb->seasonEpisodes($found['tmdb_id'], $season)
        );
    }

    private function year(?array $details, bool $isTv): ?string
    {
        $date = $isTv ? ($details['first_air_date'] ?? '') : ($details['release_date'] ?? '');

        return $date !== '' ? substr($date, 0, 4) : null;
    }

    private function runtime(?array $details, bool $isTv): ?string
    {
        $minutes = $isTv
            ? ($details['episode_run_time'][0] ?? null)
            : ($details['runtime'] ?? null);

        return $minutes ? $minutes . ' min' : null;
    }

    private function genres(?array $details): ?string
    {
        $names = array_column($details['genres'] ?? [], 'name');

        return $names !== [] ? implode(', ', $names) : null;
    }

    private function director(?array $details): ?string
    {
        foreach ($details['credits']['crew'] ?? [] as $member) {
            if (($member['job'] ?? '') === 'Director') {
                return $member['name'] ?? null;
            }
        }

        return null;
    }

    private function poster(?string $path): ?string
    {
        return $path !== null ? TmdbService::IMAGE_BASE . $path : null;
    }
}
