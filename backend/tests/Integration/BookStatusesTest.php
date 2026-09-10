<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Repository\Book\UserBookRepositoryInterface;
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
 *
 * El **tercero** llegó el 2026-09-09 y es de la misma familia:
 * `MySqlUserBookRepository::getUserStatuses()` leía `user_book_statuses` por
 * `ubs.user_id`/`ubs.book_isbn`, columnas que esa tabla no tiene, así que
 * devolvía `[]` siempre y `UpdateBookUserStatusesUseCase` no podía distinguir
 * una transición de un reguardado. Los dos últimos tests de aquí lo fijan, y
 * por integración por la misma razón que los otros dos: era un desajuste entre
 * el esquema y la query, y un mock de PDO no lo ve.
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

    #[Test]
    public function the_saved_statuses_are_readable_back_by_isbn(): void
    {
        // El *Hecho cuando* del hito: el método que devolvía `[]` siempre
        // ahora devuelve lo que hay guardado, resuelto por el modelo
        // Work/Edition (ISBN → `book_editions` → `user_book_editions`).
        $isbn = '9780000000064';
        $this->guardarLibro($isbn);

        $respuesta = $this->router()->dispatch('update_book_user_statuses', [
            'isbn'     => $isbn,
            'statuses' => ['owned', 'read'],
        ]);
        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');

        $repositorio = $this->container()->get(UserBookRepositoryInterface::class);
        $estados     = $repositorio->getUserStatuses($this->userId, $isbn);
        sort($estados);

        $this->assertSame(['owned', 'read'], $estados, 'getUserStatuses no puede devolver []');
        $this->assertSame(
            [],
            $repositorio->getUserStatuses($this->userId, '9780000000071'),
            'Un ISBN que no está en la biblioteca sigue devolviendo []'
        );
    }

    #[Test]
    public function re_saving_the_statuses_of_an_already_read_book_adds_no_journal_entry(): void
    {
        $isbn = '9780000000071';
        $this->guardarLibro($isbn);

        // Marcarlo leído: esa transición SÍ apunta diario (`source = status`).
        $this->router()->dispatch('update_book_user_statuses', [
            'isbn'     => $isbn,
            'statuses' => ['owned', 'read'],
        ]);
        $this->assertSame(1, $this->entradasDeDiario($isbn), 'La transición a `read` apunta una entrada');

        // Se retrasa un día la entrada existente para que la clave de duplicado
        // del origen `status` (`medio:id:día`) NO pueda tapar el defecto: con
        // `getUserStatuses` roto, reguardar los mismos estados apuntaría una
        // entrada de hoy, que es exactamente lo que le pasó a *Dune* en dev.
        $this->pdo()
            ->prepare("UPDATE journal_entry
                          SET entry_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY),
                              source_id  = CONCAT('book:', :isbn, ':', DATE_SUB(CURDATE(), INTERVAL 1 DAY))
                        WHERE entity_id = :isbn2")
            ->execute([':isbn' => $isbn, ':isbn2' => $isbn]);

        $respuesta = $this->router()->dispatch('update_book_user_statuses', [
            'isbn'     => $isbn,
            'statuses' => ['owned', 'read'],
        ]);
        $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');

        $this->assertSame(
            1,
            $this->entradasDeDiario($isbn),
            'Reguardar los mismos estados no es una transición: no puede apuntar diario'
        );
    }

    /**
     * `countByStatus` era el gemelo de `getUserStatuses`: la misma query imposible
     * sobre `user_book_statuses` (`ubs.user_id`, `ubs.book_isbn`), el mismo `catch`
     * tragándose la `PDOException`, y un `0` en vez de un `[]`. Duró más porque un
     * contador que devuelve cero no parece roto: parece que no tienes libros.
     *
     * Por integración, igual que sus hermanos: el defecto es un desajuste entre el
     * esquema y la query, y un PDO mockeado lo daría por bueno.
     */
    #[Test]
    public function books_are_counted_by_status(): void
    {
        $repositorio = $this->container()->get(UserBookRepositoryInterface::class);

        // El usuario nace sin biblioteca: aquí el 0 es verdad, no el del `catch`.
        $this->assertSame(0, $repositorio->countByStatus($this->userId, 'read'));

        $primero = '9780000000064';
        $segundo = '9780000000071';
        $this->guardarLibro($primero);
        $this->guardarLibro($segundo);

        foreach ([$primero, $segundo] as $isbn) {
            $respuesta = $this->router()->dispatch('update_book_user_statuses', [
                'isbn'     => $isbn,
                'statuses' => ['owned', 'read'],
            ]);
            $this->assertSame('success', $respuesta['status'], $respuesta['message'] ?? '');
        }

        $this->assertSame(2, $repositorio->countByStatus($this->userId, 'read'));
        $this->assertSame(2, $repositorio->countByStatus($this->userId, 'owned'));
        $this->assertSame(0, $repositorio->countByStatus($this->userId, 'reading'));

        // Un estado que no existe no es un error: `getStatusId` devuelve null y se
        // sale antes de consultar. Ese 0 sí es legítimo.
        $this->assertSame(0, $repositorio->countByStatus($this->userId, 'inventado'));
    }

    private function entradasDeDiario(string $isbn): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM journal_entry WHERE user_id = :u AND media = \'book\' AND entity_id = :isbn'
        );
        $stmt->execute([':u' => $this->userId, ':isbn' => $isbn]);

        return (int) $stmt->fetchColumn();
    }
}
