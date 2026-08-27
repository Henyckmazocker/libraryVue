<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Las sesiones de lectura, por `ActionRouter` y contra el esquema real.
 *
 * `get_user_active_reading_sessions` respondía **500** porque
 * `BookController.php:375` llamaba a `getActiveSessions()` y el repositorio lo
 * tiene como `getUserActiveSessions()` (`MySqlReadingSessionRepository.php:367`).
 * Un `Error` de método inexistente, invisible para el test unitario porque
 * mockea la interfaz — la misma familia que los dos de `BookStatusesTest`.
 */
class ReadingSessionsTest extends IntegrationTestCase
{
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

    #[Test]
    public function the_active_sessions_of_a_user_can_be_listed(): void
    {
        // Sin sesiones abiertas la lista es vacía, pero la acción tiene que
        // responder: lo que estaba roto era la llamada, no el resultado.
        $respuesta = $this->router()->dispatch('get_user_active_reading_sessions', []);

        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');
        $this->assertIsArray($respuesta['data']);
    }

    #[Test]
    public function starting_a_session_makes_it_show_up_as_active(): void
    {
        $isbn = '9780000000064';

        $alta = $this->router()->dispatch('add_book', ['book' => [
            'isbn'         => $isbn,
            'title'        => 'Un libro de prueba',
            'author'       => 'Autora de prueba',
            'userStatuses' => ['owned'],
        ]]);
        $this->assertSame('success', $alta['status']);

        $sesion = $this->router()->dispatch('create_reading_session', [
            'isbn'      => $isbn,
            'startPage' => 1,
        ]);
        $this->assertSame('success', $sesion['status'], $sesion['message'] ?? '');

        $activas = $this->router()->dispatch('get_user_active_reading_sessions', []);
        $this->assertSame('success', $activas['status']);
        $this->assertNotEmpty($activas['data'], 'La sesión recién abierta tiene que salir como activa');
    }
}
