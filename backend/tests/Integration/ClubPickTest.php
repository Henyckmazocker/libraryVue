<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PDO;
use PHPUnit\Framework\Attributes\Test;

/**
 * El M2 de los clubs: el ítem activo, el historial y el cierre automático.
 *
 * Va en su propio fichero y no en `ClubsTest.php` porque su `setUp` es otro:
 * aquí hace falta **biblioteca sembrada** —una película en `movie` y filas en
 * `user_movie_statuses`— para que la consulta de completado tenga algo que
 * contar. Sin eso no se puede probar lo único que de verdad importa de este
 * hito.
 *
 * El cierre automático se evalúa **al leer el club**, así que todos los tests
 * que lo comprueban lo hacen llamando a `get_club`: no hay cron que esperar
 * (`Infrastructure/Http/PostResponse.php:12`) y esa es exactamente la
 * semántica que se implementó.
 */
class ClubPickTest extends IntegrationTestCase
{
    private const PELICULA = 'tt0111161';

    private int $yo;
    private int $amiga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yo    = $this->crearUsuario('Dueño del club', 'david');
        $this->amiga = $this->crearUsuario('La otra miembro', 'ana');

        $this->crearPelicula(self::PELICULA, 'Cadena perpetua');
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Sembrado
    // ------------------------------------------------------------------

