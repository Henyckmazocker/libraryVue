<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Infrastructure\Http\RateGate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La puerta de cadencia de MusicBrainz.
 *
 * Casi todo se comprueba con `reserve()`, que devuelve la espera sin gastarla:
 * medir el throttle durmiendo de verdad costaría un segundo por aserción y la
 * suite entera tarda hoy 0,6 s.
 */
class RateGateTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rategate_' . uniqid('', true);
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function gate(float $intervalo = 1.0, string $nombre = 'musicbrainz'): RateGate
    {
        return new RateGate($nombre, $intervalo, $this->dir);
    }

    #[Test]
    public function the_first_call_does_not_wait(): void
    {
        $this->assertSame(0.0, $this->gate()->reserve());
    }

    #[Test]
    public function the_second_call_waits_the_interval(): void
    {
        $gate = $this->gate(1.0);
        $gate->reserve();

        $espera = $gate->reserve();

        $this->assertGreaterThan(0.9, $espera);
        $this->assertLessThanOrEqual(1.0, $espera);
    }

    #[Test]
    public function consecutive_callers_get_consecutive_slots(): void
    {
        // Lo que reserva es el turno FUTURO, no el instante actual: si no, tres
        // procesos que entran a la vez se darían todos el mismo hueco y los
        // tres llamarían en el mismo segundo.
        $gate = $this->gate(1.0);

        $gate->reserve();
        $segunda = $gate->reserve();
        $tercera = $gate->reserve();

        $this->assertGreaterThan($segunda, $tercera);
        $this->assertGreaterThan(1.9, $tercera);
    }

    #[Test]
    public function the_gate_is_shared_between_separate_instances(): void
    {
        // El caso real: dos procesos de Apache distintos, cada uno con su
        // objeto. Si el estado viviera en memoria, la segunda no esperaría.
        $this->gate(1.0)->reserve();

        $this->assertGreaterThan(0.9, $this->gate(1.0)->reserve());
    }

    #[Test]
    public function different_names_do_not_share_a_gate(): void
    {
        $this->gate(1.0, 'musicbrainz')->reserve();

        $this->assertSame(0.0, $this->gate(1.0, 'otra-api')->reserve());
    }

    #[Test]
    public function a_clock_far_in_the_future_cannot_block_forever(): void
    {
        // Un reloj que salte, o un fichero manipulado, no pueden dejar una
        // petición dormida indefinidamente.
        file_put_contents($this->dir . '/gate_musicbrainz.txt', (string) (microtime(true) + 3600));

        $this->assertLessThanOrEqual(5.0, $this->gate(1.0)->reserve());
    }

    #[Test]
    public function wait_actually_sleeps_the_reserved_time(): void
    {
        // El único test que gasta reloj, y con un intervalo corto: comprueba
        // que `wait()` duerme de verdad lo que `reserve()` promete.
        $gate = $this->gate(0.3);
        $gate->wait();

        $inicio = microtime(true);
        $dormido = $gate->wait();
        $transcurrido = microtime(true) - $inicio;

        $this->assertGreaterThan(0.2, $dormido);
        $this->assertGreaterThan(0.2, $transcurrido);
    }
}
