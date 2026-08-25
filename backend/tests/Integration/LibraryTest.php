<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * El modelo Work/Edition de punta a punta, contra el esquema real.
 *
 * Aquí es donde la suite se gana el sueldo: `add_book` recorre el use case, los
 * repositorios y el SQL contra las tablas de verdad —`book_works`,
 * `book_editions`, `user_book_editions`—, y `get_library` lo lee de vuelta. Un
 * `SELECT` con una columna que no existe pasa el test unitario, porque el mock
 * devuelve lo que se le diga, y explota en la primera petición real.
 */
class LibraryTest extends IntegrationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)'
        );
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Lector']);
        $this->userId = (int) $this->pdo()->lastInsertId();

        // Por JWT y no por sesión: así el pipeline omite el CSRF
        // (`CSRFMiddleware.php:23`) y estos tests se concentran en los datos,
        // que es lo que vienen a probar. El 403 sin token ya lo cubre
        // PipelineTest.
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
     * Los ISBN llevan **checksum válido de ISBN-13**, y no es un detalle: el
     * ValueObject lo valida y un número inventado sale por
     * `InvalidArgumentException` antes de tocar la base. Descubierto escribiendo
     * este test con `978000000001` + un dígito cualquiera.
     *
     * @return array<string,mixed>
     */
    private function libro(string $isbn): array
    {
        return [
            'isbn'          => $isbn,
            'title'         => 'Un libro de prueba',
            'author'        => 'Autora de prueba',
            'userStatuses'  => ['owned'],
        ];
    }

    #[Test]
    public function a_saved_book_comes_back_in_the_library(): void
    {
        $isbn = '9780000000019';

        $alta = $this->router()->dispatch('add_book', ['book' => $this->libro($isbn)]);
        $this->assertSame('success', $alta['status'], 'add_book tiene que guardar');

        $biblioteca = $this->router()->dispatch('get_library', []);
        $this->assertSame('success', $biblioteca['status']);

        $isbns = array_map(
            static fn (array $b): ?string => $b['isbn'] ?? null,
            $biblioteca['data'] ?? []
        );

        $this->assertContains($isbn, $isbns, 'El libro guardado tiene que aparecer en la biblioteca');
    }

    #[Test]
    public function saving_the_same_book_twice_does_not_duplicate_it(): void
    {
        // Lo que se comprueba no es el mensaje de error concreto —eso puede
        // cambiar— sino que el esquema no acabe con dos filas del mismo libro
        // para el mismo usuario.
        $isbn = '9780000000026';

        $this->router()->dispatch('add_book', ['book' => $this->libro($isbn)]);
        $this->router()->dispatch('add_book', ['book' => $this->libro($isbn)]);

        $biblioteca = $this->router()->dispatch('get_library', []);
        $repetidos = array_filter(
            $biblioteca['data'] ?? [],
            static fn (array $b): bool => ($b['isbn'] ?? null) === $isbn
        );

        $this->assertCount(1, $repetidos, 'Guardar dos veces no puede dejar dos filas');
    }

    #[Test]
    public function the_library_only_shows_what_belongs_to_this_user(): void
    {
        // Un fallo de aislamiento entre usuarios es de los peores que puede
        // tener esta app, y ningún test unitario con PDO mockeado lo ve: el
        // mock devuelve lo que se le pida, con `WHERE user_id` o sin él.
        $this->router()->dispatch('add_book', ['book' => $this->libro('9780000000033')]);

        $stmt = $this->pdo()->prepare('INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)');
        $otro = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $otro, 'e' => $otro . '@ejemplo.test', 'n' => 'Otra persona']);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => (int) $this->pdo()->lastInsertId()]);

        $ajena = $this->router()->dispatch('get_library', []);

        $this->assertSame('success', $ajena['status']);
        $this->assertSame([], $ajena['data'] ?? [], 'La biblioteca de otro usuario tiene que salir vacía');
    }
}
