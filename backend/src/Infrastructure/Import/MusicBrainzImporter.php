<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

use PDO;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Imports the open MusicBrainz dumps into the library_mirror schema
 *
 * MusicBrainz is normalised to the bone: what Spotify hands over as one album
 * object lives here across nine tables. Doing those joins at search time would
 * be slower than calling Spotify, so this importer pays the cost once, cold,
 * and writes one denormalised row per album into mb_release_group.
 *
 * Three things about this class are measurements, not preferences. All three
 * were taken on the 2026-08-22 dump, 2.88 M albums:
 *
 *   1. **The columns are cut before MySQL sees them.** Loading the twelve
 *      tables whole takes 1907 s; loading only the columns used takes 543 s.
 *      cover_art alone goes from 657 MB and 8 M rows to 30 MB, because all the
 *      import needs is *whether* a release has a picture.
 *   2. **The FULLTEXT index is added last.** With it declared up front MySQL
 *      indexes row by row and the final INSERT takes 7563 s; without it, 344 s,
 *      and the ALTER that adds it afterwards costs 192 s.
 *   3. **The picking and the date composition happen in SQL**, not in PHP.
 *      Streaming 2.88 M groups through PHP to choose a release would undo the
 *      point of the previous two.
 *
 * Like ImdbImporter, everything lands in a `_new` twin and is swapped in with
 * RENAME TABLE, so search keeps answering from the previous import until the
 * last second. The temporary files go to a directory shared with the MySQL
 * container, because LOAD DATA INFILE reads from the *server's* disk.
 */
class MusicBrainzImporter
{
    private const BASE_URL = 'https://data.metabrainz.org/pub/musicbrainz/data/fullexport/';

    /** Only these two. Singles and bootlegs multiply the volume and nobody shelves them. */
    private const KEPT_TYPES = ['Album', 'EP'];

    /**
     * The dump files that are extracted, and the columns kept from each
     *
     * The indices are 1-based because that is what `cut -f` takes, and cutting
     * with a shell pipe is what keeps this out of PHP's memory. Verified
     * against the real dump: `medium` has 9 columns, not the 8 the MusicBrainz
     * schema documentation shows — the ninth is a gid added recently.
     *
     * @var array<string,array{cut:string,table:string,columns:string}>
     */
    private const FILES = [
        'release_group' => [
            'cut'     => '1,2,3,4,5',
            'table'   => 'release_group',
            'columns' => '(id, gid, name, artist_credit, @type) SET type = @type',
        ],
        'release' => [
            // El gid (campo 2) se conserva aunque la desnormalización no lo use:
            // es el MBID con el que se le piden las pistas a la API a la release
            // canónica. Sin él habría que preguntar por el release group entero,
            // y eso no termina en discos muy reeditados (medido: >2 min en
            // Dark Side of the Moon, 151 reediciones).
            'cut'     => '1,2,5,10',
            'table'   => 'mb_release',
            'columns' => "(id, gid, release_group, @barcode) SET barcode = NULLIF(@barcode, '')",
        ],
        'artist' => [
            'cut'     => '1,2',
            'table'   => 'artist',
            'columns' => '(id, gid)',
        ],
        'artist_credit' => [
            'cut'     => '1,2',
            'table'   => 'artist_credit',
            'columns' => '(id, name)',
        ],
        'artist_credit_name' => [
            'cut'     => '1,2,3',
            'table'   => 'artist_credit_name',
            'columns' => '(artist_credit, position, artist)',
        ],
        'release_group_primary_type' => [
            'cut'     => '1,2',
            'table'   => 'release_group_primary_type',
            'columns' => '(id, name)',
        ],
        'medium' => [
            'cut'     => '2,8',
            'table'   => 'medium',
            'columns' => '(`release`, @tc) SET track_count = @tc',
        ],
        'release_country' => [
            'cut'     => '1,3,4,5',
            'table'   => 'release_country',
            'columns' => '(`release`, @y, @m, @d) SET date_year = @y, date_month = @m, date_day = @d',
        ],
        'release_unknown_country' => [
            'cut'     => '1,2,3,4',
            'table'   => 'release_unknown_country',
            'columns' => '(`release`, @y, @m, @d) SET date_year = @y, date_month = @m, date_day = @d',
        ],
        'release_label' => [
            'cut'     => '2,3',
            'table'   => 'release_label',
            'columns' => '(`release`, @label) SET label = @label',
        ],
        'label' => [
            'cut'     => '1,3',
            'table'   => 'label',
            'columns' => '(id, name)',
        ],
    ];

