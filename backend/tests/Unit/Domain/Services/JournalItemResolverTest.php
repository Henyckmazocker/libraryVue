<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Model\Album;
use App\Domain\Model\Edition;
use App\Domain\Model\Game;
use App\Domain\Model\JournalEntry;
use App\Domain\Model\Movie;
use App\Domain\Model\Video;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Services\JournalItemResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El primer test de `JournalItemResolver`, y lo que fija es la **identidad**.
 *
 * `resolve()` devuelve una tripleta `[entityId canónico, título, portada]`, y el
 * primer elemento es el que acaba en `journal_entry.entity_id`. La regla que
 * este fichero convierte en barrera es la del plan *Identidad de Álbum en el
 * Diario*:
 *
 *  - **cinco medios devuelven el identificador que entró** —ISBN, tconst, id de
 *    IGDB y `youtube_id` ya son la identidad con la que el resto de la app
 *    conoce el ítem—, y
 *  - **el álbum es el único que lo descarta**: venga como venga (MBID del
 *    mirror, base62 de Spotify o el propio PK), lo que se guarda es el PK de
 *    `albums`, que es lo que ya escriben los emisores automáticos y lo que
 *    `libraryItem.idOf` usa como clave de portada.
 *
 * Sin esta barrera, un sexto medio con dos formas de identificador repetiría el
 * fallo entero —`400` al apuntar desde la ficha— sin que nada avisara. Los
 * fixtures se parecen a los datos de dev (`albums.id = 2`, MBID
 * `171db008-…`, «The New Sound») a propósito, pero aquí no se toca la BD: los
 * repositorios son mocks.
 */
class JournalItemResolverTest extends TestCase
{
    private const MBID    = '171db008-7f7b-48d3-a3b5-640d6ea41aa7';
    private const BASE62  = '4aawyAB9vmqN3uQ7FjRGTy';
    private const PK      = 2;

    private EditionRepositoryInterface $editions;
    private MovieRepositoryInterface   $movies;
    private GameRepositoryInterface    $games;
    private AlbumRepositoryInterface   $albums;
    private VideoRepositoryInterface   $videos;

    private JournalItemResolver $resolver;

    protected function setUp(): void
    {
        $this->editions = $this->createMock(EditionRepositoryInterface::class);
        $this->movies   = $this->createMock(MovieRepositoryInterface::class);
        $this->games    = $this->createMock(GameRepositoryInterface::class);
        $this->albums   = $this->createMock(AlbumRepositoryInterface::class);
        $this->videos   = $this->createMock(VideoRepositoryInterface::class);

        $this->resolver = new JournalItemResolver(
            $this->editions,
            $this->movies,
            $this->games,
            $this->albums,
            $this->videos
        );
    }

    // ------------------------------------------------------------------
    // Los cinco medios que devuelven el identificador que recibieron
    // ------------------------------------------------------------------

    #[Test]
    public function a_book_keeps_the_isbn_it_was_asked_with(): void
    {
        $this->editions->method('findByIsbn')->willReturn($this->edicion());

        $this->assertSame(
            ['9780441013593', 'Dune', 'https://cdn/dune-m.jpg'],
            $this->resolver->resolve(JournalEntry::MEDIA_BOOK, '9780441013593')
        );
    }

    #[Test]
    public function a_movie_keeps_the_tconst(): void
    {
        $this->movies->method('findById')->willReturn($this->pelicula());

        $this->assertSame(
            ['tt0816692', 'Interstellar', 'https://cdn/interstellar.jpg'],
            $this->resolver->resolve(JournalEntry::MEDIA_MOVIE, 'tt0816692')
        );
    }

    #[Test]
    public function a_series_resolves_through_the_movie_repository_and_keeps_the_tconst(): void
    {
        // Serie y película comparten tabla, repositorio y rama: lo que las
        // separa es `movie.media_type`, no la identidad.
        $this->movies->expects($this->once())
            ->method('findById')
            ->with('tt0944947')
            ->willReturn($this->pelicula('tt0944947', 'Game of Thrones'));

        $this->assertSame(
            ['tt0944947', 'Game of Thrones', 'https://cdn/interstellar.jpg'],
            $this->resolver->resolve(JournalEntry::MEDIA_SERIES, 'tt0944947')
        );
    }

