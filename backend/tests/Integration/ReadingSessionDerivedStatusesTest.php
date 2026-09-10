<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\ReadingSessionRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * `updateBookStatusesBasedOnSessions` AJUSTA los estados; ya no los reconstruye.
 *
 * Hasta el 2026-09-09 la función hacía
 * `array_intersect($currentStatuses, ['owned', 'want-to-buy'])` y añadía encima
 * lo que supiera de las sesiones, así que **cualquier** estado que no fuera de
 * propiedad desaparecía al apuntar progreso: `to-read`, `paused` y `abandoned`
 * se perdían sin que nadie los hubiera tocado. Sus dos invocadores son
 * `UpdateReadingProgressUseCase:97` y `:157`.
 *
 * Lo que este fichero fija son **las dos ramas**, que no significan lo mismo:
 *
 * - **Sin sesión activa** no hay estado de lectura que imponer, así que solo se
 *   retira lo que ha dejado de ser cierto (`reading`/`re-reading`). Ahí es donde
 *   estaba el defecto, y es donde `to-read`, `paused` y `abandoned` sobreviven.
 * - **Con sesión activa** `reading`/`re-reading` ocupan la ranura excluyente de
 *   estado de lectura y **desplazan** a quien la tuviera. Eso no es el defecto:
 *   es la regla del dominio, escrita en
 *   `MySqlUserBookEditionRepository::validateStatusLogic()`, que **lanza**
 *   `InvalidArgumentException` si le llegan dos de
 *   `['to-read','reading','re-reading','paused','abandoned']`. Por eso la rama
 *   activa hace `array_diff` del grupo entero.
 *
 * Por integración y no por unitario: lo que hay que comprobar es justamente que
 * el conjunto resultante **pasa por `validateStatusLogic` y se escribe**, y esa
 * validación vive en el repositorio de destino. Un mock de PDO no la ejecuta, y
 * un unitario con la validación mockeada daría verde con la app lanzando un
 * `RuntimeException` en mitad de `update_reading_progress`.
 */
class ReadingSessionDerivedStatusesTest extends IntegrationTestCase
{
    private const ISBN = '9780000000040';

    private const PAGINAS = 100;

    private int $userId;

    private int $editionId;

