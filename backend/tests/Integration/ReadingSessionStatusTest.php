<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Repository\Book\ReadingSessionRepositoryInterface;
use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * El estado manda: cambiar el estado de un libro cierra su sesión de lectura.
 *
 * Hasta el 2026-09-09 `UpdateBookUserStatusesUseCase` no mencionaba las
 * sesiones: quien las cerraba era `UpdateReadingProgressUseCase`, y solo al
 * llegar al 100 % de páginas. Marcar «leído» a mano dejaba la sesión abierta
 * aunque el desplegable prometiera lo contrario.
 *
 * Por integración y no por unitario porque el hito cruza el use case, dos
 * repositorios y el esquema: la página que se conserva en `end_page` sale de
 * `user_book_editions.current_page` resuelta por el modelo Work/Edition, y
 * «abandonada» no es una columna sino la nota `[ABANDONED] …` que escribe
 * `MySqlReadingSessionRepository::abandon()`. Un mock de PDO no ve ninguna de
 * las dos cosas.
 */
class ReadingSessionStatusTest extends IntegrationTestCase
{
    private const ISBN = '9780000000033';

    private int $userId;

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

    /** El checksum de ISBN-13 tiene que ser válido: el ValueObject lo valida. */
    private function guardarLibro(): void
    {
        $alta = $this->router()->dispatch('add_book', ['book' => [
            'isbn'         => self::ISBN,
            'title'        => 'Un libro con sesión',
            'author'       => 'Autora de prueba',
            'userStatuses' => ['owned', 'reading'],
        ]]);

        $this->assertSame('success', $alta['status'], $alta['message'] ?? '');
    }

    /**
     * La página no se apunta por una acción: vive en `user_book_editions`, y es
     * justo la columna que `getCurrentPage()` viene a exponer.
     */
    private function ponerPaginaActual(int $pagina): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE user_book_editions SET current_page = :p WHERE user_id = :u'
        );
        $stmt->execute([':p' => $pagina, ':u' => $this->userId]);
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

    private function sesion(int $sessionId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM reading_sessions WHERE id = :id');
        $stmt->execute([':id' => $sessionId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function marcar(array $estados): array
    {
        return $this->router()->dispatch('update_book_user_statuses', [
            'isbn'     => self::ISBN,
            'statuses' => $estados,
        ]);
    }

    private function entradasDeDiario(): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM journal_entry WHERE user_id = :u AND media = \'book\' AND entity_id = :isbn'
        );
        $stmt->execute([':u' => $this->userId, ':isbn' => self::ISBN]);

        return (int) $stmt->fetchColumn();
    }

    #[Test]
    public function marking_a_book_as_read_completes_its_active_session(): void
    {
        $this->guardarLibro();
        $this->ponerPaginaActual(68);
        $sessionId = $this->abrirSesion(10);

        $respuesta = $this->marcar(['owned', 'read']);
        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');

        $sesion = $this->sesion($sessionId);
        $this->assertNotNull($sesion['end_date'], 'La sesión tiene que quedar con fecha de fin');
        $this->assertSame(0, (int) $sesion['is_active'], 'La sesión ya no está activa');
        $this->assertSame(
            68,
            (int) $sesion['end_page'],
            'Se conserva la última página conocida, no un 0: `complete(null)` escribiría 0'
        );

        // Y el estado queda puesto, que es la otra mitad del *Hecho cuando*.
        $estados = $this->container()
            ->get(\App\Domain\Repository\Book\UserBookRepositoryInterface::class)
            ->getUserStatuses($this->userId, self::ISBN);
        $this->assertContains('read', $estados);

        // El cierre desde el estado NO escribe diario: la única entrada es la
        // que ya apuntaba `recordIfConsumed` con origen `status`.
        $this->assertSame(1, $this->entradasDeDiario(), 'Una sola entrada de diario, no dos');
    }

    #[Test]
    public function marking_a_book_as_abandoned_abandons_its_active_session(): void
    {
        $this->guardarLibro();
        $this->ponerPaginaActual(42);
        $sessionId = $this->abrirSesion(10);

        $respuesta = $this->marcar(['owned', 'abandoned']);
        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');

        $sesion = $this->sesion($sessionId);
        $this->assertNotNull($sesion['end_date']);
        $this->assertSame(0, (int) $sesion['is_active']);
        // `reading_sessions` no tiene columna de estado: «abandonada» se
        // distingue por la nota que concatena `abandon()`.
        $this->assertStringContainsString('[ABANDONED] status_change', (string) $sesion['notes']);

        // `abandoned` no está en `CONSUMED_STATUSES['book']`: ni antes ni
        // después de este hito apunta diario.
        $this->assertSame(0, $this->entradasDeDiario());
    }

    #[Test]
    public function marking_a_book_as_read_without_an_active_session_changes_nothing(): void
    {
        $this->guardarLibro();
        $this->ponerPaginaActual(68);

        $respuesta = $this->marcar(['owned', 'read']);
        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');

        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM reading_sessions WHERE user_id = :u');
        $stmt->execute([':u' => $this->userId]);
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'Sin sesión abierta no se inventa ninguna');
    }

    #[Test]
    public function marking_a_book_as_read_twice_is_harmless(): void
    {
        $this->guardarLibro();
        $this->ponerPaginaActual(68);
        $sessionId = $this->abrirSesion(10);

        $this->assertSame('success', $this->marcar(['owned', 'read'])['status']);
        $primerCierre = $this->sesion($sessionId);

        // Segundo guardado con los mismos estados: no es una transición, así
        // que `$anadidos` sale vacío; y aunque no lo saliera, `getActive()`
        // filtra por `is_active = TRUE` y ya no la devolvería.
        $respuesta = $this->marcar(['owned', 'read']);
        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');

        $segundoCierre = $this->sesion($sessionId);
        $this->assertSame(0, (int) $segundoCierre['is_active']);
        $this->assertSame((int) $primerCierre['end_page'], (int) $segundoCierre['end_page']);
        $this->assertSame(1, $this->entradasDeDiario(), 'Reguardar no apunta una segunda entrada');

        // El disimulo del `EditItemModal`: tras guardar llama a
        // `updateReadingProgress`, que acaba en `complete()` sobre la misma
        // sesión. Con el cierre ya hecho tiene que ser inocuo, no dejar una
        // sesión de cero páginas.
        $this->container()->get(ReadingSessionRepositoryInterface::class)->complete($sessionId, 68);

        $tercerCierre = $this->sesion($sessionId);
        $this->assertSame(0, (int) $tercerCierre['is_active']);
        $this->assertSame(68, (int) $tercerCierre['end_page']);
    }
}