    #[Test]
    public function a_game_keeps_the_igdb_id_that_is_also_its_pk(): void
    {
        // `games` no tiene columna `igdb_id`: su PK **es** el id de IGDB, así que
        // el entero que trae la ruta ya es la identidad. El casteo a `int` es
        // para la consulta; lo que se devuelve es la cadena que entró.
        $this->games->expects($this->once())
            ->method('findById')
            ->with(1020)
            ->willReturn($this->juego());

        $this->assertSame(
            ['1020', 'Hollow Knight', 'https://cdn/hollow.jpg'],
            $this->resolver->resolve(JournalEntry::MEDIA_GAME, '1020')
        );
    }

    #[Test]
    public function a_non_numeric_game_id_never_reaches_the_repository(): void
    {
        // La guarda de `ctype_digit` está antes del `(int)`: sin ella,
        // `findById(0)` sería una consulta inútil por cada id mal formado.
        $this->games->expects($this->never())->method('findById');

        $this->assertNull($this->resolver->resolve(JournalEntry::MEDIA_GAME, 'hollow-knight'));
    }

    #[Test]
    public function a_video_keeps_the_youtube_id_and_not_its_pk(): void
    {
        // El vídeo tiene PK propia (`videos.id = 31`) y aun así el diario guarda
        // el `youtube_id`: es el parámetro de su ficha y la clave de su portada.
        $this->videos->method('findByYouTubeId')->willReturn($this->video());

        $this->assertSame(
            ['A9mvuAwl5eo', 'Una charla', 'https://cdn/charla.jpg'],
            $this->resolver->resolve(JournalEntry::MEDIA_VIDEO, 'A9mvuAwl5eo')
        );
    }

    // ------------------------------------------------------------------
    // El álbum: los cuatro casos del hito
    // ------------------------------------------------------------------

    #[Test]
    public function an_album_asked_by_mbid_comes_back_as_the_primary_key(): void
    {
        // El caso que estaba roto: la ficha vive en `/albums/:albumId` y ese
        // parámetro es el MBID del mirror.
        $this->albums->expects($this->once())
            ->method('findBySpotifyId')
            ->with(self::MBID)
            ->willReturn($this->album());
        $this->albums->expects($this->never())->method('findById');

        $this->assertSame(
            ['2', 'The New Sound', 'https://cdn/the-new-sound.jpg'],
            $this->resolver->resolve(JournalEntry::MEDIA_ALBUM, self::MBID)
        );
    }

    #[Test]
    public function an_album_asked_by_base62_comes_back_as_the_primary_key(): void
    {
        // La otra forma que puede traer la ruta: los álbumes viejos de Spotify.
        // La consulta es la misma —el `WHERE` de `findBySpotifyId` mira
        // `spotify_id` **y** `mb_release_group_gid`—, así que aquí lo que se fija
        // es que el base62 no se confunda con un PK ni se pierda por el camino.
        $this->albums->expects($this->once())
            ->method('findBySpotifyId')
            ->with(self::BASE62)
            ->willReturn($this->album());

        $this->assertSame(
            ['2', 'The New Sound', 'https://cdn/the-new-sound.jpg'],
            $this->resolver->resolve(JournalEntry::MEDIA_ALBUM, self::BASE62)
        );
    }

    #[Test]
    public function an_album_asked_by_its_primary_key_still_resolves(): void
    {
        // Es lo que mandan los emisores automáticos y, desde el M3, la ficha.
        // Un id numérico va por `findById`, no por `findBySpotifyId`.
        $this->albums->expects($this->once())
            ->method('findById')
            ->with(self::PK)
            ->willReturn($this->album());
        $this->albums->expects($this->never())->method('findBySpotifyId');

        $this->assertSame(
            ['2', 'The New Sound', 'https://cdn/the-new-sound.jpg'],
            $this->resolver->resolve(JournalEntry::MEDIA_ALBUM, '2')
        );
    }

