<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Covers;

use App\Infrastructure\Covers\CoverStore;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Lo que protege esta clase no es el SQL —eso se comprueba contra el mirror
 * real— sino las dos barreras que impiden que una portada rompa algo: que
 * register() no salga nunca a la red ni tumbe un guardado, y que fetchPending()
 * no escriba al disco lo que no es una imagen.
 */
class CoverStoreTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/coverstore-' . uniqid();
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->tmpDir);
    }

    // =========================================================================
    // register — idempotencia y aislamiento del flujo de guardado
    // =========================================================================

    #[Test]
    public function register_writes_one_upsert_with_the_three_values(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                'media_type' => 'movie',
                'entity_key' => 'tt0095016',
                'source_url' => 'https://image.tmdb.org/t/p/w500/abc.jpg',
            ])
            ->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        // ON DUPLICATE KEY UPDATE es lo que hace idempotente a register():
        // llamarlo mil veces deja una fila, no mil.
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('ON DUPLICATE KEY UPDATE'))
            ->willReturn($stmt);

        $this->store($pdo)->register('movie', 'tt0095016', 'https://image.tmdb.org/t/p/w500/abc.jpg');
    }

    #[Test]
    public function register_ignores_an_empty_key_or_url(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('prepare');

        $store = $this->store($pdo);
        $store->register('movie', '', 'https://example.test/a.jpg');
        $store->register('movie', 'tt0095016', '');
        $store->register('', 'tt0095016', 'https://example.test/a.jpg');
    }

    #[Test]
    public function register_swallows_a_database_error_so_a_save_never_fails(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('mirror caído'));

        // Sin excepción que se escape: guardar una película no puede depender de
        // que el mirror esté vivo.
        $this->store($pdo)->register('movie', 'tt0095016', 'https://example.test/a.jpg');

        $this->assertTrue(true);
    }

    // =========================================================================
    // fetchPending — lo que llega al disco y lo que no
    // =========================================================================

    #[Test]
    public function fetch_pending_writes_the_image_and_marks_the_row(): void
    {
        $updated = [];
        $pdo = $this->pdoConPendientes(
            [['id' => 7, 'media_type' => 'movie', 'entity_key' => 'tt0095016', 'source_url' => 'https://cdn.test/a.jpg']],
            $updated
        );

        $store = $this->store($pdo, [
            new Response(200, ['Content-Type' => 'image/jpeg'], 'binario-de-jpeg'),
        ]);

        $this->assertSame(1, $store->fetchPending(10));

        $relative = $store->relativePathFor('https://cdn.test/a.jpg', 'image/jpeg');
        $this->assertFileExists($this->tmpDir . '/' . $relative);
        $this->assertSame('binario-de-jpeg', file_get_contents($this->tmpDir . '/' . $relative));

        $this->assertSame($relative, $updated[0]['storage_path']);
        $this->assertSame('image/jpeg', $updated[0]['mime_type']);
        $this->assertSame(strlen('binario-de-jpeg'), $updated[0]['bytes']);
    }

    #[Test]
    public function fetch_pending_rejects_a_content_type_that_is_not_an_image(): void
    {
        $updated = [];
        $pdo = $this->pdoConPendientes(
            [['id' => 7, 'media_type' => 'movie', 'entity_key' => 'tt0095016', 'source_url' => 'https://cdn.test/a.jpg']],
            $updated
        );

        // Un CDN caído devuelve su página de error con 200 y text/html. Eso NO
        // puede acabar en el disco: quedaría cacheado un mes en el navegador.
        $store = $this->store($pdo, [
            new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], '<html>502</html>'),
        ]);

        $this->assertSame(0, $store->fetchPending(10));
        $this->assertCount(0, glob($this->tmpDir . '/*/*') ?: []);
        $this->assertStringContainsString('Content-Type no es imagen', $updated[0]['last_error']);
    }

    #[Test]
    public function fetch_pending_rejects_a_body_over_the_size_cap(): void
    {
        $updated = [];
        $pdo = $this->pdoConPendientes(
            [['id' => 7, 'media_type' => 'movie', 'entity_key' => 'tt0095016', 'source_url' => 'https://cdn.test/a.jpg']],
            $updated
        );

        $store = $this->store($pdo, [
            new Response(200, ['Content-Type' => 'image/jpeg'], str_repeat('x', 5 * 1024 * 1024 + 1)),
        ]);

        $this->assertSame(0, $store->fetchPending(10));
        $this->assertCount(0, glob($this->tmpDir . '/*/*') ?: []);
        $this->assertStringContainsString('Supera los', $updated[0]['last_error']);
    }

    #[Test]
    public function fetch_pending_records_the_error_of_a_404(): void
    {
        $updated = [];
        $pdo = $this->pdoConPendientes(
            [['id' => 7, 'media_type' => 'movie', 'entity_key' => 'tt0095016', 'source_url' => 'https://cdn.test/a.jpg']],
            $updated
        );

        $store = $this->store($pdo, [new Response(404, [], 'Not Found')]);

        $this->assertSame(0, $store->fetchPending(10));
        $this->assertNotEmpty($updated[0]['last_error']);
        // El SQL del fallo incrementa attempts; es lo que hace que a los 3
        // intentos la fila deje de salir como pendiente.
        $this->assertStringContainsString('attempts   = attempts + 1', $updated[0]['sql']);
    }

    #[Test]
    public function fetch_pending_asks_only_for_rows_under_the_attempt_cap(): void
    {
        $bound = [];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function ($params) use (&$bound) {
            $bound = $params;
            return true;
        });
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($stmt) {
            $this->assertStringContainsString('storage_path IS NULL', $sql);
            $this->assertStringContainsString('attempts < :max_attempts', $sql);
            // attempts primero: una tanda de URLs muertas no puede tapar a las
            // que nunca se han intentado.
            $this->assertStringContainsString('ORDER BY attempts ASC, id ASC', $sql);
            return $stmt;
        });

        $this->assertSame(0, $this->store($pdo)->fetchPending(10));
        $this->assertSame(CoverStore::MAX_ATTEMPTS, $bound['max_attempts']);
    }

    #[Test]
    public function fetch_pending_does_nothing_with_a_limit_below_one(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('prepare');

        $this->assertSame(0, $this->store($pdo)->fetchPending(0));
    }

    #[Test]
    public function count_pending_tells_apart_nothing_to_do_from_everything_failed(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn('4');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($stmt) {
            $this->assertStringContainsString('COUNT(*)', $sql);
            $this->assertStringContainsString('attempts < :max_attempts', $sql);
            return $stmt;
        });

        $this->assertSame(4, $this->store($pdo)->countPending());
    }

    // =========================================================================
    // El reparto por hash
    // =========================================================================

    #[Test]
    public function the_relative_path_shards_by_the_first_two_characters_of_the_hash(): void
    {
        $url  = 'https://image.tmdb.org/t/p/w500/abc.jpg';
        $hash = sha1($url);

        $this->assertSame(
            substr($hash, 0, 2) . '/' . $hash . '.jpg',
            $this->store($this->createMock(PDO::class))->relativePathFor($url, 'image/jpeg')
        );
    }

    #[Test]
    public function the_extension_follows_the_content_type(): void
    {
        $this->assertSame('jpg',  CoverStore::extensionFor('image/jpeg'));
        $this->assertSame('png',  CoverStore::extensionFor('image/png'));
        $this->assertSame('webp', CoverStore::extensionFor('image/webp'));
        // Con parámetros y en mayúsculas: es como llega de más de un CDN.
        $this->assertSame('png',  CoverStore::extensionFor('IMAGE/PNG; charset=binary'));
        // Lo desconocido cae a jpg, que es lo que sirve el 99% de las portadas.
        $this->assertSame('jpg',  CoverStore::extensionFor('application/octet-stream'));
    }

    #[Test]
    public function local_path_is_null_when_the_file_is_not_on_disk(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        // La fila dice que se bajó, pero alguien vació el volumen: sin el
        // fichero, el endpoint tiene que caer al 302 igual que si no existiera.
        $stmt->method('fetch')->willReturn([
            'storage_path' => 'ab/abcdef.jpg',
            'source_url'   => 'https://cdn.test/a.jpg',
            'mime_type'    => 'image/jpeg',
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->assertNull($this->store($pdo)->localPath('movie', 'tt0095016'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** @param Response[] $responses */
    private function store(PDO $pdo, array $responses = []): CoverStore
    {
        $client = null;
        if ($responses !== []) {
            $client = new Client([
                'handler'         => HandlerStack::create(new MockHandler($responses)),
                'http_errors'     => false,
                'allow_redirects' => ['max' => 3],
            ]);
        }

        return new CoverStore($pdo, new NullLogger(), $this->tmpDir, $client);
    }

    /**
     * PDO que devuelve $pendientes en el SELECT y va apuntando en $updated los
     * parámetros de cada UPDATE, junto con el SQL que lo ejecutó.
     */
    private function pdoConPendientes(array $pendientes, array &$updated): PDO
    {
        $select = $this->createMock(PDOStatement::class);
        $select->method('execute')->willReturn(true);
        $select->method('fetchAll')->willReturn($pendientes);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($select, &$updated) {
            if (str_starts_with(ltrim($sql), 'SELECT')) {
                return $select;
            }

            $update = $this->createMock(PDOStatement::class);
            $update->method('execute')->willReturnCallback(function ($params) use (&$updated, $sql) {
                $updated[] = $params + ['sql' => $sql, 'last_error' => $params['last_error'] ?? null];
                return true;
            });

            return $update;
        });

        return $pdo;
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rmrf($path) : unlink($path);
        }
        rmdir($dir);
    }
}
