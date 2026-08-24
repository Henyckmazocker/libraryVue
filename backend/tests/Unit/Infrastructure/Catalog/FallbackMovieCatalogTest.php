<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Catalog;

use App\Domain\Repository\Catalog\MovieCatalogInterface;
use App\Infrastructure\Catalog\FallbackMovieCatalog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Las reglas del decorador, que es donde vive la decisión local-o-red.
 *
 * Con dobles: aquí no se toca ni la base de datos ni la red. Lo que se
 * comprueba es exactamente cuándo se sale a internet y cuándo no, que es la
 * promesa entera del mirror.
 */
class FallbackMovieCatalogTest extends TestCase
{
    private MockObject $local;
    private MockObject $remote;
    private FallbackMovieCatalog $catalog;

    protected function setUp(): void
    {
        $this->local   = $this->createMock(MovieCatalogInterface::class);
        $this->remote  = $this->createMock(MovieCatalogInterface::class);
        $this->catalog = new FallbackMovieCatalog($this->local, $this->remote, new NullLogger());
    }

    /** Una ficha completa, como la que devuelve el mirror ya enriquecida */
    private function fichaCompleta(): array
    {
        return [
            'Title'      => 'Pan\'s Labyrinth',
            'Year'       => '2006',
            'Runtime'    => '118 min',
            'Genre'      => 'Drama, Fantasy, War',
            'Director'   => null,
            'Plot'       => 'En el Madrid de 1944...',
            'Poster'     => 'https://image.tmdb.org/t/p/w500/x.jpg',
            'imdbRating' => '8.2',
            'imdbID'     => 'tt0457430',
            'Type'       => 'movie',
        ];
    }

    // =========================================================================
    // search — nunca sale a la red
    // =========================================================================

    #[Test]
    public function search_never_consults_the_network(): void
    {
        $this->local->method('search')->willReturn([]);
        $this->remote->expects($this->never())->method('search');

        $this->assertSame([], $this->catalog->search('una cosa que no existe'));
    }

    #[Test]
    public function search_returns_what_the_mirror_says(): void
    {
        $resultados = [['Title' => 'Die Hard', 'imdbID' => 'tt0095016']];
        $this->local->method('search')->willReturn($resultados);

        $this->assertSame($resultados, $this->catalog->search('jungla de cristal'));
    }

    // =========================================================================
    // findByImdbId — cae a la red solo si falta Plot o Poster
    // =========================================================================

    #[Test]
    public function does_not_consult_the_network_when_the_local_record_is_complete(): void
    {
        $this->local->method('findByImdbId')->willReturn($this->fichaCompleta());
        $this->remote->expects($this->never())->method('findByImdbId');

        $this->assertSame($this->fichaCompleta(), $this->catalog->findByImdbId('tt0457430'));
    }

    #[Test]
    public function consults_the_network_when_the_plot_is_missing(): void
    {
        $sinPlot         = $this->fichaCompleta();
        $sinPlot['Plot'] = null;

        $this->local->method('findByImdbId')->willReturn($sinPlot);
        $this->remote->expects($this->once())
            ->method('findByImdbId')
            ->willReturn(['Plot' => 'Sinopsis de TMDB']);

        $this->assertSame('Sinopsis de TMDB', $this->catalog->findByImdbId('tt0457430')['Plot']);
    }

    #[Test]
    public function consults_the_network_when_the_poster_is_missing(): void
    {
        $sinPoster           = $this->fichaCompleta();
        $sinPoster['Poster'] = null;

        $this->local->method('findByImdbId')->willReturn($sinPoster);
        $this->remote->expects($this->once())
            ->method('findByImdbId')
            ->willReturn(['Poster' => 'https://image.tmdb.org/t/p/w500/y.jpg']);

        $this->assertNotNull($this->catalog->findByImdbId('tt0457430')['Poster']);
    }

    #[Test]
    public function merges_instead_of_replacing_and_local_wins(): void
    {
        // Lo importante del decorador: el runtime, los géneros y la nota del
        // mirror vienen de IMDb, y los de TMDB son una copia de segunda mano.
        $incompleta = $this->fichaCompleta();
        $incompleta['Plot']     = null;
        $incompleta['Director'] = null;

        $this->local->method('findByImdbId')->willReturn($incompleta);
        $this->remote->method('findByImdbId')->willReturn([
            'Plot'       => 'Sinopsis de TMDB',
            'Director'   => 'Guillermo del Toro',
            'Runtime'    => '999 min',           // TMDB se equivoca
            'imdbRating' => '1.0',               // y su nota no es la de IMDb
        ]);

        $resultado = $this->catalog->findByImdbId('tt0457430');

        $this->assertSame('Sinopsis de TMDB', $resultado['Plot']);
        $this->assertSame('Guillermo del Toro', $resultado['Director']);
        $this->assertSame('118 min', $resultado['Runtime'], 'El runtime local manda');
        $this->assertSame('8.2', $resultado['imdbRating'], 'La nota local manda');
    }

    #[Test]
    public function returns_the_remote_record_when_the_title_is_not_in_the_mirror(): void
    {
        $this->local->method('findByImdbId')->willReturn(null);
        $this->remote->method('findByImdbId')->willReturn($this->fichaCompleta());

        $this->assertSame('tt0457430', $this->catalog->findByImdbId('tt0457430')['imdbID']);
    }

    #[Test]
    public function keeps_the_incomplete_local_record_when_there_is_no_network(): void
    {
        // Sin red, una ficha sin sinopsis es mejor que ninguna ficha: es
        // justo lo que OmdbService devolvía cuando la API no respondía.
        $sinPlot         = $this->fichaCompleta();
        $sinPlot['Plot'] = null;

        $this->local->method('findByImdbId')->willReturn($sinPlot);
        $this->remote->method('findByImdbId')->willThrowException(new RuntimeException('sin red'));

        $resultado = $this->catalog->findByImdbId('tt0457430');

        $this->assertSame('Pan\'s Labyrinth', $resultado['Title']);
        $this->assertNull($resultado['Plot']);
    }

    #[Test]
    public function returns_null_when_neither_source_knows_the_title(): void
    {
        $this->local->method('findByImdbId')->willReturn(null);
        $this->remote->method('findByImdbId')->willReturn(null);

        $this->assertNull($this->catalog->findByImdbId('tt9999999'));
    }

    // =========================================================================
    // seasonEpisodes — cae a la red solo si el local no devuelve nada
    // =========================================================================

    #[Test]
    public function does_not_consult_the_network_when_the_mirror_has_the_season(): void
    {
        $episodios = [['Title' => 'Pilot', 'Episode' => '1']];

        $this->local->method('seasonEpisodes')->willReturn($episodios);
        $this->remote->expects($this->never())->method('seasonEpisodes');

        $this->assertSame($episodios, $this->catalog->seasonEpisodes('tt0903747', 1));
    }

    #[Test]
    public function consults_the_network_for_a_season_the_mirror_does_not_have(): void
    {
        $this->local->method('seasonEpisodes')->willReturn([]);
        $this->remote->expects($this->once())
            ->method('seasonEpisodes')
            ->willReturn([['Title' => 'Episodio de TMDB', 'Episode' => '1']]);

        $this->assertCount(1, $this->catalog->seasonEpisodes('tt0903747', 99));
    }

    #[Test]
    public function returns_an_empty_season_instead_of_failing_without_network(): void
    {
        $this->local->method('seasonEpisodes')->willReturn([]);
        $this->remote->method('seasonEpisodes')->willThrowException(new RuntimeException('sin red'));

        $this->assertSame([], $this->catalog->seasonEpisodes('tt0903747', 1));
    }
}
