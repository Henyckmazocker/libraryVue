<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Services\SpoilerRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La regla de spoiler, que es lógica pura y por eso se fija entera aquí.
 *
 * Las cinco ramas que pedía el plan, más las dos guardas que no estaban
 * escritas en él y se decidieron al implementar: la nota propia y el
 * «completado» que desactiva la regla para los seis medios.
 */
class SpoilerRuleTest extends TestCase
{
    private SpoilerRule $regla;

    protected function setUp(): void
    {
        $this->regla = new SpoilerRule();
    }

    /**
     * @return array<string, array{0: ?int, 1: ?string, 2: ?int, 3: bool, 4: bool, 5: bool}>
     */
    public static function casos(): array
    {
        // notePoint, axis, myPoint, iCompleted, isMine, esperado
        return [
            'con eje y la nota va por delante'      => [180, 'page', 50, false, false, true],
            'con eje y la nota va por detrás'       => [30, 'page', 50, false, false, false],
            'con eje y la nota está justo donde yo' => [50, 'page', 50, false, false, false],
            'con eje y sin punto en la nota'        => [null, 'season', 2, false, false, true],
            'sin eje y sin completar'               => [null, null, null, false, false, true],
            'sin eje y completado'                  => [null, null, null, true, false, false],
            'con eje, completado, nota por delante' => [999, 'page', 50, true, false, false],
            'mi propia nota, por delante de mí'     => [900, 'page', 50, false, true, false],
            'mi propia nota, sin eje y sin acabar'  => [null, null, null, false, true, false],
        ];
    }

    #[Test]
    #[DataProvider('casos')]
    public function la_tabla_de_verdad_completa(
        ?int $notePoint,
        ?string $axis,
        ?int $myPoint,
        bool $iCompleted,
        bool $isMine,
        bool $esperado
    ): void {
        $this->assertSame(
            $esperado,
            $this->regla->isSpoiler($notePoint, $axis, $myPoint, $iCompleted, $isMine)
        );
    }

    #[Test]
    public function sin_progreso_registrado_no_es_la_pagina_cero(): void
    {
        // «No he empezado» tiene que ocultar incluso una nota en el punto 0.
        // Tratar el `null` como cero daría `0 < 0` → visible, y destriparía.
        $this->assertTrue($this->regla->isSpoiler(0, 'page', null, false));
        $this->assertTrue($this->regla->isSpoiler(1, 'page', null, false));
    }

    #[Test]
    public function completar_desactiva_la_regla_en_los_seis_medios(): void
    {
        foreach ([null, 'page', 'season'] as $eje) {
            $this->assertFalse(
                $this->regla->isSpoiler(9999, $eje, 0, true),
                "el eje {$eje} debería estar desactivado tras completar"
            );
        }
    }
}
