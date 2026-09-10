<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Las cinco acciones `update_*_rating`, de punta a punta y por integración a propósito.
 *
 * Es la misma lección que este repo ya pagó con `delete_movie` y que fijó
 * `MovieDeleteTest`: los ~1300 tests unitarios llevaban **meses en verde** con
 * `update_book_rating` muerta, porque un unitario del comando lo construye
 * **bien** y jamás pasa por `ActionRouter`. El defecto vivía justo en esa
 * costura —`ActionRouter.php:306` lo construía posicional, `(int, string, mixed)`
 * contra un constructor `(ISBN, int, ?Rating)`—, que es exactamente lo que un
 * mock sustituye.
 *
 * Por eso se entra por `ActionRouter::dispatch`, como el resto de la suite de
 * integración: cubre `config/routes.php` + la pila de middleware + el `match`
 * del router + el use case + el repositorio + el SQL contra el esquema real.
 *
 * Tres casos por medio, quince en total:
 *   1. valoración válida contra un ítem que está en la biblioteca;
 *   2. `rating: 0`, que **borra** la valoración (`NULL` en la columna, 200);
 *   3. identificador ausente, que tiene que salir por `VALIDATION_FAILED` desde
 *      `ValidationMiddleware` y **no** por un warning de índice indefinido —que
 *      en dev se imprime como HTML antes del JSON y es lo que tumbó
 *      `update_movie_user_statuses`—.
 *
 * ⚠ **La siembra del caso 2 no es caprichosa.** `MySqlUserGameRepository::updateRating`
 * y `MySqlUserMovieRepository::updateRating` lanzan `RuntimeException` cuando
 * `rowCount() === 0`, y MySQL devuelve 0 cuando el `UPDATE` no cambia nada: borrar
 * una valoración que ya era `NULL` reventaría. Así que el borrado se prueba
 * **después** de valorar, sobre un valor previo que existe, que es además lo que
 * hace la ficha de verdad.
 */
