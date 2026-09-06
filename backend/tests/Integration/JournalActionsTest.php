<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Las cuatro acciones del diario, por `ActionRouter` y contra el esquema real.
 *
 * Va por el router y no por el caso de uso porque el fallo típico de este repo
 * es justo la costura: una acción declarada en dos de los tres sitios, o un
 * comando construido con los argumentos en otro orden que su constructor
 * (`ActionRouter.php:291` lo tiene así desde hace meses en `update_book_rating`).
 * Un unitario con la interfaz mockeada no ve ninguna de las dos cosas.
 */
class JournalActionsTest extends IntegrationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)'
        );
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Diarista']);
        $this->userId = (int) $this->pdo()->lastInsertId();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $this->userId]);

        // Una película del catálogo, que es lo que el alta manual resuelve para
        // copiar título y portada en la entrada.
        $this->pdo()->prepare(
            'INSERT INTO movie (isbn, title, coverUrl) VALUES (:i, :t, :c)'
        )->execute([':i' => 'tt0133093', ':t' => 'The Matrix', ':c' => 'https://ejemplo.test/matrix.jpg']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    #[Test]
    public function an_entry_can_be_logged_by_hand_with_a_past_date(): void
    {
        $alta = $this->router()->dispatch('add_journal_entry', [
            'media'     => 'movie',
            'entityId'  => 'tt0133093',
            'entryDate' => '2026-07-12',
            'rating'    => 4.5,
        ]);

        $this->assertSame('success', $alta['status'], $alta['message'] ?? '');
        $this->assertIsInt($alta['data']['id']);

        $diario = $this->router()->dispatch('get_journal', []);

        $this->assertSame('success', $diario['status'], $diario['message'] ?? '');
        $this->assertSame(1, $diario['data']['total']);
        $entrada = $diario['data']['entries'][0];
        // El título y la portada NO viajan en la petición: los resuelve el
        // backend contra el catálogo.
        $this->assertSame('The Matrix', $entrada['entity_title']);
        $this->assertSame('https://ejemplo.test/matrix.jpg', $entrada['entity_cover']);
        $this->assertSame('2026-07-12', $entrada['entry_date']);
        $this->assertSame(4.5, $entrada['rating']);
        $this->assertFalse($entrada['is_repeat']);
    }

    #[Test]
    public function the_rating_of_the_entry_also_lands_on_the_item(): void
    {
        // «La última entrada manda»: la ficha y /library tienen que enseñar el
        // mismo número que el diario.
        $this->pdo()->prepare('INSERT INTO user_movies (user_id, movie_isbn) VALUES (:u, :m)')
            ->execute([':u' => $this->userId, ':m' => 'tt0133093']);

        $this->router()->dispatch('add_journal_entry', [
            'media' => 'movie', 'entityId' => 'tt0133093', 'entryDate' => '2026-09-04', 'rating' => 4.5,
        ]);

        $stmt = $this->pdo()->prepare(
            'SELECT personal_rating FROM user_movies WHERE user_id = :u AND movie_isbn = :m'
        );
        $stmt->execute([':u' => $this->userId, ':m' => 'tt0133093']);

        $this->assertSame('4.5', $stmt->fetchColumn());
    }

    #[Test]
    public function the_same_film_can_be_logged_twice_and_the_second_is_a_repeat(): void
    {
        $this->router()->dispatch('add_journal_entry', [
            'media' => 'movie', 'entityId' => 'tt0133093', 'entryDate' => '2026-07-12',
        ]);
        $this->router()->dispatch('add_journal_entry', [
            'media' => 'movie', 'entityId' => 'tt0133093', 'entryDate' => '2026-09-04',
        ]);

        $diario = $this->router()->dispatch('get_journal', []);

        $this->assertSame(2, $diario['data']['total']);
        $this->assertTrue($diario['data']['entries'][0]['is_repeat']);
        $this->assertFalse($diario['data']['entries'][1]['is_repeat']);
    }

    #[Test]
    public function an_entry_can_change_its_date_and_be_deleted(): void
    {
        $id = $this->router()->dispatch('add_journal_entry', [
            'media' => 'movie', 'entityId' => 'tt0133093', 'entryDate' => '2026-09-04',
        ])['data']['id'];

        $edicion = $this->router()->dispatch('update_journal_entry', [
            'entryId' => $id, 'entryDate' => '2026-08-01',
        ]);
        $this->assertSame('success', $edicion['status'], $edicion['message'] ?? '');

        $diario = $this->router()->dispatch('get_journal', []);
        $this->assertSame('2026-08-01', $diario['data']['entries'][0]['entry_date']);

        $borrado = $this->router()->dispatch('delete_journal_entry', ['entryId' => $id]);
        $this->assertSame('success', $borrado['status'], $borrado['message'] ?? '');
        $this->assertSame(0, $this->router()->dispatch('get_journal', [])['data']['total']);
    }

    #[Test]
    public function an_item_outside_the_catalog_is_rejected(): void
    {
        $respuesta = $this->router()->dispatch('add_journal_entry', [
            'media' => 'movie', 'entityId' => 'tt0000000', 'entryDate' => '2026-09-04',
        ]);

        $this->assertNotSame('success', $respuesta['status']);
        $this->assertSame(0, $this->router()->dispatch('get_journal', [])['data']['total']);
    }

    #[Test]
    public function a_future_date_is_accepted_on_purpose(): void
    {
        // Decidido con David el 2026-09-04: `SeriesSeasonTracker` ya deja marcar
        // temporadas con fecha por venir y esos son datos del usuario. Si algún
        // día se validara contra CURDATE(), este test lo diría.
        $futuro = date('Y-m-d', strtotime('+30 days'));

        $alta = $this->router()->dispatch('add_journal_entry', [
            'media' => 'movie', 'entityId' => 'tt0133093', 'entryDate' => $futuro,
        ]);

        $this->assertSame('success', $alta['status'], $alta['message'] ?? '');
        $this->assertSame($futuro, $this->router()->dispatch('get_journal', [])['data']['entries'][0]['entry_date']);
    }

    #[Test]
    public function the_media_filter_and_the_paging_work_together(): void
    {
        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $fecha) {
            $this->router()->dispatch('add_journal_entry', [
                'media' => 'movie', 'entityId' => 'tt0133093', 'entryDate' => $fecha,
            ]);
        }

        $pagina = $this->router()->dispatch('get_journal', ['limit' => 2, 'offset' => 0]);
        $this->assertCount(2, $pagina['data']['entries']);
        $this->assertSame(3, $pagina['data']['total']);
        $this->assertTrue($pagina['data']['hasMore']);

        $ultima = $this->router()->dispatch('get_journal', ['limit' => 2, 'offset' => 2]);
        $this->assertCount(1, $ultima['data']['entries']);
        $this->assertFalse($ultima['data']['hasMore']);

        $this->assertSame(0, $this->router()->dispatch('get_journal', ['media' => 'game'])['data']['total']);
    }

    /**
     * Los dos que **no** cazó la primera versión de este fichero, y sí una
     * petición real con `curl` el 2026-09-04. El primero pasaba en verde con un
     * `assertNotSame('success', …)`, que da por bueno un 500.
     */
    #[Test]
    public function acting_on_an_entry_that_does_not_exist_is_a_404_and_not_a_500(): void
    {
        $borrado = $this->router()->dispatch('delete_journal_entry', ['entryId' => 99999]);
        $this->assertSame(404, $borrado['http_code']);

        $edicion = $this->router()->dispatch('update_journal_entry', [
            'entryId' => 99999, 'entryDate' => '2026-09-04',
        ]);
        $this->assertSame(404, $edicion['http_code']);
    }

    #[Test]
    public function watching_the_next_season_of_a_series_is_not_a_repeat(): void
    {
        // En series la identidad de una entrada es la TEMPORADA, no la serie:
        // ver la 2 después de la 1 no es revisionar nada.
        $this->pdo()->prepare('INSERT INTO movie (isbn, title) VALUES (:i, :t)')
            ->execute([':i' => 'tt14452776', ':t' => 'The Bear']);

        $repo = $this->container()->get(\App\Domain\Repository\Journal\JournalRepositoryInterface::class);

        foreach ([['1', '2026-09-17'], ['2', '2026-09-24']] as [$temporada, $fecha]) {
            $repo->add(new \App\Domain\Model\JournalEntry(
                id: null, userId: $this->userId, media: 'series',
                entityId: 'tt14452776', entityTitle: 'The Bear', entityCover: null,
                entryDate: $fecha, rating: null,
                source: \App\Domain\Model\JournalEntry::SOURCE_SERIES_SEASON,
                sourceId: 'tt14452776:' . $temporada
            ));
        }

        $entradas = $this->router()->dispatch('get_journal', ['media' => 'series'])['data']['entries'];

        $this->assertCount(2, $entradas);
        $this->assertFalse($entradas[0]['is_repeat'], 'La temporada 2 no es una repetición de la 1');
        $this->assertFalse($entradas[1]['is_repeat']);
    }

    #[Test]
    public function rewatching_the_same_season_IS_a_repeat(): void
    {
        // La contraparte del test de arriba: la condición nueva no puede haber
        // apagado la detección de repetición en series.
        $this->pdo()->prepare('INSERT INTO movie (isbn, title) VALUES (:i, :t)')
            ->execute([':i' => 'tt14452776', ':t' => 'The Bear']);

        $repo = $this->container()->get(\App\Domain\Repository\Journal\JournalRepositoryInterface::class);

        foreach (['2026-05-01', '2026-09-17'] as $fecha) {
            $repo->add(new \App\Domain\Model\JournalEntry(
                id: null, userId: $this->userId, media: 'series',
                entityId: 'tt14452776', entityTitle: 'The Bear', entityCover: null,
                entryDate: $fecha, rating: null,
                source: \App\Domain\Model\JournalEntry::SOURCE_MANUAL, sourceId: null
            ));
        }

        $entradas = $this->router()->dispatch('get_journal', ['media' => 'series'])['data']['entries'];

        $this->assertTrue($entradas[0]['is_repeat'], 'La misma temporada dos veces sí lo es');
        $this->assertFalse($entradas[1]['is_repeat']);
    }

    #[Test]
    public function the_entries_of_another_user_are_out_of_reach(): void
    {
        $id = $this->router()->dispatch('add_journal_entry', [
            'media' => 'movie', 'entityId' => 'tt0133093', 'entryDate' => '2026-09-04',
        ])['data']['id'];

        $stmt = $this->pdo()->prepare('INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)');
        $stmt->execute(['g' => 'g-otro', 'e' => 'otro@ejemplo.test', 'n' => 'Otro']);
        $otro = (int) $this->pdo()->lastInsertId();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)->generate(['user_id' => $otro]);

        $this->assertSame(0, $this->router()->dispatch('get_journal', [])['data']['total']);
        $this->assertNotSame('success', $this->router()->dispatch('delete_journal_entry', ['entryId' => $id])['status']);
    }
}
