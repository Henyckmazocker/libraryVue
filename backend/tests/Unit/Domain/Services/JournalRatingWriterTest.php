<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Model\Album;
use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\Movie\SeriesSeasonRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Services\JournalRatingWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * El primer test de `JournalRatingWriter`, y existe por un fallo concreto: hasta
 * el 2026-09-10 la rama de álbum hacía `updateRating($userId, (int) $entityId, …)`,
 * y PHP convierte `"171db008-7f7b-…"` en **`171`** sin error, sin aviso y sin
 * log. La valoración acababa en el álbum 171 —uno cualquiera— en vez de no
 * acabar en ninguno, y nadie podía verlo: la entrada se guardaba y la petición
 * devolvía 200.
 *
 * Por eso el caso central de este fichero no es «escribe donde debe» sino
 * «**nunca** escribe donde no debe»: que el número que llega a
 * `user_albums.updateRating` no pueda ser jamás el resultado de castear un MBID.
 *
 * La guarda sigue haciendo falta aunque `JournalItemResolver` ya normalice a PK
 * en el alta manual, porque por aquí pasan dos vías más que no lo hacen: la
 * edición de una entrada ya guardada, que reenvía el `entity_id` tal como está
 * en la BD, y los emisores automáticos, que llaman a `write()` sin resolver.
 */
class JournalRatingWriterTest extends TestCase
{
    private const USUARIO = 1;

    /** El MBID de dev, elegido a propósito: `(int)` lo convertiría en 171. */
    private const MBID = '171db008-7f7b-48d3-a3b5-640d6ea41aa7';

    /** Lo que ese casteo produciría, y que ningún repositorio debe ver nunca. */
    private const PK_FANTASMA = 171;

    private const PK = 2;

    private AlbumRepositoryInterface     $albums;
    private UserAlbumRepositoryInterface $userAlbums;
    private UserGameRepositoryInterface  $userGames;

    private JournalRatingWriter $writer;

    protected function setUp(): void
    {
        $this->albums     = $this->createMock(AlbumRepositoryInterface::class);
        $this->userAlbums = $this->createMock(UserAlbumRepositoryInterface::class);
        $this->userGames  = $this->createMock(UserGameRepositoryInterface::class);

        $this->writer = new JournalRatingWriter(
            $this->createMock(EditionRepositoryInterface::class),
            $this->createMock(UserBookEditionRepositoryInterface::class),
            $this->createMock(UserMovieRepositoryInterface::class),
            $this->userGames,
            $this->userAlbums,
            $this->albums,
            $this->createMock(UserVideoRepositoryInterface::class),
            $this->createMock(VideoRepositoryInterface::class),
            $this->createMock(SeriesSeasonRepositoryInterface::class),
            new NullLogger()
        );
    }

    #[Test]
    public function an_album_pointed_at_by_mbid_propagates_to_the_right_primary_key(): void
    {
        $this->albums->expects($this->once())
            ->method('findBySpotifyId')
            ->with(self::MBID)
            ->willReturn($this->album());

        // El segundo argumento es el PK de `albums`, que es por lo que
        // `user_albums` está indexada.
        $this->userAlbums->expects($this->once())
            ->method('updateRating')
            ->with(self::USUARIO, self::PK, 3.5);

        $this->writer->write(self::USUARIO, JournalEntry::MEDIA_ALBUM, self::MBID, 3.5);
    }

    #[Test]
    public function an_album_pointed_at_by_its_primary_key_goes_through_find_by_id(): void
    {
        // El camino de los emisores automáticos y, desde el M3, el de la ficha.
        $this->albums->expects($this->once())
            ->method('findById')
            ->with(self::PK)
            ->willReturn($this->album());
        $this->albums->expects($this->never())->method('findBySpotifyId');

        $this->userAlbums->expects($this->once())
            ->method('updateRating')
            ->with(self::USUARIO, self::PK, 3.5);

        $this->writer->write(self::USUARIO, JournalEntry::MEDIA_ALBUM, (string) self::PK, 3.5);
    }

    #[Test]
    public function an_unknown_mbid_propagates_nothing_at_all(): void
    {
        $this->albums->method('findBySpotifyId')->willReturn(null);

        // No propagar es la respuesta: quedarse sin valorar se arregla en un
        // clic, valorar el álbum de otro no se descubre nunca.
        $this->userAlbums->expects($this->never())->method('updateRating');

        $this->writer->write(self::USUARIO, JournalEntry::MEDIA_ALBUM, self::MBID, 3.5);
    }

    /**
     * El caso que hoy fallaría en silencio, y el motivo de todo el fichero.
     *
     * Se recorren las dos situaciones en las que el casteo ciego escribía:
     * el MBID conocido —donde escribía en 171 en vez de en 2— y el desconocido
     * —donde escribía en 171 en vez de en nada—. Ninguna de las dos puede
     * terminar con un `171` en `user_albums`.
     */
    #[Test]
    public function the_repository_is_never_called_with_the_int_cast_of_an_mbid(): void
    {
        $escrituras = [];

        $this->userAlbums->method('updateRating')->willReturnCallback(
            function (int $userId, int $albumId, ?float $rating) use (&$escrituras): void {
                $escrituras[] = $albumId;
            }
        );

        // 1. MBID conocido: se resuelve al PK real.
        $this->albums->method('findBySpotifyId')->willReturnCallback(
            fn (string $id): ?Album => $id === self::MBID ? $this->album() : null
        );

        $this->writer->write(self::USUARIO, JournalEntry::MEDIA_ALBUM, self::MBID, 3.5);

        // 2. MBID desconocido: el que antes escribía en un álbum ajeno.
        $this->writer->write(
            self::USUARIO,
            JournalEntry::MEDIA_ALBUM,
            'd44c50ad-0000-0000-0000-000000000000',
            3.5
        );

        // 3. Base62 desconocido, que `(int)` convertiría en 4.
        $this->writer->write(self::USUARIO, JournalEntry::MEDIA_ALBUM, '4aawyAB9vmqN3uQ7FjRGTy', 3.5);

        $this->assertSame([self::PK], $escrituras);
        $this->assertNotContains(
            self::PK_FANTASMA,
            $escrituras,
            'Un MBID casteado a entero jamás puede llegar a user_albums'
        );
    }

    #[Test]
    public function the_int_cast_of_the_game_branch_stays_because_it_is_the_identity(): void
    {
        // `games` no tiene columna `igdb_id`: su PK **es** el id de IGDB, así que
        // aquí el casteo no convierte nada, identifica. Se parece al fallo del
        // álbum y no lo es, y este test está para que nadie lo «arregle».
        $this->userGames->expects($this->once())
            ->method('updateRating')
            ->with(self::USUARIO, 1020, 4.0);

        $this->writer->write(self::USUARIO, JournalEntry::MEDIA_GAME, '1020', 4.0);
    }

    /** El álbum de dev: PK 2, identidad en el MBID y `spotify_id` a NULL. */
    private function album(): Album
    {
        return Album::fromArray([
            'id'                   => self::PK,
            'mb_release_group_gid' => self::MBID,
            'spotify_id'           => null,
            'catalog_source'       => 'musicbrainz',
            'title'                => 'The New Sound',
            'artist'               => 'Geordie Greep',
            'userStatuses'         => ['listened'],
        ]);
    }
}
