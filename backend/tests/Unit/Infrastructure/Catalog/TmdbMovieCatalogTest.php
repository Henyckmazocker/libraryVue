<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Catalog;

use App\Domain\Services\TmdbService;
use App\Infrastructure\Catalog\TmdbMovieCatalog;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La copia persistida en tmdb_title y su ventana de frescura.
 *
 * No es una caché de rendimiento: las condiciones de TMDB permiten cachear
 * como mucho 6 meses, y `cached_at` es lo que permite cumplirlo. Que se sirva
 * una fila caducada no se notaría mirando la pantalla, así que se fija aquí.
 */
class TmdbMovieCatalogTest extends TestCase
{
    /**
     * @param array|false $filaCacheada Lo que devuelve el SELECT sobre tmdb_title
     */
    private function pdoConFila(array|false $filaCacheada): PDO
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($filaCacheada);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    #[Test]
    public function serves_a_fresh_row_without_calling_tmdb(): void
    {
        // El SELECT ya lleva el filtro `cached_at > NOW() - INTERVAL 5 MONTH`,
        // así que si devuelve fila, es fresca por construcción.
        $tmdb = $this->createMock(TmdbService::class);
        $tmdb->expects($this->never())->method('findByImdbId');

        $catalog = new TmdbMovieCatalog($tmdb, $this->pdoConFila([
            'media_type'    => 'movie',
            'title_es'      => 'El padrino',
            'overview_es'   => 'Don Vito Corleone...',
            'poster_path'   => '/abc.jpg',
            'director'      => 'Francis Ford Coppola',
            'total_seasons' => null,
        ]));

        $ficha = $catalog->findByImdbId('tt0068646');

        $this->assertSame('El padrino', $ficha['Title']);
        $this->assertSame('Francis Ford Coppola', $ficha['Director']);
        $this->assertSame('https://image.tmdb.org/t/p/w500/abc.jpg', $ficha['Poster']);
        $this->assertSame('movie', $ficha['Type']);
    }

    #[Test]
    public function does_not_invent_the_fields_the_mirror_owns(): void
    {
        // tmdb_title no guarda runtime, géneros ni año: son del mirror. Que
        // salgan a null es lo que deja que el decorador conserve los suyos.
        $catalog = new TmdbMovieCatalog($this->createMock(TmdbService::class), $this->pdoConFila([
            'media_type'    => 'movie',
            'title_es'      => 'El padrino',
            'overview_es'   => 'Don Vito Corleone...',
            'poster_path'   => '/abc.jpg',
            'director'      => 'Francis Ford Coppola',
            'total_seasons' => null,
        ]));

        $ficha = $catalog->findByImdbId('tt0068646');

        $this->assertNull($ficha['Runtime']);
        $this->assertNull($ficha['Genre']);
        $this->assertNull($ficha['Year']);
        $this->assertNull($ficha['imdbRating']);
    }

    #[Test]
    public function goes_to_tmdb_when_there_is_no_usable_row(): void
    {
        // Sin fila, o con una que el filtro de 5 meses ya descartó.
        $tmdb = $this->createMock(TmdbService::class);
        $tmdb->expects($this->once())
            ->method('findByImdbId')
            ->willReturn([
                'tmdb_id'     => 238,
                'media_type'  => 'movie',
                'title'       => 'El padrino',
                'overview'    => 'Don Vito Corleone...',
                'poster_path' => '/abc.jpg',
            ]);
        $tmdb->method('details')->willReturn([
            'release_date' => '1972-03-14',
            'runtime'      => 175,
            'genres'       => [['name' => 'Drama'], ['name' => 'Crimen']],
            'credits'      => ['crew' => [['job' => 'Director', 'name' => 'Francis Ford Coppola']]],
        ]);

        $catalog = new TmdbMovieCatalog($tmdb, $this->pdoConFila(false));
        $ficha   = $catalog->findByImdbId('tt0068646');

        $this->assertSame('1972', $ficha['Year']);
        $this->assertSame('175 min', $ficha['Runtime']);
        $this->assertSame('Drama, Crimen', $ficha['Genre']);
        $this->assertSame('Francis Ford Coppola', $ficha['Director']);
    }

    #[Test]
    public function returns_null_when_tmdb_does_not_know_the_title(): void
    {
        $tmdb = $this->createMock(TmdbService::class);
        $tmdb->method('findByImdbId')->willReturn(null);

        $this->assertNull(
            (new TmdbMovieCatalog($tmdb, $this->pdoConFila(false)))->findByImdbId('tt9999999')
        );
    }

    #[Test]
    public function never_searches(): void
    {
        // La búsqueda es del mirror y solo del mirror: 2,84 M de títulos que
        // responden en milisegundos y sin red.
        $tmdb = $this->createMock(TmdbService::class);

        $this->assertSame(
            [],
            (new TmdbMovieCatalog($tmdb, $this->pdoConFila(false)))->search('lo que sea')
        );
    }
}