    private PDO $mirror;
    private LoggerInterface $logger;
    private string $workDir;

    /** Version of the dump being imported, kept for mirror_import.source_version */
    private ?string $version = null;

    public function __construct(PDO $mirror, LoggerInterface $logger, string $workDir = '/var/lib/mysql-files')
    {
        $this->mirror  = $mirror;
        $this->logger  = $logger;
        $this->workDir = rtrim($workDir, '/') . '/musicbrainz';
    }

    // =========================================================================
    // Pure helpers — the part worth unit testing
    // =========================================================================

    /**
     * Pack a MusicBrainz date into one sortable integer
     *
     * A date in MusicBrainz is three nullable columns, and *any* of them can be
     * null. Packing them lets a plain MIN() pick the earliest release without a
     * self-join; 9999 in the year slot sorts the undated ones last, and a 0 in
     * the month or day slot means "not known to that precision".
     */
    public static function packDate(?int $year, ?int $month, ?int $day): int
    {
        return ($year ?? 9999) * 10000 + ($month ?? 0) * 100 + ($day ?? 0);
    }

    /**
     * Compose a date string with whatever precision the packed value carries
     *
     * Exactly what albums.release_date_precision already models: 'YYYY',
     * 'YYYY-MM' or 'YYYY-MM-DD'. Returns null for a group whose releases carry
     * no date at all.
     */
    public static function formatDate(int $packed): ?string
    {
        $year  = intdiv($packed, 10000);
        $month = intdiv($packed % 10000, 100);
        $day   = $packed % 100;

        if ($year === 9999) {
            return null;
        }
        if ($month === 0) {
            return sprintf('%04d', $year);
        }
        if ($day === 0) {
            return sprintf('%04d-%02d', $year, $month);
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * The precision of a composed date, in albums.release_date_precision terms
     */
    public static function datePrecision(int $packed): ?string
    {
        if (intdiv($packed, 10000) === 9999) {
            return null;
        }
        if (intdiv($packed % 10000, 100) === 0) {
            return 'year';
        }

        return $packed % 100 === 0 ? 'month' : 'day';
    }

    // =========================================================================
    // The import itself
    // =========================================================================

    /**
     * Download, narrow, load, denormalise and swap
     *
     * @return array{albums:int,groups:int,releases:int,seconds:int}
     */
    public function import(): array
    {
        $this->assertWorkDirWritable();

        $started  = time();
        $importId = $this->startImportRecord();

        try {
            $this->version = $this->latestVersion();
            $this->logger->info('musicbrainz import: starting', ['version' => $this->version]);

            $this->download('mbdump.tar.bz2');
            $this->download('mbdump-cover-art-archive.tar.bz2');

            $this->extract();
            $this->narrow();
            $this->createStaging();
            $this->load();

            $counts = $this->denormalise();
            $this->swap();
            $this->cleanUp();

            $this->finishImportRecord($importId, 'ok', $counts['albums'], $this->version);

            return $counts + ['seconds' => time() - $started];
        } catch (\Throwable $e) {
            $this->finishImportRecord($importId, 'failed', null, $this->version);
            $this->logger->error('musicbrainz import failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * The version string of the newest full export, e.g. '20260822-002831'
     *
     * Read from the LATEST file rather than guessed from the date: exports are
     * published twice a week but not on a fixed weekday.
     */
    public function latestVersion(): string
    {
        $latest = @file_get_contents(self::BASE_URL . 'LATEST');
        if ($latest === false || trim($latest) === '') {
            throw new RuntimeException('Could not read the MusicBrainz LATEST marker');
        }

        return trim($latest);
    }

    /** The version recorded by the last successful import, or null if there was none */
    public function importedVersion(): ?string
    {
        $stmt = $this->mirror->query(
            "SELECT source_version FROM mirror_import
              WHERE source = 'musicbrainz' AND status = 'ok'
              ORDER BY id DESC LIMIT 1"
        );
        $version = $stmt->fetchColumn();

        return $version === false || $version === null ? null : (string) $version;
    }

    /**
     * Download one archive, unless a complete copy is already on disk
     *
     * "Complete" is checked against the server's Content-Length, not against
     * "the file exists and is not empty". An interrupted download leaves a
     * perfectly readable 7.2 GB file that is simply not all of it, and treating
     * that as done means the next run fails inside tar with a corrupt-archive
     * error that says nothing about the real cause.
     */
    private function download(string $file): void
    {
        $url    = self::BASE_URL . $this->version . '/' . $file;
        $target = $this->workDir . '/' . $file;

        $expected = $this->remoteSize($url);

        if (is_file($target) && $expected !== null && filesize($target) === $expected) {
            $this->logger->info('musicbrainz import: already downloaded', ['file' => $file]);

            return;
        }
        if (is_file($target)) {
            $this->logger->info('musicbrainz import: discarding partial download', [
                'file'     => $file,
                'on_disk'  => filesize($target),
                'expected' => $expected,
            ]);
            @unlink($target);
        }

        $this->logger->info('musicbrainz import: downloading', ['file' => $file, 'bytes' => $expected]);

        $in = @fopen($url, 'rb');
        if ($in === false) {
            throw new RuntimeException("Could not download {$url}");
        }
        $out = fopen($target, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException("Could not write {$target}");
        }

        $copied = stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        // Se borra lo incompleto en el momento, no en la siguiente ejecución:
        // dejar 7 GB a medias en el volumen compartido con MySQL es peor que
        // fallar aquí con un mensaje claro.
        if ($expected !== null && $copied !== $expected) {
            @unlink($target);
            throw new RuntimeException(
                "Truncated download of {$file}: got {$copied} bytes of {$expected}"
            );
        }
    }

    /**
     * The Content-Length the server reports, or null if it does not say
     *
     * @return int|null
     */
    private function remoteSize(string $url): ?int
    {
        $headers = @get_headers($url, true);
        if ($headers === false || !isset($headers['Content-Length'])) {
            return null;
        }

        $length = $headers['Content-Length'];

        // Con redirecciones, get_headers() devuelve un array por salto.
        return (int) (is_array($length) ? end($length) : $length);
    }

    /**
     * Pull the twelve tables out of the two archives
     *
     * Selective extraction, not a full unpack: mbdump.tar.bz2 holds the whole
     * database and only these files are ever read.
     */
    private function extract(): void
    {
        $members = array_map(
            static fn (string $name): string => 'mbdump/' . $name,
            array_keys(self::FILES)
        );

        // Como la descarga: si un intento anterior ya extrajo, no se repiten
        // diez minutos de bzip2. cleanUp() los borra al terminar bien.
        if (is_file($this->workDir . '/mbdump/release_group')
            && is_file($this->workDir . '/mbdump/cover_art_archive.cover_art')) {
            $this->logger->info('musicbrainz import: already extracted');

            return;
        }

        $this->logger->info('musicbrainz import: extracting');
        $this->run(sprintf(
            'tar -xjf %s -C %s %s',
            escapeshellarg($this->workDir . '/mbdump.tar.bz2'),
            escapeshellarg($this->workDir),
            implode(' ', array_map('escapeshellarg', $members))
        ));
        $this->run(sprintf(
            'tar -xjf %s -C %s %s',
            escapeshellarg($this->workDir . '/mbdump-cover-art-archive.tar.bz2'),
            escapeshellarg($this->workDir),
            escapeshellarg('mbdump/cover_art_archive.cover_art')
        ));
    }

    /**
     * Cut every file down to the columns that are actually used
     *
     * `cut` is safe on this format precisely because it is COPY: a tab inside a
     * value arrives escaped as \t, so the field count per line never varies.
     *
     * cover_art gets a `sort -u` instead of a cut, because has_cover_art only
     * asks whether a release has a picture — 8 M rows collapse to the distinct
     * releases, and 657 MB to 30 MB.
     */
    private function narrow(): void
    {
        $dump   = $this->workDir . '/mbdump';
        $narrow = $this->workDir . '/narrow';
        if (!is_dir($narrow) && !mkdir($narrow, 0755, true) && !is_dir($narrow)) {
            throw new RuntimeException("Could not create {$narrow}");
        }

        $this->logger->info('musicbrainz import: narrowing');

        foreach (self::FILES as $name => $spec) {
            $this->run(sprintf(
                'cut -f%s %s > %s',
                $spec['cut'],
                escapeshellarg($dump . '/' . $name),
                escapeshellarg($narrow . '/' . $name)
            ));
        }

        $this->run(sprintf(
            'cut -f2 %s | sort -u -S 256M > %s',
            escapeshellarg($dump . '/cover_art_archive.cover_art'),
            escapeshellarg($narrow . '/cover_art')
        ));

        // mysqld runs as another user and still has to read what we just wrote.
        $this->run(sprintf('chmod 644 %s/*', escapeshellarg($narrow)));
    }

    private function createStaging(): void
    {
        // `release` is reserved enough to be a nuisance in every statement it
        // appears in, so the staging twin is called mb_release.
        $tables = [
            // VARCHAR(2000) y no (512): medido sobre el dump real, el nombre
            // más largo de un release group tiene 1125 caracteres y el de un
            // artist_credit 1726. El staging los acepta enteros y es el INSERT
            // final el que trunca a lo que cabe, a la vista y no en silencio.
            'release_group' => 'id INT PRIMARY KEY, gid CHAR(36) NOT NULL, name VARCHAR(2000) NOT NULL,
                                artist_credit INT NOT NULL, type INT NULL, KEY idx_type (type)',
            'mb_release'    => 'id INT PRIMARY KEY, gid CHAR(36) NOT NULL, release_group INT NOT NULL,
                                barcode VARCHAR(255) NULL, KEY idx_rg (release_group)',
            'artist'        => 'id INT PRIMARY KEY, gid CHAR(36) NOT NULL',
            'artist_credit' => 'id INT PRIMARY KEY, name VARCHAR(2000) NOT NULL',
            'artist_credit_name' => 'artist_credit INT NOT NULL, position SMALLINT NOT NULL,
                                     artist INT NOT NULL, PRIMARY KEY (artist_credit, position)',
            'release_group_primary_type' => 'id INT PRIMARY KEY, name VARCHAR(64) NOT NULL',
            'medium'        => '`release` INT NOT NULL, track_count INT NULL, KEY idx_release (`release`)',
            'release_country' => '`release` INT NOT NULL, date_year SMALLINT NULL, date_month TINYINT NULL,
                                  date_day TINYINT NULL, KEY idx_release (`release`)',
            'release_unknown_country' => '`release` INT NOT NULL, date_year SMALLINT NULL,
                                          date_month TINYINT NULL, date_day TINYINT NULL,
                                          KEY idx_release (`release`)',
            'release_label' => '`release` INT NOT NULL, label INT NULL, KEY idx_release (`release`)',
            'label'         => 'id INT PRIMARY KEY, name VARCHAR(2000) NOT NULL',
            // Already deduplicated by the narrowing, so the PK costs nothing.
            'cover_art'     => '`release` INT PRIMARY KEY',
        ];

        foreach ($tables as $table => $definition) {
            $this->mirror->exec("DROP TABLE IF EXISTS mb_stage_{$table}");
            $this->mirror->exec("CREATE TABLE mb_stage_{$table} ({$definition}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    /**
     * LOAD DATA the narrowed files into staging
     *
     * The escaping is the one thing to get right here, and it is *not* the one
     * ImdbImporter uses. IMDb writes a literal \N for null and escapes nothing
     * else, which is why that importer passes ESCAPED BY ''. These dumps are
     * PostgreSQL COPY for real: a value containing a tab, a newline or a
     * backslash arrives escaped, and reading them with ESCAPED BY '' would
     * split those rows in two. MySQL's default escape character handles them,
     * and turns \N into a real NULL along the way — no NULLIF needed.
     */
    private function load(): void
    {
        $narrow = $this->workDir . '/narrow';

        foreach (self::FILES as $name => $spec) {
            $this->logger->info('musicbrainz import: loading', ['file' => $name]);
            $this->mirror->exec(sprintf(
                "LOAD DATA INFILE %s INTO TABLE mb_stage_%s
                 FIELDS TERMINATED BY '\\t' LINES TERMINATED BY '\\n' %s",
                $this->mirror->quote($narrow . '/' . $name),
                $spec['table'],
                $spec['columns']
            ));
        }

        $this->mirror->exec(sprintf(
            "LOAD DATA INFILE %s INTO TABLE mb_stage_cover_art
             FIELDS TERMINATED BY '\\t' LINES TERMINATED BY '\\n' (`release`)",
            $this->mirror->quote($narrow . '/cover_art')
        ));
    }

    /**
     * Turn the twelve staging tables into one row per album
     *
     * @return array{albums:int,groups:int,releases:int}
     */
    private function denormalise(): array
    {
        $this->logger->info('musicbrainz import: denormalising');

        $types = implode(', ', array_map(
            fn (string $t): string => $this->mirror->quote($t),
            self::KEPT_TYPES
        ));

        $this->stage('mb_stage_keep',
            'rg_id INT PRIMARY KEY, gid CHAR(36) NOT NULL, name VARCHAR(2000) NOT NULL,
             ac_id INT NOT NULL, primary_type VARCHAR(32) NOT NULL, KEY idx_ac (ac_id)',
            "SELECT rg.id, rg.gid, rg.name, rg.artist_credit, t.name
               FROM mb_stage_release_group rg
               JOIN mb_stage_release_group_primary_type t ON t.id = rg.type
              WHERE t.name IN ({$types})"
        );

        // Releases with no country row at all still exist, hence the LEFT JOIN
        // and the 99990000 default: without it they would vanish from their
        // own release group.
        $this->stage('mb_stage_rel_date',
            'release_id INT PRIMARY KEY, date_key INT NOT NULL',
            'SELECT `release`,
                    MIN(COALESCE(date_year, 9999) * 10000
                        + COALESCE(date_month, 0) * 100
                        + COALESCE(date_day, 0))
               FROM (SELECT `release`, date_year, date_month, date_day FROM mb_stage_release_country
                     UNION ALL
                     SELECT `release`, date_year, date_month, date_day FROM mb_stage_release_unknown_country) d
              GROUP BY `release`'
        );

        $this->stage('mb_stage_rel',
            'release_id INT PRIMARY KEY, rg_id INT NOT NULL, date_key INT NOT NULL,
             barcode VARCHAR(255) NULL, release_gid CHAR(36) NOT NULL,
             KEY idx_rg (rg_id, date_key, release_id)',
            'SELECT r.id, r.release_group, COALESCE(d.date_key, 99990000), r.barcode, r.gid
               FROM mb_stage_mb_release r
               JOIN mb_stage_keep k ON k.rg_id = r.release_group
               LEFT JOIN mb_stage_rel_date d ON d.release_id = r.id'
        );

        // The canonical release of a group is its oldest, and ties break on the
        // lowest id. Taking whatever the join returned first would give a
        // different label and barcode on every import, and chasing that down
        // costs a day.
        $this->stage('mb_stage_canon',
            'rg_id INT PRIMARY KEY, date_key INT NOT NULL, release_id INT NOT NULL',
            'SELECT rg_id,
                    MIN(date_key * 10000000000 + release_id) DIV 10000000000,
                    MIN(date_key * 10000000000 + release_id) MOD 10000000000
               FROM mb_stage_rel GROUP BY rg_id'
        );

        // Barcode and label come from the oldest release that *has* one, which
        // is not necessarily the canonical release: a 1967 original often
        // predates barcodes entirely.
        $this->stage('mb_stage_barcode',
            'rg_id INT PRIMARY KEY, barcode VARCHAR(255)',
            'SELECT p.rg_id, r.barcode
               FROM (SELECT rg_id, MIN(date_key * 10000000000 + release_id) MOD 10000000000 AS release_id
                       FROM mb_stage_rel WHERE barcode IS NOT NULL GROUP BY rg_id) p
               JOIN mb_stage_rel r ON r.release_id = p.release_id'
        );

        $this->stage('mb_stage_rg_label',
            'rg_id INT PRIMARY KEY, label VARCHAR(2000)',
            'SELECT p.rg_id, l.name
               FROM (SELECT rr.rg_id,
                            MIN(rr.date_key * 10000000000 + rr.release_id) MOD 10000000000 AS release_id
                       FROM mb_stage_rel rr
                       JOIN mb_stage_release_label rl
                         ON rl.`release` = rr.release_id AND rl.label IS NOT NULL
                      GROUP BY rr.rg_id) p
               JOIN (SELECT `release`, MIN(label) AS label_id FROM mb_stage_release_label
                      WHERE label IS NOT NULL GROUP BY `release`) rl ON rl.`release` = p.release_id
               JOIN mb_stage_label l ON l.id = rl.label_id'
        );

        $this->stage('mb_stage_tracks',
            'rg_id INT PRIMARY KEY, track_count INT NULL',
            'SELECT c.rg_id, SUM(m.track_count)
               FROM mb_stage_canon c JOIN mb_stage_medium m ON m.`release` = c.release_id
              GROUP BY c.rg_id'
        );

        // Any release of the group having a picture is enough: Cover Art
        // Archive is addressed by release, and the front cover of a reissue is
        // the same album's cover.
        $this->stage('mb_stage_cover',
            'rg_id INT PRIMARY KEY',
            'SELECT DISTINCT rr.rg_id
               FROM mb_stage_rel rr JOIN mb_stage_cover_art c ON c.`release` = rr.release_id'
        );

        // The ranking signal, free from a GROUP BY the import already needs.
        $this->stage('mb_stage_relcount',
            'rg_id INT PRIMARY KEY, n INT NOT NULL',
            'SELECT rg_id, COUNT(*) FROM mb_stage_rel GROUP BY rg_id'
        );

        $this->stage('mb_stage_rg_artist',
            'ac_id INT PRIMARY KEY, artist_gid CHAR(36)',
            'SELECT acn.artist_credit, a.gid
               FROM mb_stage_artist_credit_name acn
               JOIN mb_stage_artist a ON a.id = acn.artist
              WHERE acn.position = 0'
        );

        // The twin is created bare and indexed afterwards — see the class
        // docblock. A CREATE TABLE ... LIKE would copy the FULLTEXT and cost
        // two hours.
        $this->mirror->exec('DROP TABLE IF EXISTS mb_release_group_new');
        $this->mirror->exec(
            'CREATE TABLE mb_release_group_new (
               gid CHAR(36) PRIMARY KEY, canonical_release_gid CHAR(36) NULL,
               name VARCHAR(512) NOT NULL,
               artist_credit VARCHAR(512) NOT NULL, artist_gid CHAR(36) NULL,
               primary_type VARCHAR(32) NULL, first_release_year SMALLINT UNSIGNED NULL,
               first_release_date VARCHAR(10) NULL, barcode VARCHAR(20) NULL,
               label VARCHAR(255) NULL, track_count SMALLINT UNSIGNED NULL,
               has_cover_art TINYINT(1) NOT NULL DEFAULT 0,
               release_count SMALLINT UNSIGNED NOT NULL DEFAULT 0
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->mirror->exec(
            "INSERT INTO mb_release_group_new
               (gid, canonical_release_gid, name, artist_credit, artist_gid, primary_type,
                first_release_year, first_release_date, barcode, label, track_count,
                has_cover_art, release_count)
             SELECT k.gid, cr.release_gid, LEFT(k.name, 512), LEFT(ac.name, 512), am.artist_gid,
                    k.primary_type,
                    CASE WHEN d.date_key DIV 10000 = 9999 THEN NULL ELSE d.date_key DIV 10000 END,
                    CASE
                      WHEN d.date_key DIV 10000 = 9999 THEN NULL
                      WHEN (d.date_key MOD 10000) DIV 100 = 0 THEN LPAD(d.date_key DIV 10000, 4, '0')
                      WHEN d.date_key MOD 100 = 0
                        THEN CONCAT(LPAD(d.date_key DIV 10000, 4, '0'), '-',
                                    LPAD((d.date_key MOD 10000) DIV 100, 2, '0'))
                      ELSE CONCAT(LPAD(d.date_key DIV 10000, 4, '0'), '-',
                                  LPAD((d.date_key MOD 10000) DIV 100, 2, '0'), '-',
                                  LPAD(d.date_key MOD 100, 2, '0'))
                    END,
                    LEFT(b.barcode, 20), LEFT(lb.label, 255), t.track_count,
                    CASE WHEN cv.rg_id IS NULL THEN 0 ELSE 1 END,
                    LEAST(COALESCE(rc.n, 0), 65535)
               FROM mb_stage_keep k
               JOIN mb_stage_artist_credit ac ON ac.id = k.ac_id
               LEFT JOIN mb_stage_rg_artist am ON am.ac_id = k.ac_id
               LEFT JOIN mb_stage_canon    d  ON d.rg_id   = k.rg_id
               LEFT JOIN mb_stage_barcode  b  ON b.rg_id   = k.rg_id
               LEFT JOIN mb_stage_rg_label lb ON lb.rg_id  = k.rg_id
               LEFT JOIN mb_stage_tracks   t  ON t.rg_id   = k.rg_id
               LEFT JOIN mb_stage_cover    cv ON cv.rg_id  = k.rg_id
               LEFT JOIN mb_stage_relcount rc ON rc.rg_id  = k.rg_id
               -- La release canónica otra vez, ahora por su MBID: mb_stage_canon
               -- guarda su id interno y el gid vive en mb_stage_rel, que tiene
               -- ese id como clave primaria.
               LEFT JOIN mb_stage_rel      cr ON cr.release_id = d.release_id"
        );

        $this->logger->info('musicbrainz import: indexing');
        $this->mirror->exec(
            'ALTER TABLE mb_release_group_new ADD FULLTEXT KEY ft_rg (name, artist_credit)'
        );
        $this->mirror->exec('ALTER TABLE mb_release_group_new ADD KEY idx_artist (artist_gid)');
        $this->mirror->exec('ALTER TABLE mb_release_group_new ADD KEY idx_year (first_release_year)');
        $this->mirror->exec('ALTER TABLE mb_release_group_new ADD KEY idx_release_count (release_count)');

        return [
            'albums'   => $this->countRows('mb_release_group_new'),
            'groups'   => $this->countRows('mb_stage_keep'),
            'releases' => $this->countRows('mb_stage_rel'),
        ];
    }

    /** The atomic part: until this runs, search answers from the previous import. */
    private function swap(): void
    {
        $this->mirror->exec('DROP TABLE IF EXISTS mb_release_group_old');
        $this->mirror->exec(
            'RENAME TABLE mb_release_group     TO mb_release_group_old,
                          mb_release_group_new TO mb_release_group'
        );
        $this->mirror->exec('DROP TABLE mb_release_group_old');
    }

    private function cleanUp(): void
    {
        foreach ($this->mirror->query("SHOW TABLES LIKE 'mb_stage_%'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $this->mirror->exec("DROP TABLE IF EXISTS `{$table}`");
        }

        $this->run(sprintf('rm -rf %s %s',
            escapeshellarg($this->workDir . '/mbdump'),
            escapeshellarg($this->workDir . '/narrow')
        ));
        @unlink($this->workDir . '/mbdump.tar.bz2');
        @unlink($this->workDir . '/mbdump-cover-art-archive.tar.bz2');
    }

    /**
     * Build one staging table from a SELECT, dropping any leftover twin first
     *
     * Cuidado con el nombre: esto hace DROP antes del SELECT, así que una tabla
     * derivada NO puede llamarse como una del dump que su propio SELECT lee.
     * De ahí el prefijo rg_ en mb_stage_rg_label y mb_stage_rg_artist, que
     * derivan de mb_stage_label y mb_stage_artist respectivamente.
     */
    private function stage(string $table, string $definition, string $select): void
    {
        $this->mirror->exec("DROP TABLE IF EXISTS {$table}");
        $this->mirror->exec("CREATE TABLE {$table} ({$definition}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->mirror->exec("INSERT INTO {$table} {$select}");
    }

    private function run(string $command): void
    {
        exec($command . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException(
                "Command failed ({$status}): {$command}\n" . implode("\n", array_slice($output, 0, 5))
            );
        }
    }

    private function countRows(string $table): int
    {
        return (int) $this->mirror->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function startImportRecord(): int
    {
        $this->mirror->prepare(
            "INSERT INTO mirror_import (source, started_at, status)
             VALUES ('musicbrainz', NOW(), 'running')"
        )->execute();

        return (int) $this->mirror->lastInsertId();
    }

    private function finishImportRecord(int $id, string $status, ?int $rows, ?string $version): void
    {
        $this->mirror->prepare(
            'UPDATE mirror_import
                SET finished_at = NOW(), status = :status, rows_loaded = :rows, source_version = :version
              WHERE id = :id'
        )->execute([
            'status'  => $status,
            'rows'    => $rows,
            'version' => $version,
            'id'      => $id,
        ]);
    }

    private function assertWorkDirWritable(): void
    {
        $parent = dirname($this->workDir);
        if (!is_dir($parent) || !is_writable($parent)) {
            throw new RuntimeException(
                "{$parent} is not writable. It must be a volume shared with the MySQL "
                . 'container, since LOAD DATA INFILE reads from the server\'s disk.'
            );
        }
        if (!is_dir($this->workDir) && !mkdir($this->workDir, 0755, true) && !is_dir($this->workDir)) {
            throw new RuntimeException("Could not create {$this->workDir}");
        }
    }
}