    #[Test]
    public function an_album_that_is_not_in_the_catalog_resolves_to_null(): void
    {
        // Y sigue siendo `null`, no una tripleta a medias: el `400` de
        // «That item is not in the catalog» es la respuesta correcta cuando el
        // álbum no está —decisión de producto del plan del diario—.
        $this->albums->method('findBySpotifyId')->willReturn(null);

        $this->assertNull(
            $this->resolver->resolve(JournalEntry::MEDIA_ALBUM, 'd44c50ad-0000-0000-0000-000000000000')
        );
    }

    // ------------------------------------------------------------------
    // Bordes
    // ------------------------------------------------------------------

    #[Test]
    public function an_item_missing_from_the_catalog_resolves_to_null_in_every_media(): void
    {
        $this->editions->method('findByIsbn')->willReturn(null);
        $this->movies->method('findById')->willReturn(null);
        $this->games->method('findById')->willReturn(null);
        $this->videos->method('findByYouTubeId')->willReturn(null);

        $this->assertNull($this->resolver->resolve(JournalEntry::MEDIA_BOOK, '9780000000000'));
        $this->assertNull($this->resolver->resolve(JournalEntry::MEDIA_MOVIE, 'tt0000000'));
        $this->assertNull($this->resolver->resolve(JournalEntry::MEDIA_SERIES, 'tt0000000'));
        $this->assertNull($this->resolver->resolve(JournalEntry::MEDIA_GAME, '999999'));
        $this->assertNull($this->resolver->resolve(JournalEntry::MEDIA_VIDEO, 'noSuchVideo'));
    }

    #[Test]
    public function an_unknown_media_resolves_to_null_without_touching_any_repository(): void
    {
        // El `default` del `match`. El caso de uso valida el medio antes de
        // llegar aquí, pero el resolver tiene más de un llamante.
        $this->editions->expects($this->never())->method('findByIsbn');
        $this->albums->expects($this->never())->method('findById');

        $this->assertNull($this->resolver->resolve('boardgame', '7'));
    }

    // ------------------------------------------------------------------
    // Fixtures — modelos reales, no dobles: lo que se prueba es de dónde sale
    // cada campo de la tripleta.
    // ------------------------------------------------------------------

    private function edicion(): Edition
    {
        return Edition::fromArray([
            'work_id'                 => 1,
            'openlibrary_edition_key' => 'OL1234M',
            'title'                   => 'Dune',
            'edition_id'              => 42,
            'cover_url_small'         => 'https://cdn/dune-s.jpg',
            // La portada del libro sale de la MEDIANA, no de la pequeña ni de la
            // grande: es la que pinta la fila del diario.
            'cover_url_medium'        => 'https://cdn/dune-m.jpg',
            'cover_url_large'         => 'https://cdn/dune-l.jpg',
        ]);
    }

    private function pelicula(string $tconst = 'tt0816692', string $titulo = 'Interstellar'): Movie
    {
        return Movie::fromArray([
            'id'           => $tconst,
            'title'        => $titulo,
            'coverUrl'     => 'https://cdn/interstellar.jpg',
            'userStatuses' => ['viewed'],
        ]);
    }

    private function juego(): Game
    {
        return Game::fromArray([
            'id'           => 1020,
            'slug'         => 'hollow-knight',
            'title'        => 'Hollow Knight',
            'cover_url'    => 'https://cdn/hollow.jpg',
            'userStatuses' => ['played'],
        ]);
    }

    private function video(): Video
    {
        return Video::fromArray([
            'id'           => 31,
            'youtube_id'   => 'A9mvuAwl5eo',
            'title'        => 'Una charla',
            'cover_url'    => 'https://cdn/charla.jpg',
            'userStatuses' => ['viewed'],
        ]);
    }

    /**
     * El álbum de dev: PK 2, identidad en `mb_release_group_gid` y
     * `spotify_id` a NULL, que es como quedan los álbumes del mirror.
     */
    private function album(): Album
    {
        return Album::fromArray([
            'id'                   => self::PK,
            'mb_release_group_gid' => self::MBID,
            'spotify_id'           => null,
            'catalog_source'       => 'musicbrainz',
            'title'                => 'The New Sound',
            'artist'               => 'Geordie Greep',
            'cover_url'            => 'https://cdn/the-new-sound.jpg',
            'userStatuses'         => ['listened'],
        ]);
    }
}
