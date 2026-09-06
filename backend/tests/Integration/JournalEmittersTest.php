<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Los emisores automáticos del diario, y sobre todo **la transición**.
 *
 * Este fichero es la red del riesgo que mandaba el plan: los cinco
 * `Update<Medio>UserStatusesUseCase` reciben el conjunto ENTERO de estados y lo
 * sustituyen, así que sin comparar contra los previos, reabrir el selector de
 * una película ya vista y volver a guardar apuntaría que la viste hoy. Un mes de
 * trastear con estados y el diario sería basura.
 *
 * Se entra por `ActionRouter`, que es como entra el cliente.
 */
class JournalEmittersTest extends IntegrationTestCase
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

    private function entradasDelDiario(): array
    {
        return $this->router()->dispatch('get_journal', [])['data']['entries'];
    }

    private function darDeAltaPelicula(string $isbn = 'tt0133093', string $tipo = 'movie'): void
    {
        $this->pdo()->prepare(
            'INSERT INTO movie (isbn, title, coverUrl, media_type) VALUES (:i, :t, :c, :mt)'
        )->execute([':i' => $isbn, ':t' => 'The Matrix', ':c' => null, ':mt' => $tipo]);

        $this->pdo()->prepare('INSERT INTO user_movies (user_id, movie_isbn) VALUES (:u, :m)')
            ->execute([':u' => $this->userId, ':m' => $isbn]);
    }

    #[Test]
    public function marking_a_film_as_viewed_writes_todays_entry(): void
    {
        $this->darDeAltaPelicula();

        $this->router()->dispatch('update_movie_user_statuses', [
            'id' => 'tt0133093', 'statuses' => ['owned', 'viewed'],
        ]);

        $entradas = $this->entradasDelDiario();

        $this->assertCount(1, $entradas);
        $this->assertSame('movie', $entradas[0]['media']);
        $this->assertSame('tt0133093', $entradas[0]['entity_id']);
        $this->assertSame(date('Y-m-d'), $entradas[0]['entry_date']);
        $this->assertSame('status', $entradas[0]['source']);
    }

    #[Test]
    public function saving_the_same_statuses_again_does_not_write_a_second_entry(): void
    {
        // **El test que justifica el M0.** Sin la comparación contra los estados
        // previos, esta segunda llamada apuntaría que viste la peli otra vez.
        $this->darDeAltaPelicula();

        $this->router()->dispatch('update_movie_user_statuses', [
            'id' => 'tt0133093', 'statuses' => ['owned', 'viewed'],
        ]);
        $this->router()->dispatch('update_movie_user_statuses', [
            'id' => 'tt0133093', 'statuses' => ['owned', 'viewed'],
        ]);

        $this->assertCount(1, $this->entradasDelDiario());
    }

    #[Test]
    public function a_status_change_that_is_not_consumption_writes_nothing(): void
    {
        $this->darDeAltaPelicula();

        // `owned` es tener, no haber visto. Y en este proyecto conviven.
        $this->router()->dispatch('update_movie_user_statuses', [
            'id' => 'tt0133093', 'statuses' => ['owned'],
        ]);

        $this->assertCount(0, $this->entradasDelDiario());
    }

    #[Test]
    public function unmarking_and_remarking_the_same_day_keeps_a_single_entry(): void
    {
        $this->darDeAltaPelicula();

        $this->router()->dispatch('update_movie_user_statuses', ['id' => 'tt0133093', 'statuses' => ['viewed']]);
        $this->router()->dispatch('update_movie_user_statuses', ['id' => 'tt0133093', 'statuses' => ['owned']]);
        $this->router()->dispatch('update_movie_user_statuses', ['id' => 'tt0133093', 'statuses' => ['viewed']]);

        // La clave del origen es `medio:id:día`: el mismo día, una entrada.
        $this->assertCount(1, $this->entradasDelDiario());
    }

    #[Test]
    public function a_game_marked_with_two_triggering_statuses_at_once_writes_one_entry(): void
    {
        $this->pdo()->prepare('INSERT INTO games (id, slug, title) VALUES (:i, :s, :t)')
            ->execute([':i' => 4242, ':s' => 'cairn', ':t' => 'Cairn']);
        $this->pdo()->prepare('INSERT INTO user_games (user_id, game_id) VALUES (:u, :g)')
            ->execute([':u' => $this->userId, ':g' => 4242]);

        // Los tres estados de juego disparan (decisión de David, 2026-09-04),
        // pero marcar dos a la vez es un solo consumo.
        $this->router()->dispatch('update_game_user_statuses', [
            'gameId' => 4242, 'statuses' => ['played', 'completed'],
        ]);

        $entradas = $this->entradasDelDiario();
        $this->assertCount(1, $entradas);
        $this->assertSame('game', $entradas[0]['media']);
    }

    #[Test]
    public function tracking_a_season_as_viewed_writes_its_own_entry(): void
    {
        $this->darDeAltaPelicula('tt14452776', 'series');

        $this->router()->dispatch('track_series_season', [
            'seriesIsbn'   => 'tt14452776',
            'seasonNumber' => 2,
            'status'       => 'viewed',
            'dateViewed'   => '2026-08-15',
            'personalRating' => 4.0,
        ]);

        $entradas = $this->entradasDelDiario();

        $this->assertCount(1, $entradas);
        $this->assertSame('series', $entradas[0]['media']);
        // La fecha es la que eligió el usuario en el seguimiento, no hoy.
        $this->assertSame('2026-08-15', $entradas[0]['entry_date']);
        $this->assertSame(4.0, $entradas[0]['rating']);
        $this->assertSame('series_season', $entradas[0]['source']);
    }

    #[Test]
    public function re_tracking_the_same_season_updates_its_entry_instead_of_duplicating(): void
    {
        $this->darDeAltaPelicula('tt14452776', 'series');

        foreach (['2026-08-15', '2026-08-20'] as $fecha) {
            $this->router()->dispatch('track_series_season', [
                'seriesIsbn' => 'tt14452776', 'seasonNumber' => 2,
                'status' => 'viewed', 'dateViewed' => $fecha,
            ]);
        }

        $entradas = $this->entradasDelDiario();

        $this->assertCount(1, $entradas, 'El `source_id` es la temporada: se actualiza, no se duplica');
        $this->assertSame('2026-08-20', $entradas[0]['entry_date']);
    }

    #[Test]
    public function finishing_a_book_writes_one_entry_from_its_reading_session(): void
    {
        // El emisor de libros vive en DOS sitios y este comprueba que no se
        // pisan: `UpdateReadingProgressUseCase` apunta al cerrar la sesión al
        // 100 %, y ese mismo camino pone el estado `read`, que también dispara.
        // Como los dos orígenes tienen claves distintas —el id de la sesión y
        // `book:<isbn>:<día>`— podrían salir DOS entradas del mismo acto.
        $this->pdo()->prepare('INSERT INTO book_works (work_id, title, authors) VALUES (1, :t, JSON_ARRAY())')
            ->execute([':t' => 'Fills de la boira']);
        $this->pdo()->prepare(
            'INSERT INTO book_editions (edition_id, work_id, openlibrary_edition_key, isbn_13, title, pages)
             VALUES (1, 1, :k, :i, :t, 300)'
        )->execute([':k' => 'OL1M', ':i' => '9788410254145', ':t' => 'Fills de la boira']);
        $this->pdo()->prepare(
            'INSERT INTO user_book_editions (user_id, edition_id, current_page) VALUES (:u, 1, 10)'
        )->execute([':u' => $this->userId]);

        $this->router()->dispatch('update_reading_progress', [
            'isbn' => '9788410254145', 'currentPage' => 300,
        ]);

        $entradas = $this->entradasDelDiario();

        $this->assertCount(1, $entradas, 'Terminar un libro apunta UNA entrada, no dos');
        $this->assertSame('book', $entradas[0]['media']);
        $this->assertSame('9788410254145', $entradas[0]['entity_id']);
        $this->assertSame('reading_session', $entradas[0]['source']);
        $this->assertSame(date('Y-m-d'), $entradas[0]['entry_date']);
    }

    #[Test]
    public function a_partial_or_skipped_season_writes_nothing(): void
    {
        $this->darDeAltaPelicula('tt14452776', 'series');

        $this->router()->dispatch('track_series_season', [
            'seriesIsbn' => 'tt14452776', 'seasonNumber' => 1, 'status' => 'partial',
        ]);
        $this->router()->dispatch('track_series_season', [
            'seriesIsbn' => 'tt14452776', 'seasonNumber' => 3, 'status' => 'skipped',
        ]);

        $this->assertCount(0, $this->entradasDelDiario());
    }
}
