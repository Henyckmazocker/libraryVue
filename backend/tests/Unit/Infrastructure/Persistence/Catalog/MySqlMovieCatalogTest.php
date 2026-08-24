<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Catalog;

use App\Infrastructure\Persistence\Catalog\MySqlMovieCatalog;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El mapeo de fila del mirror a la forma de OMDb.
 *
 * Con dobles de PDO: lo que importa aquí no es el SQL —eso se comprueba contra
 * el mirror real— sino que las claves y los tipos son los que consume el
 * frontend, que lee `imdbID` 74 veces y `totalSeasons` 27.
 */
class MySqlMovieCatalogTest extends TestCase
{
    /** Un PDO cuyo prepare() devuelve siempre estas filas */
    private function pdoQueDevuelve(array $filas, ?array $columna = null): PDO
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($filas);
        $stmt->method('fetch')->willReturn($filas[0] ?? false);
        $stmt->method('fetchColumn')->willReturn($columna[0] ?? false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    // =========================================================================
    // search
    // =========================================================================

    #[Test]
    public function maps_a_row_to_the_omdb_search_shape(): void
    {
        $catalog = new MySqlMovieCatalog($this->pdoQueDevuelve([[
            'tconst'         => 'tt0095016',
            'primary_title'  => 'Die Hard',
            'original_title' => 'Die Hard',
            'start_year'     => 1988,
            'title_type'     => 'movie',
        ]]));

        $this->assertSame([[
            'Title'  => 'Die Hard',
            'Year'   => '1988',
            'imdbID' => 'tt0095016',
            'Type'   => 'movie',
            'Poster' => null,
        ]], $catalog->search('jungla de cristal'));
    }

    #[Test]
    public function does_not_touch_the_database_when_nothing_is_searchable(): void
    {
        // Una consulta que se queda sin tokens no llega a la BD: un MATCH
        // contra cadena vacía es un escaneo que no encuentra nada.
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('prepare');

        $this->assertSame([], (new MySqlMovieCatalog($pdo))->search('el de la'));
    }

    #[Test]
    public function reports_a_series_as_series_and_a_short_as_movie(): void
    {
        // OMDb solo dice 'movie' o 'series', así que todo lo que tiene forma de
        // película —cortos, directos a vídeo, especiales— cae en 'movie'.
        $tipos = [
            'tvSeries'     => 'series',
            'tvMiniSeries' => 'series',
            'tvPilot'      => 'series',
            'movie'        => 'movie',
            'tvMovie'      => 'movie',
            'short'        => 'movie',
            'tvShort'      => 'movie',
            'video'        => 'movie',
            'tvSpecial'    => 'movie',
        ];

        foreach ($tipos as $imdbType => $esperado) {
            $catalog = new MySqlMovieCatalog($this->pdoQueDevuelve([[
                'tconst'         => 'tt0000001',
                'primary_title'  => 'Lo que sea',
                'original_title' => null,
                'start_year'     => 2000,
                'title_type'     => $imdbType,
            ]]));

            $this->assertSame($esperado, $catalog->search('lo que sea')[0]['Type'], $imdbType);
        }
    }

    #[Test]
    public function keeps_a_missing_year_as_null_and_not_as_zero(): void
    {
        $catalog = new MySqlMovieCatalog($this->pdoQueDevuelve([[
            'tconst'         => 'tt0000001',
            'primary_title'  => 'Sin año',
            'original_title' => null,
            'start_year'     => null,
            'title_type'     => 'movie',
        ]]));

        $this->assertNull($catalog->search('sin año')[0]['Year']);
    }

    // =========================================================================
    // findByImdbId
    // =========================================================================

    #[Test]
    public function maps_a_row_to_the_omdb_detail_shape(): void
    {
        $catalog = new MySqlMovieCatalog($this->pdoQueDevuelve([[
            'tconst'          => 'tt0457430',
            'title_type'      => 'movie',
            'primary_title'   => 'Pan\'s Labyrinth',
            'start_year'      => 2006,
            'end_year'        => null,
            'runtime_minutes' => 118,
            'genres'          => 'Drama,Fantasy,War',
            'average_rating'  => '8.2',
        ]]));

        $ficha = $catalog->findByImdbId('tt0457430');

        $this->assertSame('Pan\'s Labyrinth', $ficha['Title']);
        $this->assertSame('2006', $ficha['Year']);
        $this->assertSame('118 min', $ficha['Runtime'], 'OMDb escribe el runtime con unidad');
        $this->assertSame('Drama, Fantasy, War', $ficha['Genre'], 'IMDb los separa sin espacio');
        $this->assertSame('8.2', $ficha['imdbRating']);
        $this->assertSame('movie', $ficha['Type']);
        $this->assertNull($ficha['totalSeasons'], 'Una película no tiene temporadas');
    }

    #[Test]
    public function leaves_plot_poster_and_director_to_the_enrichment_layer(): void
    {
        // Los dumps abiertos de IMDb no publican sinopsis ni póster, y el
        // director vive en title.crew + name.basics, que no se ingestan.
        $catalog = new MySqlMovieCatalog($this->pdoQueDevuelve([[
            'tconst'          => 'tt0457430',
            'title_type'      => 'movie',
            'primary_title'   => 'Pan\'s Labyrinth',
            'start_year'      => 2006,
            'end_year'        => null,
            'runtime_minutes' => 118,
            'genres'          => 'Drama',
            'average_rating'  => '8.2',
        ]]));

        $ficha = $catalog->findByImdbId('tt0457430');

        $this->assertNull($ficha['Plot']);
        $this->assertNull($ficha['Poster']);
        $this->assertNull($ficha['Director']);
    }

    #[Test]
    public function writes_the_year_of_a_running_series_the_way_omdb_does(): void
    {
        $catalog = new MySqlMovieCatalog($this->pdoQueDevuelve([[
            'tconst'          => 'tt0903747',
            'title_type'      => 'tvSeries',
            'primary_title'   => 'Breaking Bad',
            'start_year'      => 2008,
            'end_year'        => null,
            'runtime_minutes' => 49,
            'genres'          => 'Crime,Drama,Thriller',
            'average_rating'  => '9.5',
        ]], [5]));

        $ficha = $catalog->findByImdbId('tt0903747');

        $this->assertSame('2008–', $ficha['Year']);
        $this->assertSame('series', $ficha['Type']);
        $this->assertSame('5', $ficha['totalSeasons']);
    }

    #[Test]
    public function returns_null_for_a_title_the_mirror_does_not_have(): void
    {
        $this->assertNull((new MySqlMovieCatalog($this->pdoQueDevuelve([])))->findByImdbId('tt9999999'));
    }

    // =========================================================================
    // seasonEpisodes
    // =========================================================================

    #[Test]
    public function maps_episodes_to_the_shape_the_tracker_prints(): void
    {
        // SeriesSeasonTracker.vue:158-167 lee Episode, Title e imdbRating.
        $catalog = new MySqlMovieCatalog($this->pdoQueDevuelve([[
            'tconst'         => 'tt0959621',
            'episode_number' => 1,
            'primary_title'  => 'Pilot',
            'average_rating' => '9.0',
        ]]));

        $this->assertSame([[
            'Title'      => 'Pilot',
            'Episode'    => '1',
            'imdbID'     => 'tt0959621',
            'imdbRating' => '9.0',
            'Released'   => null,
        ]], $catalog->seasonEpisodes('tt0903747', 1));
    }

    #[Test]
    public function keeps_an_unrated_episode_with_a_null_rating(): void
    {
        // Solo 877.130 de los 9,84 M de episodios tienen nota en IMDb.
        $catalog = new MySqlMovieCatalog($this->pdoQueDevuelve([[
            'tconst'         => 'tt0959621',
            'episode_number' => 1,
            'primary_title'  => 'Pilot',
            'average_rating' => null,
        ]]));

        $this->assertNull($catalog->seasonEpisodes('tt0903747', 1)[0]['imdbRating']);
    }
}