    protected function setUp(): void
    {
        parent::setUp();

        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)'
        );
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Lectora']);
        $this->userId = (int) $this->pdo()->lastInsertId();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $this->userId]);

        $this->guardarLibro();
        $this->editionId = $this->container()
            ->get(EditionRepositoryInterface::class)
            ->findByIsbn(self::ISBN)
            ->getEditionId();
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

    private function sesiones(): ReadingSessionRepositoryInterface
    {
        return $this->container()->get(ReadingSessionRepositoryInterface::class);
    }

    private function ediciones(): UserBookEditionRepositoryInterface
    {
        return $this->container()->get(UserBookEditionRepositoryInterface::class);
    }

    /** El checksum de ISBN-13 tiene que ser válido: el ValueObject lo valida. */
    private function guardarLibro(): void
    {
        $alta = $this->router()->dispatch('add_book', ['book' => [
            'isbn'         => self::ISBN,
            'title'        => 'Un libro con estados',
            'author'       => 'Autora de prueba',
            'pages'        => self::PAGINAS,
            'userStatuses' => ['owned'],
        ]]);

        $this->assertSame('success', $alta['status'], $alta['message'] ?? '');
    }

    /**
     * Se escribe por el mismo camino que usa la derivación —y que valida—, para
     * partir de un estado que la app admite de verdad.
     */
    private function ponerEstados(array $estados): void
    {
        $this->ediciones()->updateStatuses($this->userId, $this->editionId, $estados);
    }

    private function estados(): array
    {
        $estados = $this->ediciones()->getStatusesForEdition($this->userId, $this->editionId);
        sort($estados);

        return $estados;
    }

    private function derivar(): void
    {
        $this->sesiones()->updateBookStatusesBasedOnSessions($this->userId, self::ISBN);
    }

    private function abrirSesion(int $paginaInicial = 10): int
    {
        $sesion = $this->router()->dispatch('create_reading_session', [
            'isbn'      => self::ISBN,
            'startPage' => $paginaInicial,
        ]);
        $this->assertSame('success', $sesion['status'], $sesion['message'] ?? '');

        return (int) $sesion['data']['id'];
    }

    // ------------------------------------------------------------------
    // Rama SIN sesión activa: aquí estaba el defecto.
    // ------------------------------------------------------------------

    #[Test]
    public function without_an_active_session_to_read_and_owned_survive(): void
    {
        $this->ponerEstados(['owned', 'to-read']);

        $this->derivar();

        $this->assertSame(['owned', 'to-read'], $this->estados());
    }

    #[Test]
    public function without_an_active_session_paused_survives(): void
    {
        $this->ponerEstados(['owned', 'paused']);

        $this->derivar();

        $this->assertSame(['owned', 'paused'], $this->estados());
    }

    #[Test]
    public function without_an_active_session_abandoned_survives(): void
    {
        $this->ponerEstados(['owned', 'abandoned']);

        $this->derivar();

        // `estados()` ordena, y 'abandoned' va antes que 'owned'.
        $this->assertSame(['abandoned', 'owned'], $this->estados());
    }

    #[Test]
    public function closing_the_session_retires_reading_and_adds_read(): void
    {
        $sessionId = $this->abrirSesion();
        $this->ponerEstados(['owned', 'reading']);

        // Sesión cerrada: ya no hay activa, y `hasCompletedBook()` cuenta las
        // que tienen `end_date`, así que pasa a ser una lectura completada.
        $this->sesiones()->complete($sessionId, self::PAGINAS);

        $this->derivar();

        $this->assertSame(['owned', 'read'], $this->estados());
    }

    /**
     * La otra mitad del *Hecho cuando*: `read` es histórico y **no** participa
     * de la ranura excluyente, así que convive con `to-read` y con `owned`.
     */
    #[Test]
    public function a_completed_book_keeps_to_read_and_owned_and_gains_read(): void
    {
        $sessionId = $this->abrirSesion();
        $this->ponerEstados(['owned', 'to-read']);
        $this->sesiones()->complete($sessionId, self::PAGINAS);

        $this->derivar();

        $this->assertSame(['owned', 'read', 'to-read'], $this->estados());
    }

    // ------------------------------------------------------------------
    // Rama CON sesión activa: `reading` desplaza, y eso es correcto.
    // ------------------------------------------------------------------

    #[Test]
    public function with_an_active_session_reading_displaces_the_exclusive_group(): void
    {
        $this->abrirSesion();

        // Los tres comparten ranura con `reading`. Que los desplace no es el
        // defecto: es lo que `validateStatusLogic` obliga, y lo que un lector
        // espera —un libro que empiezas a leer deja de estar `to-read`—.
        foreach (['to-read', 'paused', 'abandoned'] as $anterior) {
            $this->ponerEstados(['owned', $anterior]);

            $this->derivar();

            $this->assertSame(
                ['owned', 'reading'],
                $this->estados(),
                "Con sesión activa, `reading` tiene que desplazar a `{$anterior}`"
            );
        }
    }

    // ------------------------------------------------------------------
    // El paseo entero, por donde entra la app de verdad.
    // ------------------------------------------------------------------

    /**
     * El caso que hizo abortar al primer intento de este hito: con el contrato
     * ingenuo —conservar `to-read` y añadir `reading`— la derivación de
     * `UpdateReadingProgressUseCase:97` lanzaría, y el libro quedaría con la
     * sesión recién abierta y **sin** progreso guardado.
     */
    #[Test]
    public function recording_progress_on_a_to_read_book_does_not_blow_up(): void
    {
        $this->ponerEstados(['owned', 'to-read']);

        $respuesta = $this->router()->dispatch('update_reading_progress', [
            'isbn'        => self::ISBN,
            'currentPage' => 30,
        ]);

        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');
        $this->assertSame(['owned', 'reading'], $this->estados());
    }

    /**
     * Y con la sesión ya abierta, llegar al 100 % conserva lo que no es suyo:
     * `to-read` y `owned` siguen, y se añade `read`.
     */
    #[Test]
    public function reaching_one_hundred_percent_keeps_the_statuses_it_does_not_own(): void
    {
        $sessionId = $this->abrirSesion();
        $this->ponerEstados(['owned', 'to-read']);

        $respuesta = $this->router()->dispatch('update_reading_progress', [
            'isbn'        => self::ISBN,
            'currentPage' => self::PAGINAS,
        ]);

        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');
        $this->assertTrue($respuesta['data']['isComplete'] ?? false, 'El 100 % tiene que cerrar');
        $this->assertSame(['owned', 'read', 'to-read'], $this->estados());

        $stmt = $this->pdo()->prepare('SELECT is_active FROM reading_sessions WHERE id = :id');
        $stmt->execute([':id' => $sessionId]);
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'La sesión queda cerrada');
    }
}
