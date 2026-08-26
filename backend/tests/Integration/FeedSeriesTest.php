<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * El feed distingue una serie de una película.
 *
 * `feed_events.entity_type` es un ENUM sin `'series'`, y no puede tenerlo: en el
 * backend series y películas son la misma entidad y se guardan las dos con
 * `AddMovieUseCase`. Lo que las separa es `movie.media_type`, y hasta este plan
 * no salía del backend: el frontend mandaba *Twin Peaks* a `/movies/tt…`, una
 * ruta que existe y carga, pero que pinta la ficha de película sin temporadas.
 *
 * Esto se prueba por integración y no con un mock de PDO **porque lo que puede
 * romperse es el SQL**: el `LEFT JOIN` de `MySqlFeedEventRepository` casa
 * `m.isbn = fe.entity_id`, dos columnas de tipos distintos en tablas distintas.
 * Un mock del repositorio devolvería lo que se le diga y no vería nada.
 */
class FeedSeriesTest extends IntegrationTestCase
{
    private int $userId;

    private int $amigoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId  = $this->crearUsuario('Quien mira');
        $this->amigoId = $this->crearUsuario('Quien publica');
        $this->conAmistadAceptada($this->userId, $this->amigoId);

        // Se publica como el amigo: el feed solo trae actividad de amigos, nunca
        // la propia (`GetFeedUseCase::doExecute`, paso 1).
        $this->autenticarComo($this->amigoId);
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

    private function crearUsuario(string $nombre): int
    {
        $sufijo = bin2hex(random_bytes(4));
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name, username) VALUES (:g, :e, :n, :u)'
        );
        $stmt->execute([
            'g' => 'g-' . $sufijo,
            'e' => $sufijo . '@ejemplo.test',
            'n' => $nombre,
            'u' => 'u' . $sufijo,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function conAmistadAceptada(int $unId, int $otroId): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO friendships (requester_id, addressee_id, status)
             VALUES (:r, :a, 'accepted')"
        );
        $stmt->execute(['r' => $unId, 'a' => $otroId]);
    }

    private function autenticarComo(int $userId): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $userId]);
    }

    /** Guarda una película o una serie y devuelve su id de IMDb. */
    private function guardar(string $mediaType): string
    {
        $tconst = 'tt' . random_int(1000000, 9999999);

        $this->router()->dispatch('add_movie', ['movie' => [
            'isbn'         => $tconst,
            'imdbID'       => $tconst,
            'title'        => $mediaType === 'series' ? 'Twin Peaks' : 'Dune',
            'userStatuses' => ['owned'],
        ]]);

        // El alta no distingue el medio: lo marca la columna, que es lo que el
        // `LEFT JOIN` del feed va a leer.
        $stmt = $this->pdo()->prepare('UPDATE movie SET media_type = :t WHERE isbn = :i');
        $stmt->execute(['t' => $mediaType, 'i' => $tconst]);

        return $tconst;
    }

    /**
     * Un ISBN-13 aleatorio pero con su dígito de control bien calculado.
     *
     * `IsbnValueObject` valida el checksum, así que un `'978' . random_int(...)`
     * se rechaza con un 400 y el libro no llega ni a guardarse: el test moriría
     * diciendo que falta el evento, que es un síntoma y no la causa.
     */
    private function isbnValido(): string
    {
        $doce = '978' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

        $suma = 0;
        foreach (str_split($doce) as $i => $digito) {
            $suma += (int) $digito * ($i % 2 === 0 ? 1 : 3);
        }

        return $doce . ((10 - $suma % 10) % 10);
    }

    /** El evento del feed cuyo `entity_id` es el que se pide. */
    private function eventoDe(string $entityId): array
    {
        $this->autenticarComo($this->userId);
        $r = $this->router()->dispatch('get_feed', ['limit' => 50, 'offset' => 0]);
        $this->autenticarComo($this->amigoId);

        $this->assertSame('success', $r['status'], 'El feed tiene que responder');

        foreach ($r['data']['events'] as $evento) {
            if ($evento['entity_id'] === $entityId) {
                return $evento;
            }
        }

        $this->fail("No hay ningún evento en el feed con entity_id {$entityId}");
    }

    #[Test]
    public function a_series_event_carries_its_media_type(): void
    {
        $tconst = $this->guardar('series');

        $this->assertSame('series', $this->eventoDe($tconst)['entity_media_type']);
    }

    #[Test]
    public function a_movie_event_carries_movie(): void
    {
        $tconst = $this->guardar('movie');

        $this->assertSame('movie', $this->eventoDe($tconst)['entity_media_type']);
    }

    #[Test]
    public function the_left_join_does_not_drop_events_of_other_media(): void
    {
        // El motivo de que el JOIN sea LEFT y de que la condición vaya en el ON.
        // Con un JOIN a secas, o con `fe.entity_type = 'movie'` en el WHERE,
        // este evento desaparecería del feed en silencio.
        $isbn = $this->isbnValido();

        $this->router()->dispatch('add_book', ['book' => [
            'isbn'         => $isbn,
            'title'        => 'Un libro que no es una película',
            'author'       => 'Nadie',
            'userStatuses' => ['owned'],
        ]]);

        $evento = $this->eventoDe($isbn);

        $this->assertSame('book', $evento['entity_type']);
        $this->assertNull($evento['entity_media_type']);
    }
}
