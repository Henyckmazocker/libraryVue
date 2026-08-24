<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

use PDO;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Imports the open IMDb datasets into the library_mirror schema
 *
 * Four files, each filtered *while streaming* into a temporary TSV and then
 * loaded with LOAD DATA INFILE. Filtering before loading is not an optimisation
 * detail: title.akas is 487 MB and 58.9 M rows, and loading it whole to delete
 * afterwards takes far longer and grows the ibd file without giving the space
 * back.
 *
 * Every table is loaded into a `_new` twin and swapped in with RENAME TABLE.
 * Importing over the live table would leave search returning nothing for the
 * ~40 s the load takes.
 *
 * The temporary TSVs are written to a directory shared with the MySQL container
 * (secure_file_priv), because LOAD DATA INFILE reads from the *server's* disk
 * and this code runs in the backend container.
 */
class ImdbImporter
{
    private const BASE_URL = 'https://datasets.imdbws.com/';

    /**
     * Everything except episodes and games, so search never needs the network
     *
     * Measured on the 2026-08-20 dump: this keeps 2.84 M of the 12.7 M rows.
     * `tvEpisode` (9.84 M) has its own table, and `videoGame` (49.886) belongs
     * to IGDB, not here. The rest — shorts, direct-to-video, TV specials — are
     * things a person legitimately tracks, and leaving them out was the only
     * reason the catalog would ever have had to fall back to TMDB to search.
     *
     * Noise is not a concern: ORDER BY num_votes DESC sinks a short with fifty
     * votes far below a film with three million.
     */
    private const TITLE_TYPES = [
        'movie'        => true,
        'tvSeries'     => true,
        'tvMiniSeries' => true,
        'tvMovie'      => true,
        'short'        => true,
        'tvShort'      => true,
        'video'        => true,
        'tvSpecial'    => true,
        'tvPilot'      => true,
    ];

    private PDO $mirror;
    private LoggerInterface $logger;
    private string $workDir;

    /** Last-Modified of the most recent download, kept for mirror_import.source_version */
    private ?string $lastModified = null;

    public function __construct(PDO $mirror, LoggerInterface $logger, string $workDir = '/var/lib/mysql-files')
    {
        $this->mirror  = $mirror;
        $this->logger  = $logger;
        $this->workDir = rtrim($workDir, '/');
    }

    // =========================================================================
    // Line filters — pure, and the only part worth unit testing
    // =========================================================================
    // IMDb writes NULLs as a literal \N, which these keep as-is: the NULLIF in
    // LOAD DATA turns them into real NULLs. Stripping them here would mean
    // deciding what an absent year looks like in two different places.

    /**
     * title.basics: tconst titleType primaryTitle originalTitle isAdult startYear endYear runtimeMinutes genres
     *
     * @return array<int,string>|null The 9 columns, or null if the row is not a type we keep
     */
    public static function filterBasicsLine(string $line): ?array
    {
        $cols = explode("\t", rtrim($line, "\r\n"));
        if (count($cols) !== 9) {
            return null;
        }
        if (!isset(self::TITLE_TYPES[$cols[1]])) {
            return null;
        }

        return $cols;
    }

    /**
     * The title of an episode, from the same title.basics line
     *
     * Episodes are `tvEpisode`, which imdb_title deliberately does not ingest —
     * but SeriesSeasonTracker.vue:158-167 prints the title and rating of every
     * episode, so they are collected here in the same pass and joined onto
     * imdb_episode afterwards.
     *
     * @return array<int,string>|null tconst and title, or null if not an episode
     */
    public static function filterEpisodeTitleLine(string $line): ?array
    {
        $cols = explode("\t", rtrim($line, "\r\n"));
        if (count($cols) !== 9 || $cols[1] !== 'tvEpisode') {
            return null;
        }

        return [$cols[0], $cols[2]];
    }

    /**
     * title.akas: titleId ordering title region language types attributes isOriginalTitle
     *
     * Spanish release titles only, and only for titles already ingested. Without
     * this table "jungla de cristal" returns nothing: IMDb stores Die Hard as
     * `Die Hard` in title.basics and the Spanish title lives here alone.
     *
     * @param array<int,bool> $ingested Set of ingested tconsts, keyed by numeric id
     * @return array<int,string>|null tconst, ordering and title, or null
     */
    public static function filterAkasLine(string $line, array $ingested): ?array
    {
        $cols = explode("\t", rtrim($line, "\r\n"));
        if (count($cols) !== 8) {
            return null;
        }
        if ($cols[3] !== 'ES' && $cols[4] !== 'es') {
            return null;
        }
        if (!isset($ingested[self::tconstKey($cols[0])])) {
            return null;
        }

        return [$cols[0], $cols[1], $cols[2]];
    }

