<?php

declare(strict_types=1);

namespace App\Domain\Repository\Catalog;

/**
 * A source of film and series catalog data
 *
 * The seam that lets the app stop depending on a network call to search. There
 * are three implementations: one reading the local IMDb mirror, one wrapping
 * TMDB, and a decorator that tries local first and only then the network.
 *
 * Every method returns data in **OMDb's shape** — PascalCase keys, everything a
 * string. Not because it is a good shape, but because the frontend consumes
 * those keys in 5 files and 11 fields, and normalising them is a plan of its
 * own. See "Contrato de respuesta" in the Mirror Local de Catálogos plan.
 */
interface MovieCatalogInterface
{
    /**
     * Search titles by name
     *
     * @param string $query Free text, as typed by the user
     * @param string $type  'movie' | 'series' | '' for both
     * @return array<int,array<string,string|null>> Results in OMDb shape:
     *         Title, Year, imdbID, Type, Poster
     */
    public function search(string $query, string $type = '', int $limit = 20): array;

    /**
     * Full record for one title
     *
     * @return array<string,string|null>|null OMDb shape: Title, Year, Runtime,
     *         Genre, Director, Plot, Poster, imdbRating, imdbID, Type,
     *         totalSeasons. Null when the title does not exist.
     */
    public function findByImdbId(string $imdbId): ?array;

    /**
     * Episodes of one season of a series
     *
     * @return array<int,array<string,string|null>> OMDb shape: Title, Episode,
     *         imdbID, imdbRating, Released
     */
    public function seasonEpisodes(string $imdbId, int $season): array;
}
