<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Catalog;

use App\Domain\Repository\Catalog\AlbumCatalogInterface;
use PDO;

/**
 * Catalog served from the local MusicBrainz mirror (library_mirror)
 *
 * Answers without network and without keeping a copy of anyone's proprietary
 * catalog: MusicBrainz publishes its core under CC0.
 *
 * What it cannot give is Spotify's `popularity` and `duration_ms`, which have
 * no equivalent in MusicBrainz and come back null. Both are painted under a
 * `v-if` in the frontend (`AlbumDetailView.vue:54`, `AlbumCarouselItem.vue:80`)
 * and nothing orders by them, so they degrade rather than break.
 */
class MySqlAlbumCatalog implements AlbumCatalogInterface
{
    /**
     * Cover Art Archive serves the front cover of a release group by MBID
     *
     * No API call and no key: this URL is a redirect to the image. It is only
     * emitted when has_cover_art says there is one, so it never 404s in the UI.
     */
    private const COVER_URL = 'https://coverartarchive.org/release-group/%s/front-500';

    private PDO $mirror;

    public function __construct(PDO $mirror)
    {
        $this->mirror = $mirror;
    }

    public function search(string $query, int $limit = 20): array
    {
        $boolean = BooleanQueryBuilder::build($query);
        if ($boolean === '') {
            return [];
        }

        // ORDER BY release_count DESC is what makes "kind of blue" return Miles
        // Davis. MusicBrainz publishes no popularity signal at all, so the
        // number of times a release group was reissued stands in for one —
        // measured: without it the right album lands 3rd, behind a reggae
        // tribute. It is the same job num_votes does in MySqlMovieCatalog.
        $stmt = $this->mirror->prepare(
            'SELECT gid, name, artist_credit, artist_gid, primary_type,
                    first_release_date, label, track_count, has_cover_art
             FROM mb_release_group
             WHERE MATCH(name, artist_credit) AGAINST (:q IN BOOLEAN MODE)
             ORDER BY release_count DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':q', $boolean, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn (array $row): array => $this->toSpotifyShape($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->mirror->prepare(
            'SELECT gid, name, artist_credit, artist_gid, primary_type,
                    first_release_date, label, track_count, has_cover_art
             FROM mb_release_group WHERE gid = :gid'
        );
        $stmt->execute([':gid' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->toSpotifyShape($row);
    }

    /**
     * Las pistas cacheadas de un álbum, en la forma que la UI ya destructura
     *
     * Devuelve `[]` tanto si el álbum no tiene pistas como si aún no se han
     * pedido: distinguir los dos casos es cosa de `mb_track_fetch`, y de eso se
     * ocupa quien decide si encolar el fetch, no este método.
     *
     * Las claves son las de Spotify —`track_number`, `name`, `duration_ms`,
     * `id`— porque `EditItemModal.vue:218-222` lee `track.track_number` y
     * `track.name`, y `mediaRegistry.js:1035` suma `duration_ms`. Cambiarlas
     * obligaría a tocar el frontend, que este plan no toca.
     *
     * @return array<int,array<string,mixed>>
     */
    public function tracksFor(string $releaseGroupGid): array
    {
        $stmt = $this->mirror->prepare(
            'SELECT position, number, title, length_ms, recording_gid
             FROM mb_track WHERE release_group_gid = :gid ORDER BY position'
        );
        $stmt->execute([':gid' => $releaseGroupGid]);

        return array_map(
            static fn (array $row): array => [
                'id'           => $row['recording_gid'],
                'name'         => $row['title'],
                // El número impreso si lo hay ('A1' en un vinilo), y si no, la
                // posición: la UI lo pinta delante del título y un hueco ahí se
                // ve raro.
                'track_number' => $row['number'] ?? (string) $row['position'],
                'duration_ms'  => $row['length_ms'] !== null ? (int) $row['length_ms'] : null,
                'disc_number'  => 1,
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * El álbum que lleva este código de barras, solo si no hay duda
     *
     * Dos guardas, y las dos salieron de probarlo con datos reales:
     *
     *   1. **El barcode tiene que ser plausible.** MusicBrainz acepta lo que le
     *      pongan, y hay filas con `000000000000`. Un UPC/EAN de verdad son 8
     *      dígitos o más y no es todo ceros.
     *   2. **Tiene que apuntar a UN solo release group.** Medido sobre el dump:
     *      hay un barcode compartido por **98** grupos distintos. Coger el más
     *      reeditado de esos 98 metería en la biblioteca del usuario un álbum
     *      que no es el suyo — peor que el problema que este método resuelve.
     *
     * Ante la duda no se resuelve: el álbum se guarda como Spotify, marcado, y
     * caduca. Equivocarse de disco no se deshace solo.
     */
    public function findByBarcode(string $barcode): ?array
    {
        $barcode = trim($barcode);

        if (!preg_match('/^[0-9]{8,}$/', $barcode) || (int) $barcode === 0) {
            return null;
        }

        $stmt = $this->mirror->prepare(
            'SELECT gid, name, artist_credit, artist_gid, primary_type,
                    first_release_date, label, track_count, has_cover_art
             FROM mb_release_group WHERE barcode = :barcode
             LIMIT 2'
        );
        $stmt->execute([':barcode' => $barcode]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // LIMIT 2 y no LIMIT 1: basta con saber si hay más de uno.
        return count($rows) === 1 ? $this->toSpotifyShape($rows[0]) : null;
    }

    /**
     * One mirror row in the shape the frontend already destructures
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function toSpotifyShape(array $row): array
    {
        $cover = $row['has_cover_art']
            ? sprintf(self::COVER_URL, $row['gid'])
            : null;

        return [
            'id'   => $row['gid'],
            'name' => $row['name'],
            // artists[] is an array because AlbumSearch.vue:95 reads
            // `result.artists?.[0]?.name`. The mirror denormalises the whole
            // credit ("Simon & Garfunkel") into one string, so there is exactly
            // one entry and it carries the joined name.
            'artists' => [[
                'id'   => $row['artist_gid'],
                'name' => $row['artist_credit'],
            ]],
            'images' => $cover !== null ? [['url' => $cover, 'height' => 500, 'width' => 500]] : [],
            'release_date'           => $row['first_release_date'],
            'release_date_precision' => $this->precisionOf($row['first_release_date']),
            'album_type'             => strtolower((string) ($row['primary_type'] ?? 'album')),
            'total_tracks'           => $row['track_count'] !== null ? (int) $row['track_count'] : null,
            'label'                  => $row['label'],
            // MusicBrainz has no equivalent of either. See the class docblock.
            'popularity'  => null,
            'duration_ms' => null,
            // The album's page on MusicBrainz, so a user can go and fix the data
            // at the source — which they cannot do with Spotify.
            'external_urls' => ['musicbrainz' => 'https://musicbrainz.org/release-group/' . $row['gid']],
            'upc'           => null,
        ];
    }

    /**
     * The precision albums.release_date_precision expects, read off the string
     *
     * The importer already composed 'YYYY', 'YYYY-MM' or 'YYYY-MM-DD', so the
     * precision is just how long it is.
     */
    private function precisionOf(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return match (strlen($date)) {
            4       => 'year',
            7       => 'month',
            default => 'day',
        };
    }
}