    /**
     * title.episode: tconst parentTconst seasonNumber episodeNumber
     *
     * @param array<int,bool> $ingested Set of ingested tconsts, keyed by numeric id
     * @return array<int,string>|null The 4 columns, or null if the parent series was not ingested
     */
    public static function filterEpisodeLine(string $line, array $ingested): ?array
    {
        $cols = explode("\t", rtrim($line, "\r\n"));
        if (count($cols) !== 4) {
            return null;
        }
        if (!isset($ingested[self::tconstKey($cols[1])])) {
            return null;
        }

        return $cols;
    }

    /**
     * title.ratings: tconst averageRating numVotes
     *
     * Not filtered against the ingested set on purpose. Ratings are needed for
     * titles *and* for episodes, and holding both sets in memory would cost
     * ~11 M keys; the whole file is only 1.6 M rows, so it is cheaper to load
     * it all into a staging table and let the UPDATE ... JOINs do the filtering.
     *
     * @return array<int,string>|null The 3 columns, or null if malformed
     */
    public static function filterRatingsLine(string $line): ?array
    {
        $cols = explode("\t", rtrim($line, "\r\n"));

        return count($cols) === 3 ? $cols : null;
    }

    /**
     * A tconst as an integer key
     *
     * The ingested set holds ~1.28 M entries and is consulted once per line of
     * a 58.9 M-row file. Integer keys keep it near 50 MB; string keys do not.
     */
    public static function tconstKey(string $tconst): int
    {
        return (int) substr($tconst, 2);
    }

    // =========================================================================
    // The import itself
    // =========================================================================

