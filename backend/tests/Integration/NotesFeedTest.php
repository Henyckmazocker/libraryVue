<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * La regla que hace publicable este plan: **solo las notas públicas emiten**.
 *
 * Se prueba contra `feed_events` de verdad y **contando filas**, no mirando la
 * pantalla, porque el `Hecho cuando` del plan lo pide así con razón: una nota
 * privada no puede generar evento **ni siquiera uno sin texto**. Que no se vea
 * no basta — `show_notes` filtra al LEER, así que un evento emitido de más
 * quedaría ahí, invisible hoy y visible el día que alguien encienda el ajuste.
 */
class NotesFeedTest extends IntegrationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $stmt = $this->pdo()->prepare('INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)');
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Anotador']);
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

    private function eventosDeNota(): int
    {
        $stmt = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM feed_events WHERE user_id = :u AND event_type = 'notes_updated'"
        );
        $stmt->execute(['u' => $this->userId]);

        return (int) $stmt->fetchColumn();
    }

    /** Guarda una película en la biblioteca y devuelve su id de IMDb. */
    private function conPelicula(): string
    {
        $tconst = 'tt' . random_int(1000000, 9999999);

        $this->router()->dispatch('add_movie', ['movie' => [
            'isbn'         => $tconst,
            'imdbID'       => $tconst,
            'title'        => 'Una película de prueba',
            'userStatuses' => ['owned'],
        ]]);

        return $tconst;
    }

    #[Test]
    public function a_public_note_emits_one_feed_event(): void
    {
        $tconst = $this->conPelicula();
        $antes = $this->eventosDeNota();

        $r = $this->router()->dispatch('add_movie_note', [
            'movieIsbn' => $tconst,
            'noteText'  => 'Una nota que sí quiero compartir',
            'isPrivate' => false,
        ]);

        $this->assertSame('success', $r['status'], 'La nota tiene que guardarse');
        $this->assertSame($antes + 1, $this->eventosDeNota());
    }

    #[Test]
    public function a_private_note_emits_nothing_at_all(): void
    {
        // Lo que este plan promete que no puede romperse. Y es el caso POR
        // DEFECTO: `is_private` nace en 1 en las cinco tablas de notas.
        $tconst = $this->conPelicula();
        $antes = $this->eventosDeNota();

        $r = $this->router()->dispatch('add_movie_note', [
            'movieIsbn' => $tconst,
            'noteText'  => 'Esto no lo lee nadie',
            'isPrivate' => true,
        ]);

        $this->assertSame('success', $r['status'], 'La nota privada se guarda igual');
        $this->assertSame($antes, $this->eventosDeNota(), 'Una nota privada no emite NADA');
    }

    #[Test]
    public function the_event_carries_the_whole_note_and_the_title(): void
    {
        $tconst = $this->conPelicula();
        $texto = str_repeat('Un texto que la tarjeta recortará con CSS, no aquí. ', 10);

        $this->router()->dispatch('add_movie_note', [
            'movieIsbn' => $tconst,
            'noteText'  => $texto,
            'noteType'  => 'quote',
            'isPrivate' => false,
        ]);

        $stmt = $this->pdo()->prepare(
            "SELECT entity_type, entity_id, entity_title, metadata
               FROM feed_events
              WHERE user_id = :u AND event_type = 'notes_updated'
              ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['u' => $this->userId]);
        $fila = $stmt->fetch();

        $this->assertSame('movie', $fila['entity_type']);
        $this->assertSame($tconst, $fila['entity_id']);
        $this->assertSame('Una película de prueba', $fila['entity_title'], 'El título sale del repositorio de la entidad');

        $metadata = json_decode((string) $fila['metadata'], true);
        $this->assertSame($texto, $metadata['note_text'], 'El texto va entero, sin truncar');
        $this->assertSame('quote', $metadata['note_type']);
    }

    /**
     * El `Hecho cuando` del M4: **las cinco entidades**, una por una.
     *
     * Games y Albums estrenaron use case el 2026-08-25 justamente para esto:
     * iban del controlador al repositorio y no tenían dónde poner la guarda.
     */
    #[Test]
    public function all_five_media_emit_with_the_same_rule(): void
    {
        $antes = $this->eventosDeNota();
        $tipos = [];

        // ── película ──
        $tconst = $this->conPelicula();
        $this->router()->dispatch('add_movie_note', [
            'movieIsbn' => $tconst, 'noteText' => 'nota de peli', 'isPrivate' => false,
        ]);
        $tipos[] = 'movie';

        // ── libro ──
        $isbn = '9780000000019';
        $this->router()->dispatch('add_book', ['book' => [
            'isbn' => $isbn, 'title' => 'Un libro', 'author' => 'Alguien', 'userStatuses' => ['owned'],
        ]]);
        $edicion = (int) $this->pdo()->query(
            'SELECT id FROM user_book_editions WHERE user_id = ' . $this->userId . ' ORDER BY id DESC LIMIT 1'
        )->fetchColumn();
        $this->assertGreaterThan(0, $edicion, 'add_book tiene que dejar una fila en user_book_editions');
        $this->router()->dispatch('add_edition_note', [
            // `add_edition_note` exige \`pageNumber\` (routes.php:231): las notas de
            // libro van ancladas a una página, al revés que las otras cuatro.
            'userEditionId' => $edicion, 'pageNumber' => 42,
            'noteText' => 'nota de libro', 'isPrivate' => false,
        ]);
        $tipos[] = 'book';

        // ── juego ──
        $this->router()->dispatch('add_game', ['game' => [
            'id' => 4242, 'title' => 'Un juego', 'userStatuses' => ['owned'],
        ]]);
        $this->router()->dispatch('add_game_note', [
            'gameId' => 4242, 'noteText' => 'nota de juego', 'isPrivate' => false,
        ]);
        $tipos[] = 'game';

        // ── álbum ──
        // `AlbumId` valida la forma: MBID o base62 de Spotify. Un id inventado
        // sale por «Invalid album ID format».
        $mbid = '1f25d940-89e2-4813-a86f-955b0e99c391';
        $this->router()->dispatch('add_album', ['album' => [
            'id' => $mbid, 'spotify_id' => $mbid,
            'title' => 'Un álbum', 'name' => 'Un álbum', 'artist' => 'Alguien',
        ], 'statuses' => ['owned']]);
        $albumId = (int) $this->pdo()->query('SELECT id FROM albums ORDER BY id DESC LIMIT 1')->fetchColumn();
        $this->router()->dispatch('add_album_note', [
            'albumId' => $albumId, 'noteText' => 'nota de álbum', 'isPrivate' => false,
        ]);
        $tipos[] = 'album';

        // ── vídeo ──
        // `add_video` NO anida bajo 'video' (ActionRouter:556-558), al revés que
        // `add_movie`, `add_game` y `add_album`. Es una inconsistencia del
        // protocolo, no de este test.
        $this->router()->dispatch('add_video', [
            'youtubeId' => 'testVideo01', 'title' => 'Un vídeo', 'userStatuses' => ['owned'],
        ]);
        $this->router()->dispatch('add_video_note', [
            'youtubeId' => 'testVideo01', 'noteText' => 'nota de vídeo', 'isPrivate' => false,
        ]);
        $tipos[] = 'video';

        $emitidos = $this->pdo()->prepare(
            "SELECT entity_type FROM feed_events
              WHERE user_id = :u AND event_type = 'notes_updated' ORDER BY id"
        );
        $emitidos->execute(['u' => $this->userId]);
        $vistos = $emitidos->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertSame($antes + 5, $this->eventosDeNota(), 'Las cinco entidades emiten');
        sort($tipos);
        $ordenados = $vistos;
        sort($ordenados);
        $this->assertSame($tipos, $ordenados, 'Y cada una con su entity_type');
    }

    #[Test]
    public function the_rest_of_the_feed_keeps_working(): void
    {
        // Lo que no puede romper: añadir sigue generando su evento de siempre.
        $antes = (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM feed_events WHERE event_type = 'item_added'"
        )->fetchColumn();

        $this->conPelicula();

        $despues = (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM feed_events WHERE event_type = 'item_added'"
        )->fetchColumn();

        $this->assertSame($antes + 1, $despues);
    }
}
