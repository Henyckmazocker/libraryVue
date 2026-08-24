<?php

declare(strict_types=1);

namespace App\Domain\Repository\Catalog;

/**
 * A source of album catalog data
 *
 * The same seam as MovieCatalogInterface, for the same reason and with one
 * extra: films moved to a local mirror because it was cheaper, albums moved
 * because storing Spotify's catalog is something Spotify's terms forbid.
 *
 * There are three implementations: one reading the local MusicBrainz mirror,
 * one wrapping Spotify, and a decorator that tries local first.
 *
 * Every method returns data in **Spotify's shape** — the nested `artists[]`,
 * `images[]` and `external_urls` objects the frontend already destructures in
 * `AlbumSearch.vue`, `AlbumDetailView.vue` and `mediaRegistry.js`. Not because
 * it is a good shape, but because changing it is a plan of its own. The same
 * debt the film catalog carries with OMDb's PascalCase keys.
 */
interface AlbumCatalogInterface
{
    /**
     * Search albums by name or artist
     *
     * @param string $query Free text, as typed by the user
     * @return array<int,array<string,mixed>> Results in Spotify's album shape:
     *         id, name, artists[], images[], release_date, album_type,
     *         total_tracks, label, external_urls
     */
    public function search(string $query, int $limit = 20): array;

    /**
     * Full record for one album
     *
     * @param string $id A MusicBrainz MBID, or a Spotify base62 id for anything
     *                   saved before the mirror existed
     * @return array<string,mixed>|null Null when the album does not exist
     */
    public function findById(string $id): ?array;

    /**
     * The album carrying this barcode, if the catalog knows it
     *
     * The bridge that keeps Spotify content out of the database: an album
     * coming back from the Spotify fallback carries a UPC, and a UPC that
     * matches the mirror means the album *is* in MusicBrainz — it just was not
     * found by name. Saving the MusicBrainz record instead means nothing of
     * Spotify's is persisted.
     *
     * @return array<string,mixed>|null
     */
    public function findByBarcode(string $barcode): ?array;
}
