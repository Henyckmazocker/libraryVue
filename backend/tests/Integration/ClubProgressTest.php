<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * `get_club_progress`: por dónde va cada miembro con el ítem activo.
 *
 * Prueba **los tres ejes** que midió el M0, porque son tres consultas distintas
 * y un mock de PDO no vería que una de ellas no casa con el esquema:
 *
 *  - `page`   — libros, vía `user_book_editions.current_page`
 *  - `season` — series, vía `user_series_seasons.season_number`
 *  - `null`   — los otros cuatro, marca binaria por estado
 *
 * El caso que más importa es el de la **serie**: su `club_pick` lleva
 * `entity_type = 'movie'` porque se guarda con `AddMovieUseCase`, así que el eje
 * solo se puede distinguir mirando `movie.media_type`. Es el sitio donde es
 * fácil pintar una marca binaria en algo que sí tiene eje.
 */
class ClubProgressTest extends IntegrationTestCase
{
    private const ISBN     = '9788401352836';
    private const PELICULA = 'tt0111161';
    private const SERIE    = 'tt0903747';

    private int $yo;
    private int $amiga;
    private int $tercero;
    private int $clubId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yo      = $this->crearUsuario('Dueño', 'david');
        $this->amiga   = $this->crearUsuario('La segunda', 'ana');
        $this->tercero = $this->crearUsuario('El tercero', 'luis');

        $this->clubId = $this->clubDeTres();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Sembrado
    // ------------------------------------------------------------------