class RatingActionsTest extends IntegrationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)'
        );
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Quien valora']);
        $this->userId = (int) $this->pdo()->lastInsertId();

        // Por JWT y no por sesión, como en `LibraryTest` y `MovieDeleteTest`: así
        // el pipeline omite el CSRF y estos tests se concentran en la costura
        // payload → ruta → comando → columna, que es lo que vienen a probar.
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

    /**
     * Despacha comprobando que **no se imprime nada** por el camino.
     *
     * Un warning de índice indefinido no rompe la respuesta: la ensucia,
     * escupiendo HTML antes del JSON. `assertSame('', $ruido)` es la forma de
     * afirmar «y no un warning» del hito, y `failOnWarning="true"`
     * (`phpunit.xml:7`) cubre el resto.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function despachar(string $accion, array $payload): array
    {
        ob_start();
        try {
            $respuesta = $this->router()->dispatch($accion, $payload);
        } finally {
            $ruido = (string) ob_get_clean();
        }

        $this->assertSame('', $ruido, "`$accion` no puede imprimir nada antes del JSON");

        return $respuesta;
    }

    // =========================================================================
    // Siembra: un ítem por medio, con la forma exacta que acepta cada alta
    // =========================================================================

    /** El ISBN lleva checksum ISBN-13 válido: el ValueObject lo valida. */
    private function sembrarLibro(string $isbn = '9780000000019'): string
    {
        $alta = $this->despachar('add_book', ['book' => [
            'isbn'         => $isbn,
            'title'        => 'Un libro valorable',
            'author'       => 'Autora de prueba',
            'userStatuses' => ['owned'],
        ]]);
        $this->assertSame('success', $alta['status'], 'add_book tiene que guardar');

        return $isbn;
    }

    private function sembrarPelicula(string $imdbId = 'tt6660001'): string
    {
        $alta = $this->despachar('add_movie', ['movie' => [
            'id'           => $imdbId,
            'title'        => 'Una película valorable',
            'media_type'   => 'movie',
            'userStatuses' => ['owned'],
        ]]);
        $this->assertSame('success', $alta['status'], 'add_movie tiene que guardar');

        return $imdbId;
    }

    private function sembrarJuego(int $gameId = 666001): int
    {
        $alta = $this->despachar('add_game', ['game' => [
            'id'       => $gameId,
            'title'    => 'Un juego valorable',
            'statuses' => ['owned'],
        ]]);
        $this->assertSame('success', $alta['status'], 'add_game tiene que guardar');

        return $gameId;
    }

    /**
     * `AddAlbumCommand::fromArray` NO lee `id`: la identidad llega por
     * `mb_release_group_gid`, y con forma de MBID porque `AlbumId` valida el
     * formato. El id que luego pide `update_album_rating` es el **autoincremental**
     * de `albums`, no el MBID.
     */
    private function sembrarAlbum(string $mbid = '66600001-2222-3333-4444-555555555555'): int
    {
        $alta = $this->despachar('add_album', ['album' => [
            'mb_release_group_gid' => $mbid,
            'title'                => 'Un álbum valorable',
            'artist'               => 'Alguien',
            'statuses'             => ['owned'],
        ]]);
        $this->assertSame('success', $alta['status'], 'add_album tiene que guardar');

        $albumId = (int) $this->pdo()
            ->query("SELECT id FROM albums WHERE title = 'Un álbum valorable' LIMIT 1")
            ->fetchColumn();
        $this->assertGreaterThan(0, $albumId, 'add_album tiene que dejar la fila del catálogo');

        return $albumId;
    }

    /** `add_video` NO anida bajo 'video', al revés que las otras tres altas. */
    private function sembrarVideo(string $youtubeId = 'ratingVid01'): string
    {
        $alta = $this->despachar('add_video', [
            'youtubeId' => $youtubeId,
            'title'     => 'Un vídeo valorable',
            'statuses'  => ['watched'],
        ]);
        $this->assertSame('success', $alta['status'], 'add_video tiene que guardar');

        return $youtubeId;
    }

    // =========================================================================
    // Lectura de la columna: es lo único que demuestra que el `null` llegó
    // =========================================================================

    /**
     * La columna que la ficha ENSEÑA es `edition_rating`: `UserBookEdition::toArray()`
     * la publica como `user_rating`, `personal_rating` y `rating`
     * (`UserBookEdition.php:239-243`). Hasta el 2026-09-09 este helper miraba
     * `work_rating`, así que los tests de libro pasaban en verde mientras la ficha se
     * quedaba en blanco al recargar: se afirmaba sobre la columna equivocada.
     */
    private function valoracionDeLibro(): ?float
    {
        $stmt = $this->pdo()->prepare(
            'SELECT edition_rating FROM user_book_editions WHERE user_id = :u ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['u' => $this->userId]);
        $valor = $stmt->fetchColumn();

        $this->assertNotFalse($valor, 'Tiene que existir la fila de user_book_editions');

        return $valor === null ? null : (float) $valor;
    }

    private function valoracion(string $tabla, string $columna, string|int $id): ?float
    {
        $stmt = $this->pdo()->prepare(
            "SELECT personal_rating FROM {$tabla} WHERE user_id = :u AND {$columna} = :i"
        );
        $stmt->execute(['u' => $this->userId, 'i' => $id]);
        $valor = $stmt->fetchColumn();

        $this->assertNotFalse($valor, "Tiene que existir la fila de {$tabla}");

        return $valor === null ? null : (float) $valor;
    }

    // =========================================================================
    // 1. Valoración válida — el caso que `update_book_rating` no pasaba
    // =========================================================================

    /**
     * Es **el** test del hito. Con la construcción posicional de antes
     * (`new UpdateBookRatingCommand($userId, $data['isbn'] ?? '', ...)`) muere en
     * un `TypeError` porque al parámetro `ISBN` le llega el `int` del usuario, y
     * el `TypeError` no lo captura el `catch (\Exception)` de `dispatch`.
     */
    #[Test]
    public function a_book_in_the_library_can_be_rated(): void
    {
        $isbn = $this->sembrarLibro();

        $r = $this->despachar('update_book_rating', ['isbn' => $isbn, 'rating' => 4.5]);

        $this->assertSame('success', $r['status'], 'update_book_rating tiene que valorar');
        $this->assertSame(4.5, $this->valoracionDeLibro());
        // Los cinco medios responden con el mismo texto desde el 2026-09-09: libro
        // devolvía `Rating updated for ISBN <isbn>` y se alineó con los otros cuatro.
        $this->assertSame('Book rating updated successfully.', $r['message']);
    }

    /**
     * La otra mitad del defecto del 2026-09-09: el UPDATE del repositorio escribe las
     * dos columnas de una vez, así que la acción borraba `work_rating` —la valoración
     * de la OBRA, otro concepto del modelo Work/Edition— cada vez que se valoraba la
     * edición desde la ficha.
     */
    #[Test]
    public function rating_a_book_does_not_wipe_the_work_rating(): void
    {
        $isbn = $this->sembrarLibro();

        $pdo = $this->pdo();
        $pdo->exec('UPDATE user_book_editions SET work_rating = 2.5 WHERE user_id = ' . $this->userId);

        $this->despachar('update_book_rating', ['isbn' => $isbn, 'rating' => 4.5]);

        $this->assertSame(4.5, $this->valoracionDeLibro(), 'La edición recibe la valoración');
        $stmt = $pdo->prepare('SELECT work_rating FROM user_book_editions WHERE user_id = :u ORDER BY id DESC LIMIT 1');
        $stmt->execute(['u' => $this->userId]);
        $this->assertSame(2.5, (float) $stmt->fetchColumn(), 'La valoración de la obra se conserva');
    }

    #[Test]
    public function a_movie_in_the_library_can_be_rated(): void
    {
        $imdbId = $this->sembrarPelicula();

        $r = $this->despachar('update_movie_rating', ['isbn' => $imdbId, 'rating' => 4]);

        $this->assertSame('success', $r['status'], 'update_movie_rating tiene que valorar');
        $this->assertSame(4.0, $this->valoracion('user_movies', 'movie_isbn', $imdbId));
    }

    #[Test]
    public function a_game_in_the_library_can_be_rated(): void
    {
        $gameId = $this->sembrarJuego();

        $r = $this->despachar('update_game_rating', ['gameId' => $gameId, 'rating' => 3.5]);

        $this->assertSame('success', $r['status'], 'update_game_rating tiene que valorar');
        $this->assertSame(3.5, $this->valoracion('user_games', 'game_id', $gameId));
    }

    #[Test]
    public function an_album_in_the_library_can_be_rated(): void
    {
        $albumId = $this->sembrarAlbum();

        $r = $this->despachar('update_album_rating', ['albumId' => $albumId, 'rating' => 5]);

        $this->assertSame('success', $r['status'], 'update_album_rating tiene que valorar');
        $this->assertSame(5.0, $this->valoracion('user_albums', 'album_id', $albumId));
    }

    #[Test]
    public function a_video_in_the_library_can_be_rated(): void
    {
        $youtubeId = $this->sembrarVideo();
        $videoId = (int) $this->pdo()
            ->query("SELECT id FROM videos WHERE youtube_id = 'ratingVid01' LIMIT 1")
            ->fetchColumn();

        $r = $this->despachar('update_video_rating', ['youtubeId' => $youtubeId, 'rating' => 2]);

        $this->assertSame('success', $r['status'], 'update_video_rating tiene que valorar');
        $this->assertSame(2.0, $this->valoracion('user_videos', 'video_id', $videoId));
    }

    // =========================================================================
    // 2. `rating: 0` borra la valoración — el `null` tiene que llegar a la columna
    // =========================================================================

    /**
     * Antes del 2026-09-09 el 0 era un `TypeError` en libro y película (un `null`
     * contra un parámetro `Rating` no nulable) y una `InvalidArgumentException` en
     * los otros tres: cinco comportamientos para el mismo dato. Ahora significa
     * «borra mi valoración» en los cinco.
     */
    #[Test]
    public function rating_a_book_with_zero_clears_it(): void
    {
        $isbn = $this->sembrarLibro();

        $this->despachar('update_book_rating', ['isbn' => $isbn, 'rating' => 3]);
        $this->assertSame(3.0, $this->valoracionDeLibro(), 'Primero tiene que haber algo que borrar');

        $r = $this->despachar('update_book_rating', ['isbn' => $isbn, 'rating' => 0]);

        $this->assertSame('success', $r['status'], 'Borrar la valoración es un éxito, no un error');
        $this->assertNull($this->valoracionDeLibro(), 'El 0 tiene que dejar la columna a NULL');
    }

    #[Test]
    public function rating_a_movie_with_zero_clears_it(): void
    {
        $imdbId = $this->sembrarPelicula();

        $this->despachar('update_movie_rating', ['isbn' => $imdbId, 'rating' => 3]);
        $this->assertSame(3.0, $this->valoracion('user_movies', 'movie_isbn', $imdbId));

        $r = $this->despachar('update_movie_rating', ['isbn' => $imdbId, 'rating' => 0]);

        $this->assertSame('success', $r['status']);
        $this->assertNull($this->valoracion('user_movies', 'movie_isbn', $imdbId));
    }

    #[Test]
    public function rating_a_game_with_zero_clears_it(): void
    {
        $gameId = $this->sembrarJuego();

        $this->despachar('update_game_rating', ['gameId' => $gameId, 'rating' => 3]);
        $this->assertSame(3.0, $this->valoracion('user_games', 'game_id', $gameId));

        $r = $this->despachar('update_game_rating', ['gameId' => $gameId, 'rating' => 0]);

        $this->assertSame('success', $r['status']);
        $this->assertNull($this->valoracion('user_games', 'game_id', $gameId));
    }

    #[Test]
    public function rating_an_album_with_zero_clears_it(): void
    {
        $albumId = $this->sembrarAlbum();

        $this->despachar('update_album_rating', ['albumId' => $albumId, 'rating' => 3]);
        $this->assertSame(3.0, $this->valoracion('user_albums', 'album_id', $albumId));

        $r = $this->despachar('update_album_rating', ['albumId' => $albumId, 'rating' => 0]);

        $this->assertSame('success', $r['status']);
        $this->assertNull($this->valoracion('user_albums', 'album_id', $albumId));
    }

    #[Test]
    public function rating_a_video_with_zero_clears_it(): void
    {
        $youtubeId = $this->sembrarVideo();
        $videoId = (int) $this->pdo()
            ->query("SELECT id FROM videos WHERE youtube_id = 'ratingVid01' LIMIT 1")
            ->fetchColumn();

        $this->despachar('update_video_rating', ['youtubeId' => $youtubeId, 'rating' => 3]);
        $this->assertSame(3.0, $this->valoracion('user_videos', 'video_id', $videoId));

        $r = $this->despachar('update_video_rating', ['youtubeId' => $youtubeId, 'rating' => 0]);

        $this->assertSame('success', $r['status']);
        $this->assertNull($this->valoracion('user_videos', 'video_id', $videoId));
    }

    // =========================================================================
    // 3. Identificador ausente — `VALIDATION_FAILED`, y no un warning
    // =========================================================================

    /**
     * El `rating` **sí** viaja: lo que falta es solo el identificador, así que el
     * único campo que puede reclamar `ValidationMiddleware` es ese. Es la
     * regresión de `update_movie_rating`, cuya ruta declaraba `['rating']` a secas
     * y dejaba pasar el payload hasta el último `??` sin default del `fromArray`.
     *
     * Se comprueba el `code`, no el `message`: `VALIDATION_FAILED` solo lo emite
     * `ValidationMiddleware.php:60`, o sea que la petición murió **antes** de
     * construir el comando. Un warning de índice indefinido no llega hasta aquí.
     *
     * @param string $accion
     * @param string $clave  La clave de identificador que la ruta exige
     */
    #[Test]
    public function rating_a_book_without_an_identifier_is_a_validation_error(): void
    {
        $this->assertFaltaElIdentificador('update_book_rating', 'isbn');
    }

    #[Test]
    public function rating_a_movie_without_an_identifier_is_a_validation_error(): void
    {
        $this->assertFaltaElIdentificador('update_movie_rating', 'isbn');
    }

    #[Test]
    public function rating_a_game_without_an_identifier_is_a_validation_error(): void
    {
        $this->assertFaltaElIdentificador('update_game_rating', 'gameId');
    }

    #[Test]
    public function rating_an_album_without_an_identifier_is_a_validation_error(): void
    {
        $this->assertFaltaElIdentificador('update_album_rating', 'albumId');
    }

    #[Test]
    public function rating_a_video_without_an_identifier_is_a_validation_error(): void
    {
        $this->assertFaltaElIdentificador('update_video_rating', 'youtubeId');
    }

    // =========================================================================
    // 4. Revalorar con el mismo número — la trampa de `rowCount()`
    // =========================================================================

    /**
     * Hasta el 2026-09-09, `MySqlUserMovieRepository:390` y
     * `MySqlUserGameRepository:425` lanzaban `RuntimeException` si el `UPDATE`
     * devolvía `rowCount() === 0`. Como `DatabaseConnector.php:115-117` no activa
     * `PDO::MYSQL_ATTR_FOUND_ROWS`, ese contador son las filas **cambiadas**, así
     * que reescribir el valor que la fila ya tenía contaba como cero y reventaba.
     *
     * No se notaba porque la ficha valoraba por `editUser*`, que escribe con
     * `edit()`; el M3 la pasa a `update_*_rating`, y ahí repulsar la estrella ya
     * marcada habría dado un 500. Álbum, libro y vídeo nunca tuvieron la guarda.
     */
    #[Test]
    public function rating_a_movie_twice_with_the_same_number_is_not_an_error(): void
    {
        $imdbId = $this->sembrarPelicula();

        $this->despachar('update_movie_rating', ['isbn' => $imdbId, 'rating' => 4]);
        $r = $this->despachar('update_movie_rating', ['isbn' => $imdbId, 'rating' => 4]);

        $this->assertSame('success', $r['status'], 'Repulsar la misma estrella no puede ser un error');
        $this->assertSame(4.0, $this->valoracion('user_movies', 'movie_isbn', $imdbId));
    }

    #[Test]
    public function rating_a_game_twice_with_the_same_number_is_not_an_error(): void
    {
        $gameId = $this->sembrarJuego();

        $this->despachar('update_game_rating', ['gameId' => $gameId, 'rating' => 3.5]);
        $r = $this->despachar('update_game_rating', ['gameId' => $gameId, 'rating' => 3.5]);

        $this->assertSame('success', $r['status'], 'Repulsar la misma estrella no puede ser un error');
        $this->assertSame(3.5, $this->valoracion('user_games', 'game_id', $gameId));
    }

    private function assertFaltaElIdentificador(string $accion, string $clave): void
    {
        $r = $this->despachar($accion, ['rating' => 4]);

        $this->assertSame('error', $r['status'], "`$accion` sin identificador no puede responder éxito");
        $this->assertSame(
            'VALIDATION_FAILED',
            $r['code'] ?? null,
            "`$accion` tiene que morir en ValidationMiddleware, no abajo"
        );
        $this->assertSame(400, $r['http_code'] ?? null, 'Falta un dato: es culpa de quien llama');
        $this->assertStringContainsString($clave, $r['message'] ?? '', "El aviso tiene que nombrar `$clave`");
    }
}
