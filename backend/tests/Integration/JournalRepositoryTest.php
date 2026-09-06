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