    private function crearUsuario(string $nombre, string $username): int
    {
        $sufijo = bin2hex(random_bytes(4));
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name, username) VALUES (:g, :e, :n, :u)'
        );
        $stmt->execute([
            'g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test',
            'n' => $nombre, 'u' => $username,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function crearLibro(): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO book_works (openlibrary_work_key, title, authors) VALUES (:k, :t, '[]')"
        );
        $stmt->execute(['k' => 'OL' . random_int(1000, 9999) . 'W', 't' => 'Un libro del club']);
        $workId = (int) $this->pdo()->lastInsertId();

        $stmt = $this->pdo()->prepare(
            'INSERT INTO book_editions (work_id, openlibrary_edition_key, isbn_13, title, pages)
             VALUES (:w, :k, :i, :t, 400)'
        );
        $stmt->execute([
            'w' => $workId, 'k' => 'OL' . random_int(1000, 9999) . 'M',
            'i' => self::ISBN, 't' => 'Un libro del club',
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function crearPelicula(string $imdbId, string $mediaType): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO movie (isbn, title, media_type) VALUES (:i, :t, :m)'
        );
        $stmt->execute(['i' => $imdbId, 't' => 'Un título', 'm' => $mediaType]);
    }

    /** Pone al usuario en una página concreta del libro. */
    private function enLaPagina(int $userId, int $editionId, int $pagina, ?string $estado = null): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_book_editions (user_id, edition_id, current_page) VALUES (:u, :e, :p)'
        );
        $stmt->execute(['u' => $userId, 'e' => $editionId, 'p' => $pagina]);
        $userEditionId = (int) $this->pdo()->lastInsertId();

        if ($estado !== null) {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO user_book_statuses (user_edition_id, status_id)
                 VALUES (:ue, (SELECT id FROM book_statuses WHERE name = :e))'
            );
            $stmt->execute(['ue' => $userEditionId, 'e' => $estado]);
        }
    }

    private function vioTemporada(int $userId, int $temporada): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO user_series_seasons (user_id, series_isbn, season_number, status)
             VALUES (:u, :s, :n, 'viewed')"
        );
        $stmt->execute(['u' => $userId, 's' => self::SERIE, 'n' => $temporada]);
    }

    private function marcarPelicula(int $userId, string $imdbId, string $estado): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_movie_statuses (user_id, movie_isbn, status_id)
             VALUES (:u, :m, (SELECT id FROM movie_statuses WHERE name = :e))
             ON DUPLICATE KEY UPDATE status_id = VALUES(status_id)'
        );
        $stmt->execute(['u' => $userId, 'm' => $imdbId, 'e' => $estado]);
    }

    private function hacerAmigos(int $a, int $b): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT IGNORE INTO friendships (requester_id, addressee_id, status) VALUES (:a, :b, 'accepted')"
        );
        $stmt->execute(['a' => $a, 'b' => $b]);
    }

    private function comoUsuario(int $userId): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)->generate(['user_id' => $userId]);
    }

    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    private function clubDeTres(): int
    {
        $this->comoUsuario($this->yo);
        $clubId = (int) $this->router()->dispatch('create_club', ['name' => 'Club de tres'])['data']['clubId'];

        foreach ([$this->amiga, $this->tercero] as $invitado) {
            $this->hacerAmigos($this->yo, $invitado);
            $this->comoUsuario($this->yo);
            $r = $this->router()->dispatch('invite_to_club', ['clubId' => $clubId, 'userId' => $invitado]);
            $this->comoUsuario($invitado);
            $this->router()->dispatch('accept_club_invitation', [
                'recommendationId' => $r['data']['recommendationId'],
            ]);
        }

        $this->comoUsuario($this->yo);

        return $clubId;
    }

    private function elegir(string $tipo, string $id): void
    {
        $this->comoUsuario($this->yo);
        $r = $this->router()->dispatch('set_club_pick', [
            'clubId' => $this->clubId, 'entityType' => $tipo, 'entityId' => $id,
        ]);
        $this->assertSame('success', $r['status'], json_encode($r));
    }

    /** @return array<int, array> el progreso indexado por `user_id` */
    private function progreso(): array
    {
        $r = $this->router()->dispatch('get_club_progress', ['clubId' => $this->clubId]);
        $this->assertSame('success', $r['status'], json_encode($r));

        return [
            'axis'    => $r['data']['axis'],
            'members' => array_column($r['data']['members'], null, 'user_id'),
        ];
    }

    // ------------------------------------------------------------------
    // Eje `page` — el caso del *Hecho cuando* del M3
    // ------------------------------------------------------------------

    #[Test]
    public function three_members_on_a_book_show_three_different_positions(): void
    {
        $editionId = $this->crearLibro();
        $this->elegir('book', self::ISBN);

        $this->enLaPagina($this->yo, $editionId, 50);
        $this->enLaPagina($this->amiga, $editionId, 200);
        // `tercero` no lo tiene en su biblioteca.

        $p = $this->progreso();

        $this->assertSame('page', $p['axis']);
        $this->assertCount(3, $p['members']);
        $this->assertSame(50,  $p['members'][$this->yo]['point']);
        $this->assertSame(200, $p['members'][$this->amiga]['point']);

        // Quien no lo tiene SALE, con `point: null`. Ocultarlo escondería justo
        // al miembro que bloquea el cierre automático.
        $this->assertNull($p['members'][$this->tercero]['point']);
        $this->assertFalse($p['members'][$this->tercero]['completed']);
    }

    #[Test]
    public function the_position_updates_when_a_member_advances(): void
    {
        $editionId = $this->crearLibro();
        $this->elegir('book', self::ISBN);
        $this->enLaPagina($this->yo, $editionId, 50);

        $this->assertSame(50, $this->progreso()['members'][$this->yo]['point']);

        $stmt = $this->pdo()->prepare(
            'UPDATE user_book_editions SET current_page = 180 WHERE user_id = :u AND edition_id = :e'
        );
        $stmt->execute(['u' => $this->yo, 'e' => $editionId]);

        $this->assertSame(180, $this->progreso()['members'][$this->yo]['point']);
    }

    #[Test]
    public function a_book_marked_read_is_completed(): void
    {
        $editionId = $this->crearLibro();
        $this->elegir('book', self::ISBN);
        $this->enLaPagina($this->yo, $editionId, 400, 'read');
        $this->enLaPagina($this->amiga, $editionId, 10, 'reading');

        $p = $this->progreso();
        $this->assertTrue($p['members'][$this->yo]['completed']);
        $this->assertFalse($p['members'][$this->amiga]['completed']);
    }

    // ------------------------------------------------------------------
    // Eje `season` — la serie que viaja como `movie`
    // ------------------------------------------------------------------

    #[Test]
    public function a_series_uses_the_season_axis_even_though_it_travels_as_movie(): void
    {
        $this->crearPelicula(self::SERIE, 'series');
        $this->elegir('movie', self::SERIE);

        $this->vioTemporada($this->yo, 1);
        $this->vioTemporada($this->yo, 2);
        $this->vioTemporada($this->amiga, 1);

        $p = $this->progreso();

        // El `entity_type` es 'movie'; el eje solo sale de `movie.media_type`.
        $this->assertSame('season', $p['axis']);
        $this->assertSame(2, $p['members'][$this->yo]['point']);
        $this->assertSame(1, $p['members'][$this->amiga]['point']);
        $this->assertNull($p['members'][$this->tercero]['point']);
    }

    #[Test]
    public function the_season_point_is_the_highest_seen_not_the_count(): void
    {
        $this->crearPelicula(self::SERIE, 'series');
        $this->elegir('movie', self::SERIE);

        // Ve la 1 y la 3, saltándose la 2: está en la 3, no en «dos vistas».
        $this->vioTemporada($this->yo, 1);
        $this->vioTemporada($this->yo, 3);

        $this->assertSame(3, $this->progreso()['members'][$this->yo]['point']);
    }

    #[Test]
    public function a_series_marked_viewed_is_completed(): void
    {
        $this->crearPelicula(self::SERIE, 'series');
        $this->elegir('movie', self::SERIE);
        $this->marcarPelicula($this->yo, self::SERIE, 'viewed');

        // Sale de `user_movie_statuses`, la MISMA fuente que usa el cierre
        // automático: si saliera de las temporadas, la pantalla diría «no lo ha
        // acabado» de alguien a quien el cierre ya cuenta como acabado.
        $p = $this->progreso();
        $this->assertTrue($p['members'][$this->yo]['completed']);
        $this->assertFalse($p['members'][$this->amiga]['completed']);
    }

    // ------------------------------------------------------------------
    // Sin eje — los otros cuatro medios
    // ------------------------------------------------------------------

    #[Test]
    public function a_movie_has_no_axis_and_only_a_binary_mark(): void
    {
        $this->crearPelicula(self::PELICULA, 'movie');
        $this->elegir('movie', self::PELICULA);
        $this->marcarPelicula($this->yo, self::PELICULA, 'viewed');
        $this->marcarPelicula($this->amiga, self::PELICULA, 'watching');

        $p = $this->progreso();

        $this->assertNull($p['axis']);
        $this->assertTrue($p['members'][$this->yo]['completed']);
        $this->assertFalse($p['members'][$this->amiga]['completed']);
        $this->assertNull($p['members'][$this->yo]['point']);
    }

    // ------------------------------------------------------------------
    // Bordes y permiso
    // ------------------------------------------------------------------

    #[Test]
    public function without_an_active_item_the_progress_is_empty_and_not_an_error(): void
    {
        $r = $this->router()->dispatch('get_club_progress', ['clubId' => $this->clubId]);

        // Es el estado normal entre un ítem y el siguiente, no un fallo.
        $this->assertSame('success', $r['status']);
        $this->assertNull($r['data']['axis']);
        $this->assertSame([], $r['data']['members']);
    }

    #[Test]
    public function a_non_member_cannot_read_the_progress(): void
    {
        $this->crearPelicula(self::PELICULA, 'movie');
        $this->elegir('movie', self::PELICULA);

        $extranio = $this->crearUsuario('Nadie de aquí', 'nadie');
        $this->comoUsuario($extranio);

        $r = $this->router()->dispatch('get_club_progress', ['clubId' => $this->clubId]);
        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);
    }

    #[Test]
    public function every_member_shows_up_even_with_nothing_recorded(): void
    {
        $this->crearLibro();
        $this->elegir('book', self::ISBN);

        $p = $this->progreso();

        $this->assertCount(3, $p['members']);
        foreach ($p['members'] as $miembro) {
            $this->assertNull($miembro['point']);
            $this->assertFalse($miembro['completed']);
            $this->assertNotSame('', $miembro['username']);
        }
    }
}
