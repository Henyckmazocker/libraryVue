<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Services\CoverService;
use App\Infrastructure\Covers\CoverStore;
use App\Infrastructure\Http\PostResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * La única promesa que hace este servicio: guardar un ítem no puede fallar ni
 * ralentizarse por su portada. Eso son dos cosas comprobables — que no sale a
 * la red dentro de la petición, y que ninguna excepción se escapa.
 */
class CoverServiceTest extends TestCase
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
    public function it_registers_the_cover_without_downloading_anything(): void
    {
        $store = $this->createMock(CoverStore::class);
        $store->expects($this->once())
            ->method('register')
            ->with('movie', 'tt0068646', 'https://cdn.test/a.jpg');
        // La descarga se encola, no se ejecuta: dentro de la petición no hay red.
        $store->expects($this->never())->method('fetchPending');

        (new CoverService($store, new NullLogger()))
            ->recordCover('movie', 'tt0068646', 'https://cdn.test/a.jpg');
    }

    #[Test]
    public function the_deferred_work_is_what_downloads(): void
    {
        $store = $this->createMock(CoverStore::class);
        $store->method('register');
        $store->expects($this->once())->method('fetchPending')->willReturn(1);

        (new CoverService($store, new NullLogger()))
            ->recordCover('movie', 'tt0068646', 'https://cdn.test/a.jpg');

        // Lo que PHP dispara al apagarse, aquí a mano.
        PostResponse::run();
    }

    #[Test]
    public function a_bulk_import_queues_one_download_not_one_per_item(): void
    {
        $store = $this->createMock(CoverStore::class);
        $store->expects($this->exactly(3))->method('register');
        // importData() añade en bucle: tres ítems, UNA tarea encolada.
        $store->expects($this->once())->method('fetchPending')->willReturn(0);

        $service = new CoverService($store, new NullLogger());
        foreach (['tt1', 'tt2', 'tt3'] as $key) {
            $service->recordCover('movie', $key, 'https://cdn.test/' . $key . '.jpg');
        }

        PostResponse::run();
    }

    #[Test]
    public function an_item_without_a_cover_does_nothing_at_all(): void
    {
        $store = $this->createMock(CoverStore::class);
        $store->expects($this->never())->method('register');

        $service = new CoverService($store, new NullLogger());
        $service->recordCover('movie', 'tt0068646', null);
        $service->recordCover('movie', 'tt0068646', '');
    }

    #[Test]
    public function a_failure_never_escapes_towards_the_save(): void
    {
        $store = $this->createMock(CoverStore::class);
        $store->method('register')->willThrowException(new \RuntimeException('mirror caído'));

        // Sin excepción: si esto burbujeara, añadir una película fallaría porque
        // su portada no se pudo registrar.
        (new CoverService($store, new NullLogger()))
            ->recordCover('movie', 'tt0068646', 'https://cdn.test/a.jpg');

        $this->assertTrue(true);
    }
}
