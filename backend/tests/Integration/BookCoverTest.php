<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PDO;
use PHPUnit\Framework\Attributes\Test;

/**
 * La portada de un libro sobrevive al alta.
 *
 * Tres defectos encadenados hacían que no lo hiciera, y ninguno fallaba: el
 * libro se guardaba bien, la ficha lo pintaba con el dato que aún tenía en
 * memoria, y la carátula solo faltaba **después** —al recargar la biblioteca, y
 * de forma definitiva, porque `bin/mirror covers:seed` filtra por esas mismas
 * columnas y no puede registrar lo que nunca se guardó—.
 *
 *  1. `AddBookUseCase` pasaba `'covers' => []` y nada más: la URL que trae el
 *     formulario (`$command->coverUrl`) no llegaba a `BookImportService`.
 *  2. `BookImportService` metía lo que construía bajo la clave `cover_urls`, que
 *     `Edition::fromArray()` **nunca ha leído** — lee `cover_url_*`.
 *  3. `AddBookUseCase` leía `$legacyFormat['cover']`, clave que
 *     `Edition::toLegacyFormat()` no emite (la suya es `coverUrl`), así que el
 *     evento del feed iba sin portada y `recordCover()` salía por su guarda de
 *     `null` sin registrar fila.
 *
 * Va por integración porque los tres viven en puntos distintos de la misma
 * cadena y un mock de cualquiera de ellos la corta justo donde está el fallo.
 */
class BookCoverTest extends IntegrationTestCase
{
    private const PORTADA = 'https://books.google.com/books/content?id=PRUEBA&printsec=frontcover';

    private int $userId;

    /** @var string[] Los ISBN creados por el test, para limpiar el mirror. */
    private array $isbnCreados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $sufijo = bin2hex(random_bytes(4));
        $stmt = $this->pdo()->prepare('INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)');
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Lector']);
        $this->userId = (int) $this->pdo()->lastInsertId();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $this->userId]);
    }

    /**
     * El truncado de `IntegrationTestCase` limpia la base de la app, **no el
     * mirror**: `cover_file` vive en `library_mirror`, que es un stack aparte y
     * lo comparten dev y producción, así que ahí no se puede truncar nada.
     *
     * Y este test es el primero que llega a escribir en él. Hasta que se arregló
     * la cadena de la portada, `recordCover()` recibía siempre `null` y salía por
     * su guarda, de modo que ninguna suite dejaba rastro; ahora sí, y sin esto
     * cada pasada añadiría cuatro filas con una URL que no existe para que
     * `covers:backfill` intente bajarlas.
     */
    protected function tearDown(): void
    {
        if ($this->isbnCreados !== []) {
            $mirror = $this->container()->get('pdo.mirror');
            $marcas = implode(',', array_fill(0, count($this->isbnCreados), '?'));
            $mirror
                ->prepare("DELETE FROM cover_file WHERE media_type = 'book' AND entity_key IN ({$marcas})")
                ->execute($this->isbnCreados);

            $this->isbnCreados = [];
        }

        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    /** Un ISBN-13 aleatorio con su dígito de control bien calculado. */
    private function isbnValido(): string
    {
        $doce = '978' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

        $suma = 0;
        foreach (str_split($doce) as $i => $digito) {
            $suma += (int) $digito * ($i % 2 === 0 ? 1 : 3);
        }

        return $doce . ((10 - $suma % 10) % 10);
    }

    /** Guarda un libro y devuelve su ISBN. */
    private function anadirLibro(?string $portada = self::PORTADA): string
    {
        $isbn = $this->isbnValido();

        $r = $this->container()->get(ActionRouter::class)->dispatch('add_book', ['book' => [
            'isbn'         => $isbn,
            'title'        => 'Un libro con carátula',
            'author'       => 'Quien Sea',
            'coverUrl'     => $portada,
            'userStatuses' => ['owned'],
        ]]);

        $this->assertSame('success', $r['status'], 'El libro tiene que guardarse');

        $this->isbnCreados[] = $isbn;

        return $isbn;
    }

    private function edicionDe(string $isbn): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT cover_url_small, cover_url_medium, cover_url_large
               FROM book_editions WHERE isbn_13 = :i'
        );
        $stmt->execute(['i' => $isbn]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    #[Test]
    public function the_cover_url_reaches_the_edition_row(): void
    {
        // El defecto que dejaba a `covers:seed` sin nada que sembrar.
        $isbn = $this->anadirLibro();

        $this->assertSame(self::PORTADA, $this->edicionDe($isbn)['cover_url_medium']);
    }

    #[Test]
    public function the_feed_event_carries_the_cover(): void
    {
        $isbn = $this->anadirLibro();

        $stmt = $this->pdo()->prepare(
            "SELECT entity_cover FROM feed_events
              WHERE user_id = :u AND entity_type = 'book' AND entity_id = :i"
        );
        $stmt->execute(['u' => $this->userId, 'i' => $isbn]);

        $this->assertSame(self::PORTADA, $stmt->fetchColumn());
    }

    #[Test]
    public function a_book_without_cover_still_saves(): void
    {
        // La guarda de `recordCover()` y el `?? null` siguen siendo el camino
        // normal de un alta manual sin carátula: no pueden romper el alta.
        $isbn = $this->anadirLibro(null);

        $this->assertNull($this->edicionDe($isbn)['cover_url_medium']);
    }
}
