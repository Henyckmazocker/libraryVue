<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * `update_book_user_statuses`, la acción que estuvo rota sin que nadie lo viera.
 *
 * Acumulaba **dos** fallos que un test unitario no puede ver, porque los dos
 * viven en las costuras que un mock sustituye:
 *
 * 1. `ActionRouter` construía `UpdateBookStatusesCommand` con los argumentos en
 *    otro orden —(int, string, array) contra un constructor (ISBN, int, array)—,
 *    así que la acción respondía **500 a todo**.
 * 2. `MySqlUserBookRepository::hasBook()` consultaba `user_books`, una tabla que
 *    el esquema **no tiene**; la `PDOException` caía en su propio `catch` y
 *    devolvía `false`, de modo que la respuesta era «Book not found in your
 *    library» con el libro delante.
 *
 * Ninguno de los dos se vio durante meses porque el frontend metía los estados
 * dentro de `edit_user_book` y esta acción no la llamaba nada. Desde el plan
 * «Composables Genéricos por Medio» (M7) sí la llama, y por eso se entra aquí
 * por `ActionRouter`, no por el use case.
 */
class BookStatusesTest extends IntegrationTestCase
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

    /** El checksum de ISBN-13 tiene que ser válido: el ValueObject lo valida. */
    private function guardarLibro(string $isbn): void
    {
        $alta = $this->router()->dispatch('add_book', ['book' => [
            'isbn'         => $isbn,
            'title'        => 'Un libro de prueba',
            'author'       => 'Autora de prueba',
            'userStatuses' => ['owned'],
        ]]);

        $this->assertSame('success', $alta['status'], 'add_book tiene que guardar');
    }

    private function estadosDe(string $isbn): array
    {
        $biblioteca = $this->router()->dispatch('get_library', []);

        foreach ($biblioteca['data'] ?? [] as $libro) {
            if (($libro['isbn'] ?? null) === $isbn) {
                return $libro['userStatuses'] ?? [];
            }
        }

        return [];
    }

    #[Test]
    public function updating_the_statuses_of_a_saved_book_persists_them(): void
    {
        $isbn = '9780000000033';
        $this->guardarLibro($isbn);

        $respuesta = $this->router()->dispatch('update_book_user_statuses', [
            'isbn'     => $isbn,
            'statuses' => ['owned', 'read'],
        ]);

        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');

        $estados = $this->estadosDe($isbn);
        sort($estados);
        $this->assertSame(['owned', 'read'], $estados);
    }

    #[Test]
    public function a_book_that_is_not_in_the_library_is_rejected(): void
    {
        // La contrapartida del arreglo de `hasBook()`: que ahora encuentre el
        // libro no puede significar que acepte cualquiera.
        $respuesta = $this->router()->dispatch('update_book_user_statuses', [
            'isbn'     => '9780000000040',
            'statuses' => ['read'],
        ]);

        $this->assertSame('error', $respuesta['status']);
        $this->assertStringContainsStringIgnoringCase('not found', $respuesta['message'] ?? '');
    }

    #[Test]
    public function the_statuses_can_be_cleared(): void
    {
        $isbn = '9780000000057';
        $this->guardarLibro($isbn);

        $respuesta = $this->router()->dispatch('update_book_user_statuses', [
            'isbn'     => $isbn,
            'statuses' => [],
        ]);

        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');
        $this->assertSame([], $this->estadosDe($isbn));
    }
}