    private function crearUsuario(string $nombre, ?string $username = null): int
    {
        $sufijo = bin2hex(random_bytes(4));
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name, username) VALUES (:g, :e, :n, :u)'
        );
        $stmt->execute([
            'g' => 'g-' . $sufijo,
            'e' => $sufijo . '@ejemplo.test',
            'n' => $nombre,
            'u' => $username ?? 'u' . $sufijo,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function crearPelicula(string $imdbId, string $titulo): void
    {
        // `movie.isbn` guarda el id de IMDb y ES la PK: por eso las películas no
        // necesitan resolución previa en `MySqlClubProgressRepository`.
        $stmt = $this->pdo()->prepare(
            "INSERT INTO movie (isbn, title, media_type) VALUES (:i, :t, 'movie')"
        );
        $stmt->execute(['i' => $imdbId, 't' => $titulo]);
    }

    /** Marca la película con un estado, por NOMBRE — su id no es estable. */
    private function marcarPelicula(int $userId, string $estado): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_movie_statuses (user_id, movie_isbn, status_id)
             VALUES (:u, :m, (SELECT id FROM movie_statuses WHERE name = :e))
             ON DUPLICATE KEY UPDATE status_id = VALUES(status_id)'
        );
        $stmt->execute(['u' => $userId, 'm' => self::PELICULA, 'e' => $estado]);
    }

    private function hacerAmigos(int $unUsuario, int $otro): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT IGNORE INTO friendships (requester_id, addressee_id, status) VALUES (:a, :b, 'accepted')"
        );
        $stmt->execute(['a' => $unUsuario, 'b' => $otro]);
    }

    private function comoUsuario(int $userId): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $userId]);
    }

    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    /** Club del que `yo` es dueño, con `amiga` dentro. */
    private function clubDeDos(): int
    {
        $this->comoUsuario($this->yo);
        $clubId = (int) $this->router()->dispatch('create_club', ['name' => 'Los del jueves'])['data']['clubId'];

        $this->hacerAmigos($this->yo, $this->amiga);
        $r = $this->router()->dispatch('invite_to_club', ['clubId' => $clubId, 'userId' => $this->amiga]);

        $this->comoUsuario($this->amiga);
        $this->router()->dispatch('accept_club_invitation', [
            'recommendationId' => $r['data']['recommendationId'],
        ]);

        $this->comoUsuario($this->yo);

        return $clubId;
    }

    private function elegirPelicula(int $clubId): array
    {
        return $this->router()->dispatch('set_club_pick', [
            'clubId'      => $clubId,
            'entityType'  => 'movie',
            'entityId'    => self::PELICULA,
            'entityTitle' => 'Cadena perpetua',
        ]);
    }

    // ------------------------------------------------------------------
    // Elegir el ítem
    // ------------------------------------------------------------------

    #[Test]
    public function the_owner_can_set_the_active_item(): void
    {
        $clubId = $this->clubDeDos();

        $r = $this->elegirPelicula($clubId);
        $this->assertSame('success', $r['status'], json_encode($r));

        $club = $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $this->assertSame('movie', $club['data']['pick']['entity_type']);
        $this->assertSame(self::PELICULA, $club['data']['pick']['entity_id']);
        $this->assertSame('Cadena perpetua', $club['data']['pick']['entity_title']);
        $this->assertNull($club['data']['pick']['finished_at']);
        $this->assertSame([], $club['data']['history']);
    }

    #[Test]
    public function a_second_active_item_is_a_409(): void
    {
        $clubId = $this->clubDeDos();
        $this->elegirPelicula($clubId);

        $r = $this->elegirPelicula($clubId);

        // 409 y no 400: es un conflicto con el estado, no una petición mal
        // formada, y el frontend lo necesita distinguido.
        $this->assertSame('error', $r['status']);
        $this->assertSame(409, $r['http_code']);
    }

    #[Test]
    public function a_member_who_is_not_the_owner_cannot_set_the_item(): void
    {
        $clubId = $this->clubDeDos();

        $this->comoUsuario($this->amiga);
        $r = $this->elegirPelicula($clubId);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);
    }

    #[Test]
    public function series_is_not_a_valid_entity_type(): void
    {
        $clubId = $this->clubDeDos();

        // Una serie se guarda con `AddMovieUseCase` y viaja como `movie`. El
        // ENUM tiene cinco valores y `series` no es uno.
        $r = $this->router()->dispatch('set_club_pick', [
            'clubId' => $clubId, 'entityType' => 'series', 'entityId' => 'tt0903747',
        ]);

        $this->assertSame('error', $r['status']);
    }

    // ------------------------------------------------------------------
    // Terminar y el historial
    // ------------------------------------------------------------------

    #[Test]
    public function finishing_moves_the_item_to_history_and_frees_the_slot(): void
    {
        $clubId = $this->clubDeDos();
        $this->elegirPelicula($clubId);

        $r = $this->router()->dispatch('finish_club_pick', ['clubId' => $clubId]);
        $this->assertSame('success', $r['status'], json_encode($r));

        $club = $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $this->assertNull($club['data']['pick']);
        $this->assertCount(1, $club['data']['history']);
        $this->assertNotNull($club['data']['history'][0]['finished_at']);

        // Y el hueco queda libre: se puede elegir otro.
        $this->crearPelicula('tt0068646', 'El padrino');
        $r = $this->router()->dispatch('set_club_pick', [
            'clubId' => $clubId, 'entityType' => 'movie', 'entityId' => 'tt0068646',
        ]);
        $this->assertSame('success', $r['status'], json_encode($r));

        $club = $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $this->assertSame('tt0068646', $club['data']['pick']['entity_id']);
        $this->assertCount(1, $club['data']['history']);
    }

    #[Test]
    public function finishing_without_an_active_item_is_a_404(): void
    {
        $clubId = $this->clubDeDos();

        $r = $this->router()->dispatch('finish_club_pick', ['clubId' => $clubId]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(404, $r['http_code']);
    }

    #[Test]
    public function a_member_who_is_not_the_owner_cannot_finish(): void
    {
        $clubId = $this->clubDeDos();
        $this->elegirPelicula($clubId);

        $this->comoUsuario($this->amiga);
        $r = $this->router()->dispatch('finish_club_pick', ['clubId' => $clubId]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);
    }

    // ------------------------------------------------------------------
    // El cierre automático
    // ------------------------------------------------------------------

    #[Test]
    public function the_item_closes_itself_when_every_member_completed_it(): void
    {
        $clubId = $this->clubDeDos();
        $this->elegirPelicula($clubId);

        // Uno solo no basta: la regla es estricta a propósito.
        $this->marcarPelicula($this->yo, 'viewed');
        $club = $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $this->assertNotNull($club['data']['pick'], 'con uno de dos no debe cerrarse');

        // Con los dos, la SIGUIENTE lectura lo cierra. No hay cron: el club
        // avanza cuando alguien lo mira.
        $this->marcarPelicula($this->amiga, 'viewed');
        $club = $this->router()->dispatch('get_club', ['clubId' => $clubId]);

        $this->assertNull($club['data']['pick']);
        $this->assertCount(1, $club['data']['history']);
        $this->assertNotNull($club['data']['history'][0]['finished_at']);
    }

    #[Test]
    public function a_different_status_does_not_count_as_completed(): void
    {
        $clubId = $this->clubDeDos();
        $this->elegirPelicula($clubId);

        $this->marcarPelicula($this->yo, 'watching');
        $this->marcarPelicula($this->amiga, 'watching');

        $club = $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $this->assertNotNull($club['data']['pick']);
    }

    #[Test]
    public function a_member_who_never_added_the_item_blocks_the_auto_close(): void
    {
        $clubId = $this->clubDeDos();
        $this->elegirPelicula($clubId);

        // `amiga` no tiene la película en su biblioteca: no la completa nunca.
        $this->marcarPelicula($this->yo, 'viewed');

        $club = $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $this->assertNotNull($club['data']['pick'], 'quien no lo tiene bloquea el cierre');

        // Y por eso el cierre manual del dueño es la vía habitual, no la
        // excepción: es la única salida de este club.
        $this->router()->dispatch('finish_club_pick', ['clubId' => $clubId]);
        $club = $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $this->assertNull($club['data']['pick']);
    }

    #[Test]
    public function someone_joining_mid_item_restarts_the_countdown(): void
    {
        $clubId = $this->clubDeDos();
        $this->elegirPelicula($clubId);
        $this->marcarPelicula($this->yo, 'viewed');
        $this->marcarPelicula($this->amiga, 'viewed');

        // Antes de leer, entra un tercero que no la ha visto. No se compara
        // `joined_at` con `started_at`: cuenta como todos, así que invitar
        // reinicia de hecho la cuenta atrás del ítem en curso.
        $tercero = $this->crearUsuario('El que llega tarde', 'luis');
        $this->hacerAmigos($this->yo, $tercero);
        $r = $this->router()->dispatch('invite_to_club', ['clubId' => $clubId, 'userId' => $tercero]);
        $this->comoUsuario($tercero);
        $this->router()->dispatch('accept_club_invitation', [
            'recommendationId' => $r['data']['recommendationId'],
        ]);

        $club = $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $this->assertNotNull($club['data']['pick'], 'el recién llegado frena el cierre');

        $this->marcarPelicula($tercero, 'viewed');
        $club = $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $this->assertNull($club['data']['pick']);
    }

    #[Test]
    public function the_auto_close_writes_the_finish_date_only_once(): void
    {
        $clubId = $this->clubDeDos();
        $this->elegirPelicula($clubId);
        $this->marcarPelicula($this->yo, 'viewed');
        $this->marcarPelicula($this->amiga, 'viewed');

        $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $primera = $this->fechaDeCierre($clubId);

        // El `UPDATE` lleva `AND finished_at IS NULL`: leer cinco veces más no
        // puede pisar la fecha ya puesta.
        for ($i = 0; $i < 5; $i++) {
            $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        }

        $this->assertSame($primera, $this->fechaDeCierre($clubId));
    }

    private function fechaDeCierre(int $clubId): ?string
    {
        $stmt = $this->pdo()->prepare(
            'SELECT finished_at FROM club_pick WHERE club_id = :c ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['c' => $clubId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $fila['finished_at'] : null;
    }
}
