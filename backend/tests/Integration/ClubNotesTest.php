<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * `get_club_notes` y la regla de spoiler, mirando **el JSON**.
 *
 * Ese es el sentido entero de este fichero y del hito: la comprobación no puede
 * ser «no se ve en pantalla», porque difuminar con CSS un texto que está en el
 * DOM es enseñarlo. Lo que se afirma aquí es que el texto **no está en la
 * respuesta**.
 *
 * El escenario es el del plan: dos usuarios en un libro, A por la página 50 y B
 * por la 200; A ve la nota de B de la página 30 y **no recibe** el texto de la
 * de la 180.
 */
class ClubNotesTest extends IntegrationTestCase
{
    private const ISBN     = '9788401352836';
    private const PELICULA = 'tt0111161';

    private int $ana;   // va por la página 50
    private int $bruno; // va por la 200
    private int $clubId;
    private int $editionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ana   = $this->crearUsuario('Ana', 'ana');
        $this->bruno = $this->crearUsuario('Bruno', 'bruno');

        $this->editionId = $this->crearLibro();
        $this->clubId    = $this->clubDeDos();
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
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test',
                        'n' => $nombre, 'u' => $username]);

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
        $stmt->execute(['w' => $workId, 'k' => 'OL' . random_int(1000, 9999) . 'M',
                        'i' => self::ISBN, 't' => 'Un libro del club']);

        return (int) $this->pdo()->lastInsertId();
    }

    /** Pone al usuario en una página y devuelve su `user_edition_id`. */
    private function enLaPagina(int $userId, int $pagina, ?string $estado = null): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_book_editions (user_id, edition_id, current_page) VALUES (:u, :e, :p)'
        );
        $stmt->execute(['u' => $userId, 'e' => $this->editionId, 'p' => $pagina]);
        $userEditionId = (int) $this->pdo()->lastInsertId();

        if ($estado !== null) {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO user_book_statuses (user_edition_id, status_id)
                 VALUES (:ue, (SELECT id FROM book_statuses WHERE name = :e))'
            );
            $stmt->execute(['ue' => $userEditionId, 'e' => $estado]);
        }

        return $userEditionId;
    }

    private function escribirNota(int $userId, int $userEditionId, int $pagina, string $texto, bool $privada = false): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_edition_notes (user_id, user_edition_id, page_number, note_text, is_private)
             VALUES (:u, :ue, :p, :t, :priv)'
        );
        $stmt->execute(['u' => $userId, 'ue' => $userEditionId, 'p' => $pagina,
                        't' => $texto, 'priv' => $privada ? 1 : 0]);

        return (int) $this->pdo()->lastInsertId();
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

    private function clubDeDos(): int
    {
        $this->comoUsuario($this->ana);
        $clubId = (int) $this->router()->dispatch('create_club', ['name' => 'Club del libro'])['data']['clubId'];

        $stmt = $this->pdo()->prepare(
            "INSERT IGNORE INTO friendships (requester_id, addressee_id, status) VALUES (:a, :b, 'accepted')"
        );
        $stmt->execute(['a' => $this->ana, 'b' => $this->bruno]);

        $r = $this->router()->dispatch('invite_to_club', ['clubId' => $clubId, 'userId' => $this->bruno]);
        $this->comoUsuario($this->bruno);
        $this->router()->dispatch('accept_club_invitation', [
            'recommendationId' => $r['data']['recommendationId'],
        ]);
        $this->comoUsuario($this->ana);

        return $clubId;
    }

    private function elegirLibro(): void
    {
        $this->comoUsuario($this->ana);
        $r = $this->router()->dispatch('set_club_pick', [
            'clubId' => $this->clubId, 'entityType' => 'book', 'entityId' => self::ISBN,
        ]);
        $this->assertSame('success', $r['status'], json_encode($r));
    }

    /** @return array<int, array> las notas indexadas por `noteId` */
    private function notas(): array
    {
        $r = $this->router()->dispatch('get_club_notes', ['clubId' => $this->clubId]);
        $this->assertSame('success', $r['status'], json_encode($r));

        return array_column($r['data']['notes'], null, 'noteId');
    }

    // ------------------------------------------------------------------
    // La prueba que de verdad importa
    // ------------------------------------------------------------------

    #[Test]
    public function the_text_of_a_note_ahead_of_me_is_not_in_the_response(): void
    {
        $ueAna   = $this->enLaPagina($this->ana, 50);
        $ueBruno = $this->enLaPagina($this->bruno, 200);
        $this->elegirLibro();

        $atras    = $this->escribirNota($this->bruno, $ueBruno, 30,  'Empieza flojo');
        $adelante = $this->escribirNota($this->bruno, $ueBruno, 180, 'Muere el protagonista');

        $this->comoUsuario($this->ana);
        $notas = $this->notas();

        // La de la página 30 la ha pasado: la ve entera.
        $this->assertFalse($notas[$atras]['isSpoiler']);
        $this->assertSame('Empieza flojo', $notas[$atras]['text']);

        // La de la 180 la adelantaría: viaja marcada y SIN TEXTO.
        $this->assertTrue($notas[$adelante]['isSpoiler']);
        $this->assertNull($notas[$adelante]['text']);

        // Y no está en ninguna parte del sobre, ni siquiera en otra clave.
        $r = $this->router()->dispatch('get_club_notes', ['clubId' => $this->clubId]);
        $this->assertStringNotContainsString('Muere el protagonista', json_encode($r));

        // `atPoint` SÍ viaja: es lo que permite decir «hay una nota en la 180»
        // sin contar qué dice.
        $this->assertSame(180, $notas[$adelante]['atPoint']);
    }

    #[Test]
    public function the_one_ahead_sees_everything(): void
    {
        $ueAna   = $this->enLaPagina($this->ana, 50);
        $this->enLaPagina($this->bruno, 200);
        $this->elegirLibro();

        $nota = $this->escribirNota($this->ana, $ueAna, 40, 'Me encanta el arranque');

        $this->comoUsuario($this->bruno);
        $notas = $this->notas();

        $this->assertFalse($notas[$nota]['isSpoiler']);
        $this->assertSame('Me encanta el arranque', $notas[$nota]['text']);
    }

    #[Test]
    public function my_own_note_is_never_a_spoiler_to_me(): void
    {
        $ueAna = $this->enLaPagina($this->ana, 50);
        $this->elegirLibro();

        // La escribí en la 300 y ahora voy por la 50 — quizá la escribí en otra
        // lectura. Ocultármela sería esconderme mi propio texto.
        $mia = $this->escribirNota($this->ana, $ueAna, 300, 'Nota mía de la relectura');

        $this->comoUsuario($this->ana);
        $notas = $this->notas();

        $this->assertTrue($notas[$mia]['isMine']);
        $this->assertFalse($notas[$mia]['isSpoiler']);
        $this->assertSame('Nota mía de la relectura', $notas[$mia]['text']);
    }

    #[Test]
    public function a_private_note_never_reaches_the_club(): void
    {
        $this->enLaPagina($this->ana, 50);
        $ueBruno = $this->enLaPagina($this->bruno, 200);
        $this->elegirLibro();

        $this->escribirNota($this->bruno, $ueBruno, 10, 'Esto no lo lee nadie', privada: true);

        $this->comoUsuario($this->ana);
        $r = $this->router()->dispatch('get_club_notes', ['clubId' => $this->clubId]);

        // Ni marcada ni sin marcar: no existe para el club. El filtro está en el
        // `WHERE`, no en un `array_filter`.
        $this->assertSame([], $r['data']['notes']);
        $this->assertStringNotContainsString('Esto no lo lee nadie', json_encode($r));
    }

    #[Test]
    public function finishing_the_book_reveals_everything(): void
    {
        $this->enLaPagina($this->ana, 400, 'read');
        $ueBruno = $this->enLaPagina($this->bruno, 200);
        $this->elegirLibro();

        $nota = $this->escribirNota($this->bruno, $ueBruno, 180, 'Muere el protagonista');

        $this->comoUsuario($this->ana);
        $notas = $this->notas();

        // Completado desactiva la regla: ya no hay nada que destripar.
        $this->assertFalse($notas[$nota]['isSpoiler']);
        $this->assertSame('Muere el protagonista', $notas[$nota]['text']);
    }

    // ------------------------------------------------------------------
    // Un medio SIN eje: la regla es binaria
    // ------------------------------------------------------------------

    #[Test]
    public function on_a_movie_notes_stay_hidden_until_you_mark_it_viewed(): void
    {
        $stmt = $this->pdo()->prepare("INSERT INTO movie (isbn, title, media_type) VALUES (:i, :t, 'movie')");
        $stmt->execute(['i' => self::PELICULA, 't' => 'Cadena perpetua']);

        $this->comoUsuario($this->ana);
        $this->router()->dispatch('set_club_pick', [
            'clubId' => $this->clubId, 'entityType' => 'movie', 'entityId' => self::PELICULA,
        ]);

        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_movie_notes (user_id, movie_isbn, note_text, is_private)
             VALUES (:u, :m, :t, 0)'
        );
        $stmt->execute(['u' => $this->bruno, 'm' => self::PELICULA, 't' => 'El final es lo mejor']);
        $notaId = (int) $this->pdo()->lastInsertId();

        // Sin marcarla como vista: oculta, y sin `atPoint` que enseñar.
        $notas = $this->notas();
        $this->assertTrue($notas[$notaId]['isSpoiler']);
        $this->assertNull($notas[$notaId]['text']);
        $this->assertNull($notas[$notaId]['atPoint']);

        // Al marcarla `viewed`, llega entera.
        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_movie_statuses (user_id, movie_isbn, status_id)
             VALUES (:u, :m, (SELECT id FROM movie_statuses WHERE name = :e))'
        );
        $stmt->execute(['u' => $this->ana, 'm' => self::PELICULA, 'e' => 'viewed']);

        $notas = $this->notas();
        $this->assertFalse($notas[$notaId]['isSpoiler']);
        $this->assertSame('El final es lo mejor', $notas[$notaId]['text']);
    }

    // ------------------------------------------------------------------
    // Bordes y permiso
    // ------------------------------------------------------------------

    #[Test]
    public function without_an_active_item_there_are_no_notes_and_it_is_not_an_error(): void
    {
        $r = $this->router()->dispatch('get_club_notes', ['clubId' => $this->clubId]);

        $this->assertSame('success', $r['status']);
        $this->assertNull($r['data']['axis']);
        $this->assertSame([], $r['data']['notes']);
    }

    #[Test]
    public function a_non_member_cannot_read_the_notes(): void
    {
        $this->elegirLibro();
        $extranio = $this->crearUsuario('Nadie', 'nadie');
        $this->comoUsuario($extranio);

        $r = $this->router()->dispatch('get_club_notes', ['clubId' => $this->clubId]);
        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);
    }

    #[Test]
    public function a_note_from_someone_who_left_the_club_disappears(): void
    {
        $this->enLaPagina($this->ana, 50);
        $ueBruno = $this->enLaPagina($this->bruno, 200);
        $this->elegirLibro();
        $this->escribirNota($this->bruno, $ueBruno, 10, 'Voy comentando');

        $this->comoUsuario($this->ana);
        $this->assertCount(1, $this->notas());

        // Salir no borra sus notas —son suyas y viven en su biblioteca— pero
        // deja de aparecer en la pantalla del club: el filtro es el `JOIN` con
        // `club_member`, no un `array_filter` que haya que recordar.
        $this->comoUsuario($this->bruno);
        $this->router()->dispatch('leave_club', ['clubId' => $this->clubId]);

        $this->comoUsuario($this->ana);
        $this->assertSame([], $this->notas());
    }
}
