<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Journal;

use App\Domain\DTO\Commands\AddJournalEntryCommand;
use App\Domain\Model\Album;
use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Services\JournalItemResolver;
use App\Domain\Services\JournalRatingWriter;
use App\Domain\UseCases\Journal\AddJournalEntryUseCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use stdClass;

/**
 * El alta manual del diario, que es justo la que estaba rota.
 *
 * Lo que se fija aquí es la costura entre el resolver y lo que se persiste: la
 * `JournalEntry` que llega al repositorio lleva el identificador **canónico**,
 * el que devuelve `JournalItemResolver`, y no el que mandó el cliente. En álbum
 * son dos cosas distintas —la ficha manda el MBID de la ruta y la fila guarda el
 * PK de `albums`— y esa diferencia es el plan entero: si el diario guardase el
 * MBID, el mismo álbum tendría dos `entity_id`, dos filas donde debería haber
 * una y una portada rota, porque la clave de `cover_file` es la de
 * `libraryItem.idOf`, que en álbum es el PK.
 *
 * El resolver va **real** y solo se mockean sus repositorios: `AbstractUseCase`
 * lo llama a través de una firma que este test debe recorrer de verdad, y así lo
 * que se inspecciona es la entidad que acabaría en la base de datos.
 */
class AddJournalEntryUseCaseTest extends TestCase
{
    private const USUARIO = 1;
    private const MBID    = '171db008-7f7b-48d3-a3b5-640d6ea41aa7';
    private const FECHA   = '2026-09-10';

    private JournalRepositoryInterface $journal;
    private AlbumRepositoryInterface   $albums;
    private JournalRatingWriter        $ratings;

    /** La entrada tal y como llegaría a `journal_entry`. */
    private ?JournalEntry $guardada = null;

    private AddJournalEntryUseCase $useCase;

    protected function setUp(): void
    {
        $this->albums  = $this->createMock(AlbumRepositoryInterface::class);
        $this->journal = $this->createMock(JournalRepositoryInterface::class);
        $this->ratings = $this->createMock(JournalRatingWriter::class);

        $this->journal->method('add')->willReturnCallback(
            function (JournalEntry $entrada): int {
                $this->guardada = $entrada;

                return 99;
            }
        );

        $resolver = new JournalItemResolver(
            $this->createMock(EditionRepositoryInterface::class),
            $this->createMock(MovieRepositoryInterface::class),
            $this->createMock(GameRepositoryInterface::class),
            $this->albums,
            $this->createMock(VideoRepositoryInterface::class)
        );

        $this->useCase = new AddJournalEntryUseCase(
            $this->journal,
            $resolver,
            $this->ratings,
            new NullLogger()
        );
    }

    #[Test]
    public function the_entry_that_reaches_the_repository_carries_the_canonical_id(): void
    {
        $this->albums->method('findBySpotifyId')->willReturn($this->album());

        $resultado = $this->useCase->execute($this->comando(self::MBID));

        $this->assertSame(['id' => 99], $resultado);
        $this->assertNotNull($this->guardada);
        // El cliente mandó el MBID; lo que se guarda es el PK.
        $this->assertSame('2', $this->guardada->getEntityId());
        $this->assertNotSame(self::MBID, $this->guardada->getEntityId());
    }

    #[Test]
    public function the_title_and_the_cover_come_from_the_catalog_and_not_from_the_client(): void
    {
        // El cliente no manda ninguno de los dos: son la otra mitad de la
        // tripleta del resolver, y por eso no se puede escribir una entrada que
        // diga lo que quiera sobre un ítem.
        $this->albums->method('findBySpotifyId')->willReturn($this->album());

        $this->useCase->execute($this->comando(self::MBID));

        $this->assertSame('The New Sound', $this->guardada->getEntityTitle());
        $this->assertSame('https://cdn/the-new-sound.jpg', $this->guardada->getEntityCover());
        // Y el resto del comando llega intacto.
        $this->assertSame(self::USUARIO, $this->guardada->getUserId());
        $this->assertSame(JournalEntry::MEDIA_ALBUM, $this->guardada->getMedia());
        $this->assertSame(self::FECHA, $this->guardada->getEntryDate());
        $this->assertSame(3.5, $this->guardada->getRating());
        $this->assertSame(JournalEntry::SOURCE_MANUAL, $this->guardada->getSource());
    }

    #[Test]
    public function the_rating_writer_also_receives_the_canonical_id(): void
    {
        // Darle el MBID sería pedirle que adivine: el writer resuelve el álbum
        // por su cuenta, pero lo que el diario guarda es el PK y las dos vías
        // tienen que hablar del mismo ítem.
        $this->albums->method('findBySpotifyId')->willReturn($this->album());

        $this->ratings->expects($this->once())
            ->method('write')
            ->with(self::USUARIO, JournalEntry::MEDIA_ALBUM, '2', 3.5);

        $this->useCase->execute($this->comando(self::MBID));
    }

    #[Test]
    public function an_item_outside_the_catalog_is_rejected_and_nothing_is_written(): void
    {
        $this->albums->method('findBySpotifyId')->willReturn(null);

        $this->journal->expects($this->never())->method('add');
        $this->ratings->expects($this->never())->method('write');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('That item is not in the catalog.');

        $this->useCase->execute($this->comando('d44c50ad-0000-0000-0000-000000000000'));
    }

    #[Test]
    public function an_unknown_media_is_rejected_before_reaching_the_catalog(): void
    {
        $this->journal->expects($this->never())->method('add');

        $this->expectException(InvalidArgumentException::class);

        $this->useCase->execute(new AddJournalEntryCommand(
            userId:    self::USUARIO,
            media:     'boardgame',
            entityId:  '7',
            entryDate: self::FECHA
        ));
    }

    #[Test]
    public function an_empty_entity_id_is_rejected(): void
    {
        $this->journal->expects($this->never())->method('add');

        $this->expectException(InvalidArgumentException::class);

        $this->useCase->execute($this->comando(''));
    }

    #[Test]
    public function another_command_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->useCase->execute(new stdClass());
    }

    private function comando(string $entityId): AddJournalEntryCommand
    {
        return new AddJournalEntryCommand(
            userId:    self::USUARIO,
            media:     JournalEntry::MEDIA_ALBUM,
            entityId:  $entityId,
            entryDate: self::FECHA,
            rating:    3.5
        );
    }

    /** El álbum de dev: PK 2, identidad en el MBID y `spotify_id` a NULL. */
    private function album(): Album
    {
        return Album::fromArray([
            'id'                   => 2,
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
