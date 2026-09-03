<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * `delete_movie` de punta a punta, y por integración a propósito.
 *
 * La acción estuvo rota con las dos suites en verde, y con **cuatro** defectos
 * apilados en los cuatro sitios que un mock sustituye: la ruta exigía `imdbID` **y**
 * `id` —su comentario decía «either», pero `ValidationMiddleware` pide todas—; el
 * cliente manda `movieIsbn`, que no era ninguna de las dos; `ActionRouter` construía
 * el comando con los argumentos en otro orden que el constructor; y el controller
 * leía `$command->movieId`, propiedad que no existe. Ninguno es visible desde un
 * unitario, porque los unitarios mockean justo esa costura.
 *
 * Es el mismo patrón que el `CLAUDE.md` ya documenta para `update_book_user_statuses`
 * y `get_books`: una acción que nadie llamaba desde la interfaz es una acción que
 * nadie sabe si funciona.
 */
class MovieDeleteTest extends IntegrationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)'
        );
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Espectadora']);
        $this->userId = (int) $this->pdo()->lastInsertId();

        // Por JWT y no por sesión, como en LibraryTest: así el pipeline omite el
        // CSRF y el test se concentra en los datos.
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

    private function guardar(string $imdbId, string $tipo = 'movie'): void
    {
        $alta = $this->router()->dispatch('add_movie', ['movie' => [
            'id'           => $imdbId,
            'title'        => 'Una película de prueba',
            'media_type'   => $tipo,
            'userStatuses' => ['owned'],
        ]]);

        $this->assertSame('success', $alta['status'], 'add_movie tiene que guardar');
    }

    private function estaGuardada(string $imdbId): bool
    {
        $r = $this->router()->dispatch('get_movies', []);
        $this->assertSame('success', $r['status']);

        foreach ($r['data'] ?? [] as $pelicula) {
            if (($pelicula['isbn'] ?? $pelicula['imdbID'] ?? null) === $imdbId) {
                return true;
            }
        }

        return false;
    }

    /**
     * `isbn` es la clave que manda el cliente: `createMediaStore.remove` la saca del
     * `store.idPayloadKey` de películas, que **no** es el `movieIsbn` del alta y los
     * estados —`mediaRegistry.js:890` lo avisa—. Antes respondía `VALIDATION_FAILED`.
     *
     * Esta prueba se escribió primero contra `movieIsbn` y pasó en verde con la app
     * todavía rota: el test estaba de acuerdo con la suposición, no con el cliente.
     * Lo destapó mirar la petición en el navegador.
     */
    #[Test]
    public function a_movie_is_deleted_with_the_key_the_client_sends(): void
    {
        $imdbId = 'tt7777771';
        $this->guardar($imdbId);
        $this->assertTrue($this->estaGuardada($imdbId), 'La película tiene que estar antes de borrarla');

        $r = $this->router()->dispatch('delete_movie', ['isbn' => $imdbId, 'itemType' => 'movie']);

        $this->assertSame('success', $r['status'], 'delete_movie con isbn tiene que borrar');
        $this->assertFalse($this->estaGuardada($imdbId), 'La película no puede seguir en la biblioteca');
    }

    /**
     * Una serie se guarda con `AddMovieUseCase` y su fila lleva `media_type = 'movie'`,
     * así que se borra por la misma acción. Es el caso que destapó el fallo.
     */
    #[Test]
    public function a_series_is_deleted_through_the_same_action(): void
    {
        $imdbId = 'tt7777772';
        $this->guardar($imdbId);

        $r = $this->router()->dispatch('delete_movie', ['isbn' => $imdbId, 'itemType' => 'movie']);

        $this->assertSame('success', $r['status']);
        $this->assertFalse($this->estaGuardada($imdbId));
    }

    /**
     * Las otras tres claves que acepta el comando siguen valiendo: quien llamara así
     * no puede romperse por el arreglo.
     */
    #[Test]
    public function the_older_identifier_keys_still_work(): void
    {
        foreach (['id' => 'tt7777773', 'imdbID' => 'tt7777774', 'movieIsbn' => 'tt7777775'] as $clave => $imdbId) {
            $this->guardar($imdbId);

            // La ruta exige `isbn`, así que viaja junto a la otra clave: lo que se
            // comprueba aquí es que el COMANDO sigue leyendo las cuatro.
            $r = $this->router()->dispatch('delete_movie', [
                'isbn' => $imdbId,
                $clave => $imdbId,
            ]);

            $this->assertSame('success', $r['status'], "delete_movie con `$clave` tiene que borrar");
            $this->assertFalse($this->estaGuardada($imdbId));
        }
    }

    /**
     * Sacar algo de tu biblioteca se lleva lo tuyo, y son TRES tablas.
     *
     * La FK de `user_movie_notes` y `user_series_seasons` apunta a `movie(isbn)` —el
     * catálogo, compartido y que NO se borra aquí—, así que su `ON DELETE CASCADE` no
     * salta por esta vía: hay que limpiarlas a mano. Hasta el 2026-09-03 no se hacía,
     * y volver a guardar la misma serie resucitaba el progreso viejo.
     *
     * Va por integración porque el fallo es exactamente lo que un mock de PDO tapa:
     * los unitarios verifican que se llamó al repositorio, no lo que el repositorio
     * dejó en la base.
     */
    #[Test]
    public function deleting_a_series_takes_its_seasons_and_notes_with_it(): void
    {
        // Como serie: `track_series_season` se niega sobre un `media_type = 'movie'`.
        $imdbId = 'tt7777776';
        $this->guardar($imdbId, 'series');

        $this->router()->dispatch('track_series_season', [
            'seriesIsbn' => $imdbId,
            'seasonNumber' => 1,
            'status' => 'viewed',
        ]);
        $this->router()->dispatch('add_movie_note', [
            'movieIsbn' => $imdbId,
            'noteText' => 'Una nota de prueba',
            'noteType' => 'note',
            'isPrivate' => true,
        ]);

        $this->assertSame(1, $this->contar('user_series_seasons', 'series_isbn', $imdbId), 'La temporada tiene que estar antes');
        $this->assertSame(1, $this->contar('user_movie_notes', 'movie_isbn', $imdbId), 'La nota tiene que estar antes');

        $r = $this->router()->dispatch('delete_movie', ['isbn' => $imdbId]);
        $this->assertSame('success', $r['status']);

        $this->assertSame(0, $this->contar('user_series_seasons', 'series_isbn', $imdbId), 'Las temporadas no pueden quedar huérfanas');
        $this->assertSame(0, $this->contar('user_movie_notes', 'movie_isbn', $imdbId), 'Las notas no pueden quedar huérfanas');
        $this->assertSame(0, $this->contar('user_movie_statuses', 'movie_isbn', $imdbId), 'Ni los estados, que ya se limpiaban');
    }

    private function contar(string $tabla, string $columna, string $id): int
    {
        $stmt = $this->pdo()->prepare("SELECT COUNT(*) FROM {$tabla} WHERE user_id = :u AND {$columna} = :i");
        $stmt->execute(['u' => $this->userId, 'i' => $id]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Sin identificador es culpa de quien llama, y se dice: antes moría en un
     * `TypeError` de `MovieIdentifier::fromString(null)`, o sea un 500.
     */
    #[Test]
    public function deleting_without_an_identifier_is_a_400_and_not_a_500(): void
    {
        $r = $this->router()->dispatch('delete_movie', []);

        $this->assertSame('error', $r['status']);
        $this->assertNotSame(500, $r['http_code'] ?? null, 'Falta un dato: es culpa de quien llama');
    }
}
