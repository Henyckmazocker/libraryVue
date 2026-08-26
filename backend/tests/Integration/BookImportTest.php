<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PDO;
use PHPUnit\Framework\Attributes\Test;

/**
 * El alta de un libro por la ruta BUENA, no por el respaldo.
 *
 * `AddBookUseCase.php:114` envuelve a `BookImportService` en un `catch
 * (\Exception)` y cae a una creación manual que guarda menos campos. Hasta el
 * 2026-08-26 ese respaldo era la única ruta que corría jamás: el servicio
 * intentaba guardar la edición con `openlibrary_edition_key` a `null` sobre una
 * columna `NOT NULL` (`init.sql:81`) y moría con `SQLSTATE[23000]` en **cada**
 * alta manual. Nadie lo notaba porque el libro acababa guardado igual.
 *
 * Lo que ningún test unitario puede ver: los de `BookImportService` mockean el
 * repositorio, así que el `null` nunca llega a MySQL y la columna `NOT NULL` no
 * existe para ellos. Hace falta el esquema de verdad, y por eso esto vive aquí.
 */
class BookImportTest extends IntegrationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = $this->crearUsuario('lectora');
        $this->autenticar($this->userId);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    private function crearUsuario(string $nombre): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)'
        );
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => $nombre]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Por JWT y no por sesión, como el resto de la suite: así el pipeline omite
     * el CSRF (`CSRFMiddleware.php:23`) y el test se concentra en los datos.
     */
    private function autenticar(int $userId): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $userId]);
    }

    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    /**
     * Un alta manual: sin clave de Open Library, que es el caso que reventaba.
     *
     * El ISBN lleva **checksum de ISBN-13 válido**: el ValueObject lo verifica y
     * un número inventado sale por `InvalidArgumentException` sin tocar la base.
     *
     * @return array<string,mixed>
     */
    private function libro(string $isbn): array
    {
        return [
            'isbn'            => $isbn,
            'title'           => 'Un libro sin clave de Open Library',
            'author'          => 'Autora de prueba',
            'publisher'       => 'Editorial de prueba',
            // La clave del payload es `publicationDate`, no `publicationYear`:
            // así la lee AddBookCommand.php:71. Con el nombre equivocado llega
            // null y `publish_date` queda vacía por una razón que no es la que
            // este test vigila.
            'publicationDate' => 1998,
            'userStatuses'    => ['owned'],
        ];
    }

    /** @return array<string,mixed>|false */
    private function edicionDe(string $isbn)
    {
        $stmt = $this->pdo()->prepare(
            'SELECT e.*, w.synthetic_work_key, w.is_synthetic
               FROM book_editions e
               JOIN book_works w ON w.work_id = e.work_id
              WHERE e.isbn_13 = :isbn'
        );
        $stmt->execute(['isbn' => $isbn]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function contarEdiciones(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM book_editions')->fetchColumn();
    }

    #[Test]
    public function a_manual_add_goes_through_book_import_service(): void
    {
        $isbn = '9780000000040';

        $alta = $this->router()->dispatch('add_book', ['book' => $this->libro($isbn)]);
        $this->assertSame('success', $alta['status'], 'add_book tiene que guardar');

        $edicion = $this->edicionDe($isbn);
        $this->assertNotFalse($edicion, 'La edición tiene que existir en book_editions');

        // 1. La clave sintética: sin ella el INSERT muere con
        //    "Column 'openlibrary_edition_key' cannot be null".
        $this->assertSame(
            'synthetic_' . $isbn,
            $edicion['openlibrary_edition_key'],
            'Sin clave de Open Library, la columna NOT NULL se rellena con la sintética'
        );

        // 2 y 3. Los dos campos que discriminan la ruta buena del respaldo.
        //    `publish_date` solo la escribe BookImportService.php:190; el
        //    respaldo (AddBookUseCase.php:145-158) guarda `publish_year` y ya.
        //    Y `synthetic_work_key` es al revés: el respaldo la rellena siempre
        //    (AddBookUseCase.php:129 + markAsSynthetic()) y la ruta buena la
        //    deja a null, porque Work::fromArray ignora el 'isSynthetic' que se
        //    le pasa —el constructor lo fija a false en Work.php:56—.
        $this->assertNotNull(
            $edicion['publish_date'],
            'publish_date solo la escribe BookImportService: si es null, corrió el respaldo'
        );
        $this->assertNull(
            $edicion['synthetic_work_key'],
            'synthetic_work_key solo la escribe el respaldo: si viene rellena, corrió el respaldo'
        );
    }

    #[Test]
    public function a_second_user_reuses_the_edition_instead_of_creating_another(): void
    {
        // Lo que protege este test: si alguien cambia la forma de la clave
        // sintética sin mirar, la deduplicación por ISBN sigue siendo la que
        // manda y no se intenta un segundo INSERT que chocaría con el UNIQUE.
        //
        // Es un SEGUNDO usuario y no el mismo dos veces a propósito: repetir el
        // alta con el mismo devuelve error en AddBookUseCase.php:65 ("You
        // already have this book in your library") y no llega a la edición.
        $isbn = '9780000000057';

        $this->router()->dispatch('add_book', ['book' => $this->libro($isbn)]);
        $edicionesTrasLaPrimera = $this->contarEdiciones();

        $this->autenticar($this->crearUsuario('otro lector'));

        $segunda = $this->router()->dispatch('add_book', ['book' => $this->libro($isbn)]);
        $this->assertSame('success', $segunda['status'], 'El segundo usuario también puede guardarlo');

        $this->assertSame(
            $edicionesTrasLaPrimera,
            $this->contarEdiciones(),
            'La edición se reutiliza por ISBN: book_editions no puede crecer'
        );
    }
}