    /**
     * Download, filter, load and swap the four IMDb files
     *
     * @return array{titles:int,akas:int,episodes:int,ratings:int}
     */
    public function import(): array
    {
        $this->assertWorkDirWritable();

        $importId = $this->startImportRecord();

        try {
            $this->logger->info('imdb import: title.basics');
            [$basicsPath, $episodeTitlePath, $version, $ingested] = $this->prepareBasics();

            $this->logger->info('imdb import: title.ratings');
            $ratingsPath = $this->prepareFiltered(
                'title.ratings.tsv.gz',
                fn (string $line): ?array => self::filterRatingsLine($line)
            );

            $this->logger->info('imdb import: title.akas');
            $akasPath = $this->prepareFiltered(
                'title.akas.tsv.gz',
                fn (string $line): ?array => self::filterAkasLine($line, $ingested)
            );

            $this->logger->info('imdb import: title.episode');
            $episodePath = $this->prepareFiltered(
                'title.episode.tsv.gz',
                fn (string $line): ?array => self::filterEpisodeLine($line, $ingested)
            );

            unset($ingested);

            $counts = $this->loadAndSwap(
                $basicsPath,
                $ratingsPath,
                $akasPath,
                $episodePath,
                $episodeTitlePath
            );

            $this->finishImportRecord($importId, 'ok', $counts['titles'], $version);

            return $counts;
        } catch (\Throwable $e) {
            $this->finishImportRecord($importId, 'failed', null, null);
            $this->logger->error('imdb import failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Filter title.basics into two files, and build the ingested set on the way
     *
     * One pass, three outputs: the titles the app searches, the titles of every
     * episode (a separate file because they do not belong in imdb_title), and
     * the set of ingested tconsts that the akas and episode filters need.
     *
     * @return array{0:string,1:string,2:string|null,3:array<int,bool>}
     */
    private function prepareBasics(): array
    {
        $gzPath  = $this->download('title.basics.tsv.gz');
        $version = $this->lastModified;
        $outPath = $this->workDir . '/imdb_title.tsv';
        $epPath  = $this->workDir . '/imdb_episode_title.tsv';

        $in    = gzopen($gzPath, 'rb');
        $out   = fopen($outPath, 'wb');
        $epOut = fopen($epPath, 'wb');
        if ($in === false || $out === false || $epOut === false) {
            throw new RuntimeException('Could not open title.basics for filtering');
        }

        gzgets($in); // header row

        $ingested = [];
        while (($line = gzgets($in)) !== false) {
            $cols = self::filterBasicsLine($line);
            if ($cols !== null) {
                $ingested[self::tconstKey($cols[0])] = true;
                fwrite($out, implode("\t", $cols) . "\n");
                continue;
            }

            $episode = self::filterEpisodeTitleLine($line);
            if ($episode !== null) {
                fwrite($epOut, implode("\t", $episode) . "\n");
            }
        }

        gzclose($in);
        fclose($out);
        fclose($epOut);
        @unlink($gzPath);
        $this->makeServerReadable($outPath);
        $this->makeServerReadable($epPath);

        return [$outPath, $epPath, $version, $ingested];
    }

    /**
     * Download one file and stream it through $filter into a temporary TSV
     *
     * @param callable(string):(array<int,string>|null) $filter
     */
    private function prepareFiltered(string $file, callable $filter): string
    {
        $gzPath  = $this->download($file);
        $outPath = $this->workDir . '/' . str_replace('.tsv.gz', '', $file) . '.tsv';

        $in  = gzopen($gzPath, 'rb');
        $out = fopen($outPath, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException("Could not open {$file} for filtering");
        }

        gzgets($in); // header row

        while (($line = gzgets($in)) !== false) {
            $cols = $filter($line);
            if ($cols === null) {
                continue;
            }
            fwrite($out, implode("\t", $cols) . "\n");
        }

        gzclose($in);
        fclose($out);
        @unlink($gzPath);
        $this->makeServerReadable($outPath);

        return $outPath;
    }

    private function download(string $file): string
    {
        $url    = self::BASE_URL . $file;
        $target = $this->workDir . '/' . $file;

        $in = @fopen($url, 'rb');
        if ($in === false) {
            throw new RuntimeException("Could not download {$url}");
        }

        // fopen() populates this with the response headers of the last request
        $this->lastModified = null;
        foreach ($http_response_header ?? [] as $header) {
            if (stripos($header, 'Last-Modified:') === 0) {
                $this->lastModified = trim(substr($header, 14));
            }
        }

        $out = fopen($target, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException("Could not write {$target}");
        }

        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        return $target;
    }

    /**
     * Load the filtered TSVs into `_new` tables and swap them in
     *
     * @return array{titles:int,akas:int,episodes:int,ratings:int}
     */
    private function loadAndSwap(
        string $basics,
        string $ratings,
        string $akas,
        string $episode,
        string $episodeTitles
    ): array {
        $this->recreateTwin('imdb_title');
        $this->recreateTwin('imdb_title_es');
        $this->recreateTwin('imdb_episode');

        // NULLIF(@col,'\\N'): IMDb marks NULLs with a literal \N, not an empty
        // field. ESCAPED BY '': by default MySQL reads \ as an escape character
        // and would break every title containing one.
        $this->mirror->exec(sprintf(
            "LOAD DATA INFILE %s INTO TABLE imdb_title_new
             FIELDS TERMINATED BY '\\t' ESCAPED BY ''
             LINES TERMINATED BY '\\n'
             (tconst, title_type, primary_title, @original_title, is_adult,
              @start_year, @end_year, @runtime_minutes, @genres)
             SET original_title  = NULLIF(@original_title, '\\\\N'),
                 start_year      = NULLIF(@start_year, '\\\\N'),
                 end_year        = NULLIF(@end_year, '\\\\N'),
                 runtime_minutes = NULLIF(@runtime_minutes, '\\\\N'),
                 genres          = NULLIF(@genres, '\\\\N')",
            $this->mirror->quote($basics)
        ));
        $titles = $this->countRows('imdb_title_new');

        $this->mirror->exec(sprintf(
            "LOAD DATA INFILE %s INTO TABLE imdb_title_es_new
             FIELDS TERMINATED BY '\\t' ESCAPED BY ''
             LINES TERMINATED BY '\\n'
             (tconst, ordering, title)",
            $this->mirror->quote($akas)
        ));
        $akasRows = $this->countRows('imdb_title_es_new');

        $this->mirror->exec(sprintf(
            "LOAD DATA INFILE %s INTO TABLE imdb_episode_new
             FIELDS TERMINATED BY '\\t' ESCAPED BY ''
             LINES TERMINATED BY '\\n'
             (tconst, parent_tconst, @season_number, @episode_number)
             SET season_number  = NULLIF(@season_number, '\\\\N'),
                 episode_number = NULLIF(@episode_number, '\\\\N')",
            $this->mirror->quote($episode)
        ));
        $episodes = $this->countRows('imdb_episode_new');

        // Ratings live in imdb_title, so they go through a staging table and an
        // UPDATE ... JOIN instead of a load of their own.
        $this->mirror->exec('DROP TABLE IF EXISTS imdb_ratings_stage');
        $this->mirror->exec(
            'CREATE TABLE imdb_ratings_stage (
               tconst         VARCHAR(12) PRIMARY KEY,
               average_rating DECIMAL(3,1) NULL,
               num_votes      INT UNSIGNED NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->mirror->exec(sprintf(
            "LOAD DATA INFILE %s INTO TABLE imdb_ratings_stage
             FIELDS TERMINATED BY '\\t' ESCAPED BY ''
             LINES TERMINATED BY '\\n'
             (tconst, @average_rating, @num_votes)
             SET average_rating = NULLIF(@average_rating, '\\\\N'),
                 num_votes      = NULLIF(@num_votes, '\\\\N')",
            $this->mirror->quote($ratings)
        ));
        $ratingsRows = $this->countRows('imdb_ratings_stage');

        $this->mirror->exec(
            'UPDATE imdb_title_new t
               JOIN imdb_ratings_stage r ON r.tconst = t.tconst
                SET t.average_rating = r.average_rating,
                    t.num_votes      = r.num_votes'
        );
        $this->mirror->exec(
            'UPDATE imdb_episode_new e
               JOIN imdb_ratings_stage r ON r.tconst = e.tconst
                SET e.average_rating = r.average_rating,
                    e.num_votes      = r.num_votes'
        );
        $this->mirror->exec('DROP TABLE imdb_ratings_stage');

        // Los títulos de episodio llegan enteros (todas las filas tvEpisode de
        // title.basics) y es el JOIN el que se queda con los de series
        // ingestadas: filtrarlos en PHP habría exigido un segundo set de ~9,8 M
        // de claves en memoria.
        $this->mirror->exec('DROP TABLE IF EXISTS imdb_episode_title_stage');
        $this->mirror->exec(
            'CREATE TABLE imdb_episode_title_stage (
               tconst        VARCHAR(12) PRIMARY KEY,
               primary_title VARCHAR(512) NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->mirror->exec(sprintf(
            "LOAD DATA INFILE %s INTO TABLE imdb_episode_title_stage
             FIELDS TERMINATED BY '\t' ESCAPED BY ''
             LINES TERMINATED BY '\n'
             (tconst, @primary_title)
             SET primary_title = NULLIF(@primary_title, '\\N')",
            $this->mirror->quote($episodeTitles)
        ));
        $this->mirror->exec(
            'UPDATE imdb_episode_new e
               JOIN imdb_episode_title_stage s ON s.tconst = e.tconst
                SET e.primary_title = s.primary_title'
        );
        $this->mirror->exec('DROP TABLE imdb_episode_title_stage');

        // The atomic part. Until this statement runs, search keeps answering
        // from the previous import.
        $this->mirror->exec(
            'RENAME TABLE
               imdb_title     TO imdb_title_old,     imdb_title_new     TO imdb_title,
               imdb_title_es  TO imdb_title_es_old,  imdb_title_es_new  TO imdb_title_es,
               imdb_episode   TO imdb_episode_old,   imdb_episode_new   TO imdb_episode'
        );
        $this->mirror->exec('DROP TABLE imdb_title_old, imdb_title_es_old, imdb_episode_old');

        foreach ([$basics, $ratings, $akas, $episode, $episodeTitles] as $tsv) {
            @unlink($tsv);
        }

        return [
            'titles'   => $titles,
            'akas'     => $akasRows,
            'episodes' => $episodes,
            'ratings'  => $ratingsRows,
        ];
    }

    private function recreateTwin(string $table): void
    {
        // The _old twin only exists if a previous run died between the RENAME
        // and the DROP; leaving it there would make this run's RENAME fail.
        $this->mirror->exec("DROP TABLE IF EXISTS {$table}_old");
        $this->mirror->exec("DROP TABLE IF EXISTS {$table}_new");
        $this->mirror->exec("CREATE TABLE {$table}_new LIKE {$table}");
    }

    private function countRows(string $table): int
    {
        return (int) $this->mirror->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function startImportRecord(): int
    {
        $stmt = $this->mirror->prepare(
            "INSERT INTO mirror_import (source, started_at, status) VALUES ('imdb', NOW(), 'running')"
        );
        $stmt->execute();

        return (int) $this->mirror->lastInsertId();
    }

    private function finishImportRecord(int $id, string $status, ?int $rows, ?string $version): void
    {
        $stmt = $this->mirror->prepare(
            'UPDATE mirror_import
                SET finished_at = NOW(), status = :status, rows_loaded = :rows, source_version = :version
              WHERE id = :id'
        );
        $stmt->execute([
            'status'  => $status,
            'rows'    => $rows,
            'version' => $version,
            'id'      => $id,
        ]);
    }

    private function assertWorkDirWritable(): void
    {
        if (!is_dir($this->workDir) || !is_writable($this->workDir)) {
            throw new RuntimeException(
                "{$this->workDir} is not writable. It must be a volume shared with the MySQL "
                . 'container, since LOAD DATA INFILE reads from the server\'s disk.'
            );
        }
    }

    /** mysqld runs as another user and still has to read the file we just wrote. */
    private function makeServerReadable(string $path): void
    {
        @chmod($path, 0644);
    }
}
