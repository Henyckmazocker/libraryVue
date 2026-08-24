<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Infrastructure\Http\PostResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Lo comprobable sin Apache: el encolado y el aislamiento de errores. Que la
 * conexión se cierre de verdad ANTES del trabajo no se testea aquí —hace falta
 * mod_php— y se validó con el spike M0 a golpe de `curl -w '%{time_total}'`:
 * respuesta en 59 ms con 3 s de trabajo detrás.
 */
class PostResponseTest extends TestCase
{
    protected function setUp(): void
    {
        PostResponse::reset();
    }

    protected function tearDown(): void
    {
        PostResponse::reset();
    }

    #[Test]
    public function deferred_work_does_not_run_until_the_shutdown(): void
    {
        $corrio = false;
        PostResponse::defer(function () use (&$corrio) {
            $corrio = true;
        });

        $this->assertFalse($corrio, 'El trabajo no puede correr dentro de la petición');

        PostResponse::run();

        $this->assertTrue($corrio);
    }

    #[Test]
    public function several_deferrals_accumulate_and_all_run(): void
    {
        $orden = [];
        PostResponse::defer(function () use (&$orden) {
            $orden[] = 'a';
        });
        PostResponse::defer(function () use (&$orden) {
            $orden[] = 'b';
        });

        PostResponse::run();

        $this->assertSame(['a', 'b'], $orden);
    }

    #[Test]
    public function one_failing_task_does_not_stop_the_next(): void
    {
        $segundo = false;
        PostResponse::defer(function () {
            throw new \RuntimeException('el CDN no responde');
        });
        PostResponse::defer(function () use (&$segundo) {
            $segundo = true;
        });

        // Nadie escucha ya: el cliente tiene su respuesta desde hace rato.
        PostResponse::run();

        $this->assertTrue($segundo);
    }

    #[Test]
    public function the_queue_is_emptied_so_it_does_not_run_twice(): void
    {
        $veces = 0;
        PostResponse::defer(function () use (&$veces) {
            $veces++;
        });

        PostResponse::run();
        PostResponse::run();

        $this->assertSame(1, $veces);
    }
}
