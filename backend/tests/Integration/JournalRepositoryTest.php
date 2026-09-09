<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * El diario, contra el esquema real.
 *
 * Va de integración y no de unitario a propósito: lo que hay que probar aquí es
 * **el índice único `uq_journal_source`**, que es toda la lógica de duplicados
 * del diario y vive en la migración, no en PHP. Un test con PDO mockeado diría
 * que sí a cualquier cosa; el que importa es que MySQL admita NULLs repetidos en
 * un índice único —lo que permite repetir— y que a la vez corte los duplicados
 * con clave de origen.
 */
class JournalRepositoryTest extends IntegrationTestCase
{
    private int $userId;

    private JournalRepositoryInterface $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)'
        );
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Diarista']);
        $this->userId = (int) $this->pdo()->lastInsertId();

        $this->repo = $this->container()->get(JournalRepositoryInterface::class);
    }

    private function entradaManual(string $fecha, ?float $rating = null): JournalEntry
    {
        return new JournalEntry(
            id: null,
            userId: $this->userId,
            media: JournalEntry::MEDIA_MOVIE,
            entityId: 'tt0133093',
            entityTitle: 'The Matrix',
            entityCover: null,
            entryDate: $fecha,
            rating: $rating,
            source: JournalEntry::SOURCE_MANUAL,
            sourceId: null
        );
    }

    #[Test]
    public function the_same_item_can_be_logged_twice_by_hand(): void
    {
        $this->repo->add($this->entradaManual('2026-07-12', 4.5));
        $this->repo->add($this->entradaManual('2026-09-04', 5.0));

        $entradas = $this->repo->findByUser($this->userId, 20, 0);

        $this->assertCount(2, $entradas, 'Una entrada manual repetida no debe colisionar');
        $this->assertSame(2, $this->repo->countByUser($this->userId));
    }

    #[Test]
    public function the_later_of_two_entries_is_marked_as_a_repeat(): void
    {
        $this->repo->add($this->entradaManual('2026-07-12'));
        $this->repo->add($this->entradaManual('2026-09-04'));

        // `findByUser` ordena de lo más reciente a lo más antiguo.
        [$reciente, $antigua] = $this->repo->findByUser($this->userId, 20, 0);

        $this->assertSame('2026-09-04', $reciente->getEntryDate());
        $this->assertTrue($reciente->isRepeat(), 'La segunda vez es una repetición');
        $this->assertFalse($antigua->isRepeat(), 'La primera vez no lo es');
    }

    #[Test]
    public function an_automatic_entry_with_the_same_origin_updates_instead_of_duplicating(): void
    {
        $clave = 'movie:tt0133093:2026-09-04';

        $primera = $this->repo->add(new JournalEntry(
            id: null, userId: $this->userId, media: JournalEntry::MEDIA_MOVIE,
            entityId: 'tt0133093', entityTitle: 'The Matrix', entityCover: null,
            entryDate: '2026-09-04', rating: null,
            source: JournalEntry::SOURCE_STATUS, sourceId: $clave
        ));

        // Mismo origen, otra valoración: marcar y desmarcar el mismo día.
        $segunda = $this->repo->add(new JournalEntry(
            id: null, userId: $this->userId, media: JournalEntry::MEDIA_MOVIE,
            entityId: 'tt0133093', entityTitle: 'The Matrix', entityCover: null,
            entryDate: '2026-09-04', rating: 3.0,
            source: JournalEntry::SOURCE_STATUS, sourceId: $clave
        ));

        $this->assertSame(1, $this->repo->countByUser($this->userId), 'No debe crear una segunda fila');
        // `id = LAST_INSERT_ID(id)` en el ON DUPLICATE es lo que hace que el id
        // devuelto sea el real y no un 0 cuando la fila ya existía.
        $this->assertSame($primera, $segunda, 'Debe devolver el id de la fila que actualizó');
        $this->assertSame(3.0, $this->repo->findById($primera, $this->userId)?->getRating());
    }

    #[Test]
    public function a_reading_session_that_changes_date_moves_its_entry(): void
    {
        $id = $this->repo->add(new JournalEntry(
            id: null, userId: $this->userId, media: JournalEntry::MEDIA_BOOK,
            entityId: '9788410254145', entityTitle: 'Fills de la boira', entityCover: null,
            entryDate: '2026-08-27', rating: null,
            source: JournalEntry::SOURCE_READING_SESSION, sourceId: '2'
        ));

        $this->repo->add(new JournalEntry(
            id: null, userId: $this->userId, media: JournalEntry::MEDIA_BOOK,
            entityId: '9788410254145', entityTitle: 'Fills de la boira', entityCover: null,
            entryDate: '2026-08-30', rating: null,
            source: JournalEntry::SOURCE_READING_SESSION, sourceId: '2'
        ));

        $this->assertSame(1, $this->repo->countByUser($this->userId));
        $this->assertSame('2026-08-30', $this->repo->findById($id, $this->userId)?->getEntryDate());
    }

    #[Test]
    public function the_media_filter_narrows_both_the_list_and_the_count(): void
    {
        $this->repo->add($this->entradaManual('2026-09-01'));
        $this->repo->add(new JournalEntry(
            id: null, userId: $this->userId, media: JournalEntry::MEDIA_GAME,
            entityId: '1234', entityTitle: 'Cairn', entityCover: null,
            entryDate: '2026-09-02', rating: null,
            source: JournalEntry::SOURCE_MANUAL, sourceId: null
        ));

        $this->assertCount(1, $this->repo->findByUser($this->userId, 20, 0, JournalEntry::MEDIA_GAME));
        $this->assertSame(1, $this->repo->countByUser($this->userId, JournalEntry::MEDIA_GAME));
        $this->assertSame(2, $this->repo->countByUser($this->userId));
    }

    #[Test]
    public function an_entry_can_have_its_rating_cleared(): void
    {
        $id = $this->repo->add($this->entradaManual('2026-09-04', 4.0));

        $this->assertTrue($this->repo->update($id, $this->userId, null, null));
        $this->assertNull($this->repo->findById($id, $this->userId)?->getRating());
    }

    /**
     * Una entrada de cualquier medio en una fecha, para sembrar el agregado sin
     * repetir el constructor entero en cada test.
     */
    private function siembra(string $fecha, string $media, string $entityId): void
    {
        $this->repo->add(new JournalEntry(
            id: null, userId: $this->userId, media: $media,
            entityId: $entityId, entityTitle: 'Algo', entityCover: null,
            entryDate: $fecha, rating: null,
            source: JournalEntry::SOURCE_MANUAL, sourceId: null
        ));
    }

    #[Test]
    public function the_calendar_aggregates_one_row_per_day_with_entries(): void
    {
        $this->siembra('2026-09-04', JournalEntry::MEDIA_MOVIE, 'tt0133093');
        $this->siembra('2026-09-04', JournalEntry::MEDIA_BOOK,  '9788410254145');
        $this->siembra('2026-09-04', JournalEntry::MEDIA_BOOK,  '9788410254146');
        $this->siembra('2026-08-27', JournalEntry::MEDIA_BOOK,  '9788410254145');

        $dias = $this->repo->countByDay($this->userId, '2026-01-01', '2026-12-31');

        // Dos claves: los 363 días vacíos del año NO salen.
        $this->assertSame(['2026-08-27', '2026-09-04'], array_keys($dias));
        $this->assertSame(3, $dias['2026-09-04']['count']);
        // `GROUP_CONCAT(DISTINCT media)`: dos libros el mismo día son un medio.
        $this->assertSame(['book', 'movie'], $dias['2026-09-04']['media']);
        $this->assertSame(['count' => 1, 'media' => ['book']], $dias['2026-08-27']);
    }

    #[Test]
    public function the_calendar_only_looks_at_the_year_it_was_asked_for(): void
    {
        $this->siembra('2025-12-31', JournalEntry::MEDIA_GAME, '1');
        $this->siembra('2026-01-01', JournalEntry::MEDIA_GAME, '2');
        $this->siembra('2027-01-01', JournalEntry::MEDIA_GAME, '3');

        // Los extremos del rango son inclusivos: el 1 de enero entra.
        $this->assertSame(['2026-01-01'], array_keys($this->repo->countByDay($this->userId, '2026-01-01', '2026-12-31')));
        $this->assertSame([], $this->repo->countByDay($this->userId, '2020-01-01', '2020-12-31'));
    }

    #[Test]
    public function the_calendar_of_another_user_is_empty(): void
    {
        $this->siembra('2026-09-04', JournalEntry::MEDIA_MOVIE, 'tt0133093');

        $this->assertSame([], $this->repo->countByDay($this->userId + 999, '2026-01-01', '2026-12-31'));
        $this->assertSame([], $this->repo->yearsWithEntries($this->userId + 999));
    }

    #[Test]
    public function the_calendar_can_be_filtered_by_media_but_the_years_never_are(): void
    {
        // La enmienda del M4: las píldoras de `/journal` mandan también sobre el
        // calendario. Y su excepción deliberada, que es lo que este test fija:
        // `yearsWithEntries` sigue devolviendo TODOS los años con entradas, o
        // filtrar por «Álbum» borraría años enteros del selector.
        $this->siembra('2026-09-04', JournalEntry::MEDIA_MOVIE, 'tt0133093');
        $this->siembra('2026-09-04', JournalEntry::MEDIA_BOOK,  '9788410254145');
        $this->siembra('2026-08-27', JournalEntry::MEDIA_BOOK,  '9788410254146');
        $this->siembra('2024-05-05', JournalEntry::MEDIA_MOVIE, 'tt0111161');

        $libros = $this->repo->countByDay($this->userId, '2026-01-01', '2026-12-31', JournalEntry::MEDIA_BOOK);

        $this->assertSame(['2026-08-27', '2026-09-04'], array_keys($libros));
        // El día con una película y un libro cuenta UNO, y su acento es el libro.
        $this->assertSame(['count' => 1, 'media' => ['book']], $libros['2026-09-04']);

        // 2024 solo tiene una película: con «Book» el año se queda sin días…
        $this->assertSame([], $this->repo->countByDay($this->userId, '2024-01-01', '2024-12-31', JournalEntry::MEDIA_BOOK));
        // …pero sigue estando en el selector de años.
        $this->assertSame([2026, 2024], $this->repo->yearsWithEntries($this->userId));
    }

    #[Test]
    public function the_years_with_entries_come_deduplicated_and_newest_first(): void
    {
        $this->siembra('2025-03-01', JournalEntry::MEDIA_ALBUM, 'a');
        $this->siembra('2026-09-04', JournalEntry::MEDIA_ALBUM, 'b');
        $this->siembra('2026-01-02', JournalEntry::MEDIA_ALBUM, 'c');
        $this->siembra('2021-07-19', JournalEntry::MEDIA_ALBUM, 'd');

        $this->assertSame([2026, 2025, 2021], $this->repo->yearsWithEntries($this->userId));
    }

    #[Test]
    public function a_date_range_narrows_both_the_list_and_the_count(): void
    {
        $this->siembra('2026-08-31', JournalEntry::MEDIA_MOVIE, 'agosto');
        $this->siembra('2026-09-01', JournalEntry::MEDIA_MOVIE, 'primero');
        $this->siembra('2026-09-30', JournalEntry::MEDIA_MOVIE, 'ultimo');
        $this->siembra('2026-10-01', JournalEntry::MEDIA_MOVIE, 'octubre');

        $septiembre = $this->repo->findByUser($this->userId, 20, 0, null, '2026-09-01', '2026-09-30');

        $this->assertSame(
            ['2026-09-30', '2026-09-01'],
            array_map(static fn ($e): string => $e->getEntryDate(), $septiembre),
            'Los dos extremos son inclusivos y el orden sigue siendo del más reciente al más antiguo'
        );
        $this->assertSame(2, $this->repo->countByUser($this->userId, null, '2026-09-01', '2026-09-30'));
        $this->assertSame(4, $this->repo->countByUser($this->userId));
    }

    #[Test]
    public function each_end_of_the_range_works_on_its_own(): void
    {
        $this->siembra('2026-08-31', JournalEntry::MEDIA_MOVIE, 'agosto');
        $this->siembra('2026-09-15', JournalEntry::MEDIA_MOVIE, 'septiembre');

        $this->assertSame(1, $this->repo->countByUser($this->userId, null, '2026-09-01', null));
        $this->assertSame(1, $this->repo->countByUser($this->userId, null, null, '2026-08-31'));
    }

    #[Test]
    public function entries_of_another_user_are_neither_readable_nor_writable(): void
    {
        $id = $this->repo->add($this->entradaManual('2026-09-04'));
        $otro = $this->userId + 999;

        $this->assertNull($this->repo->findById($id, $otro));
        $this->assertFalse($this->repo->update($id, $otro, '2020-01-01', null));
        $this->assertFalse($this->repo->delete($id, $otro));
        $this->assertTrue($this->repo->delete($id, $this->userId));
    }
}
