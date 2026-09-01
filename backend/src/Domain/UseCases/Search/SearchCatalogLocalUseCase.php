<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Search;

use App\Domain\Repository\Catalog\AlbumCatalogInterface;
use App\Domain\Repository\Catalog\MovieCatalogInterface;

/**
 * La mitad rápida del buscador general: los tres medios que salen del mirror.
 *
 * Películas, series y álbumes se sirven de MySQL local —`FallbackMovieCatalog`
 * va solo a local en su `search()` (`FallbackMovieCatalog.php:46-49`) y
 * `MySqlAlbumCatalog` es el mirror de MusicBrainz—, así que responden en
 * milisegundos. Están juntos aquí, y no repartidos por medio, **porque el corte
 * es por velocidad y no por dominio**: es lo que permite pintar media pantalla
 * antes de que la red conteste, con dos peticiones por búsqueda en vez de cinco.
 *
 * No degrada ni declara `stale`: lo que sirve un dump no puede estar rancio.
 */
final class SearchCatalogLocalUseCase
{
    public function __construct(
        private readonly MovieCatalogInterface $movies,
        private readonly AlbumCatalogInterface $albums
    ) {
    }

    /**
     * @return array{movie: array<int, array>, series: array<int, array>, album: array<int, array>}
     */
    public function execute(string $query, int $limit = 20): array
    {
        // Dos consultas tipadas y no una repartida después: el catálogo ya sabe
        // filtrar por tipo (`MySqlMovieCatalog::OMDB_TO_TYPES`), y pedir las dos
        // por separado es lo que evita que una búsqueda con muchas películas
        // devuelva cero series solo por haber llenado el cupo. Son dos lecturas
        // al mirror local, de ~40 ms cada una.
        return [
            'movie'  => $this->movies->search($query, 'movie', $limit),
            'series' => $this->movies->search($query, 'series', $limit),
            'album'  => $this->albums->search($query, $limit),
        ];
    }
}
