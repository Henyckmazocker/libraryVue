<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Catalog;

use App\Domain\Repository\Catalog\MovieCatalogInterface;
use PDO;

/**
 * Catalog served from the local IMDb mirror (library_mirror)
 *
 * Answers in 1-9 ms and without network. What it cannot give is a plot or a
 * poster: IMDb's open datasets carry neither. Those come from TMDB, and it is
 * FallbackMovieCatalog that decides when to go and get them.
 */
class MySqlMovieCatalog implements MovieCatalogInterface
{
    /**
     * IMDb titleType → the Type the frontend expects, which is OMDb's
     *
     * OMDb only ever says 'movie' or 'series', so everything feature-shaped —
     * shorts, direct-to-video, TV specials — collapses into 'movie'.
     */
    private const TYPE_TO_OMDB = [
        'movie'        => 'movie',
        'tvMovie'      => 'movie',
        'short'        => 'movie',
        'tvShort'      => 'movie',
        'video'        => 'movie',
        'tvSpecial'    => 'movie',
        'tvSeries'     => 'series',
        'tvMiniSeries' => 'series',
        'tvPilot'      => 'series',
    ];

    /** ...and back, to filter a search by type */
    private const OMDB_TO_TYPES = [
        'movie'  => ['movie', 'tvMovie', 'short', 'tvShort', 'video', 'tvSpecial'],
        'series' => ['tvSeries', 'tvMiniSeries', 'tvPilot'],
    ];

    private PDO $mirror;

    public function __construct(PDO $mirror)
    {
        $this->mirror = $mirror;
    }

    public function search(string $query, string $type = '', int $limit = 20): array
    {
        $boolean = BooleanQueryBuilder::build($query);
        if ($boolean === '') {
            return [];
        }

        // Two FULLTEXT indexes joined through a UNION, and not the OR ... IN
        // (SELECT ...) the plan described. Measured on the real mirror: MySQL
        // cannot combine two FULLTEXT lookups with OR, so it falls back to
        // scanning all 2.84 M rows — "jungla de cristal" took **39 s**. Each
        // branch of the UNION uses its own index and the same search answers in
        // ~40 ms.
        //
        // The imdb_title_es branch is what finds Die Hard at all: IMDb stores
        // its primary and original titles in English, and "Jungla de cristal"
        // exists only in title.akas.
        $sql = 'SELECT t.tconst, t.primary_title, t.original_title, t.start_year, t.title_type
                FROM imdb_title t
                JOIN (SELECT tconst FROM imdb_title
                       WHERE MATCH(primary_title, original_title) AGAINST (:q IN BOOLEAN MODE)
                      UNION
                      SELECT tconst FROM imdb_title_es
                       WHERE MATCH(title) AGAINST (:q2 IN BOOLEAN MODE)
                     ) m ON m.tconst = t.tconst';

        $params = [':q' => $boolean, ':q2' => $boolean];

        $types = self::OMDB_TO_TYPES[$type] ?? null;
        if ($types !== null) {
            $placeholders = [];
            foreach ($types as $i => $titleType) {
                $placeholders[]        = ':type_' . $i;
                $params[':type_' . $i] = $titleType;
            }
            $sql .= ' WHERE t.title_type IN (' . implode(', ', $placeholders) . ')';
        }

        // num_votes DESC is what makes "matrix" return The Matrix (1999) and
        // not "Matrix Dreads". Without it the order is whatever InnoDB returns.
        $sql .= ' ORDER BY t.num_votes DESC LIMIT :limit';

        $stmt = $this->mirror->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): array => [
                'Title'  => $row['primary_title'],
                'Year'   => $row['start_year'] !== null ? (string) $row['start_year'] : null,
                'imdbID' => $row['tconst'],
                'Type'   => self::TYPE_TO_OMDB[$row['title_type']] ?? 'movie',
                'Poster' => null,
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findByImdbId(string $imdbId): ?array
    {
        $stmt = $this->mirror->prepare(
            'SELECT tconst, title_type, primary_title, start_year, end_year,
                    runtime_minutes, genres, average_rating
             FROM imdb_title WHERE tconst = :tconst'
        );
        $stmt->execute([':tconst' => $imdbId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $type = self::TYPE_TO_OMDB[$row['title_type']] ?? 'movie';

        return [
            'Title'        => $row['primary_title'],
            'Year'         => $this->formatYear($row, $type),
            'Runtime'      => $row['runtime_minutes'] !== null ? $row['runtime_minutes'] . ' min' : null,
            'Genre'        => $row['genres'] !== null ? str_replace(',', ', ', $row['genres']) : null,
            // Directors live in title.crew + name.basics, two files this mirror
            // does not ingest. Until it does, TMDB fills it in.
            'Director'     => null,
            'Plot'         => null,
            'Poster'       => null,
            'imdbRating'   => $row['average_rating'] !== null ? (string) $row['average_rating'] : null,
            'imdbID'       => $row['tconst'],
            'Type'         => $type,
            'totalSeasons' => $type === 'series' ? $this->countSeasons($imdbId) : null,
        ];
    }

    public function seasonEpisodes(string $imdbId, int $season): array
    {
        $stmt = $this->mirror->prepare(
            'SELECT tconst, episode_number, primary_title, average_rating
             FROM imdb_episode
             WHERE parent_tconst = :tconst AND season_number = :season
             ORDER BY episode_number'
        );
        $stmt->bindValue(':tconst', $imdbId, PDO::PARAM_STR);
        $stmt->bindValue(':season', $season, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): array => [
                'Title'      => $row['primary_title'],
                'Episode'    => (string) $row['episode_number'],
                'imdbID'     => $row['tconst'],
                'imdbRating' => $row['average_rating'] !== null ? (string) $row['average_rating'] : null,
                // IMDb's open datasets carry no air date for an episode.
                'Released'   => null,
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * OMDb writes a running series as "2008–" and a finished one as "2008–2013"
     */
    private function formatYear(array $row, string $type): ?string
    {
        if ($row['start_year'] === null) {
            return null;
        }
        if ($type !== 'series') {
            return (string) $row['start_year'];
        }

        return $row['start_year'] . '–' . ($row['end_year'] ?? '');
    }

    private function countSeasons(string $imdbId): ?string
    {
        $stmt = $this->mirror->prepare(
            'SELECT MAX(season_number) FROM imdb_episode WHERE parent_tconst = :tconst'
        );
        $stmt->execute([':tconst' => $imdbId]);

        $seasons = $stmt->fetchColumn();

        return $seasons !== null && $seasons !== false ? (string) $seasons : null;
    }
}
