<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Services\ClubRoundResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La tabla de verdad de la ronda: clubs de **1, 2, 3 y 8** miembros cruzados con
 * los cuatro atascos del plan.
 *
 * No es un test de cobertura sino la **validación del diseño**: lo que se
 * comprueba aquí es que en ningún cruce la ronda se queda sin transición
 * posible, porque una ronda muerta es un club muerto. Es lógica pura, sin base
 * de datos, y por eso puede correrse entera antes de escribir una sola línea de
 * esquema.
 */
class ClubRoundResolverTest extends TestCase
{
    private ClubRoundResolver $reglas;

    /** Los cuatro tamaños de club de la matriz. */
    private const TAMANOS = [1, 2, 3, 8];

    protected function setUp(): void
    {
        $this->reglas = new ClubRoundResolver();
    }

    /** @return int[] */
    private static function miembros(int $cuantos): array
    {
        return range(1, $cuantos);
    }

    // =========================================================================
    // Atasco 4 — la rotación
    // =========================================================================

    /**
     * @return array<string, array{0:int, 1:?int, 2:int[]}>
     */
    public static function rotacion(): array
    {
        // miembros, ganador anterior, quiénes pueden proponer
        return [
            'primera ronda de un club de 1'   => [1, null, [1]],
            'primera ronda de un club de 2'   => [2, null, [1, 2]],
            'primera ronda de un club de 3'   => [3, null, [1, 2, 3]],
            'primera ronda de un club de 8'   => [8, null, [1, 2, 3, 4, 5, 6, 7, 8]],
            // Con uno y con dos, la exclusión dejaría 0 y 1 proponentes: no se aplica.
            'club de 1, ganó el único'        => [1, 1, [1]],
            'club de 2, ganó A'               => [2, 1, [1, 2]],
            'club de 2, ganó B'               => [2, 2, [1, 2]],
            // A partir de tres sí rota: quedan dos, que es el mínimo para votar.
            'club de 3, ganó A'               => [3, 1, [2, 3]],
            'club de 8, ganó D'               => [8, 4, [1, 2, 3, 5, 6, 7, 8]],
            // El ganador anterior ya no está en el club: no excluye a nadie.
            'el ganador anterior se fue'      => [3, 99, [1, 2, 3]],
        ];
    }

    #[Test]
    #[DataProvider('rotacion')]
    public function la_rotacion_y_su_excepcion(int $cuantos, ?int $ganadorPrevio, array $esperado): void
    {
        $this->assertSame(
            $esperado,
            $this->reglas->eligibleProposers(self::miembros($cuantos), $ganadorPrevio)
        );
    }

    #[Test]
    public function la_rotacion_nunca_deja_al_club_sin_proponentes(): void
    {
        foreach (self::TAMANOS as $cuantos) {
            $miembros = self::miembros($cuantos);

            // Se prueba con CADA miembro como ganador anterior, no solo con uno:
            // la excepción tiene que valer para todos.
            foreach ($miembros as $ganador) {
                $pueden = $this->reglas->eligibleProposers($miembros, $ganador);

                $this->assertNotEmpty(
                    $pueden,
                    "Club de {$cuantos} con ganador {$ganador}: nadie puede proponer"
                );

                // Con dos o más miembros hacen falta DOS proponentes, o no hay
                // nada que votar: es el atasco 4 del plan.
                if ($cuantos >= 2) {
                    $this->assertGreaterThanOrEqual(
                        2,
                        count($pueden),
                        "Club de {$cuantos} con ganador {$ganador}: menos de dos proponentes"
                    );
                }
            }
        }
    }

    #[Test]
    public function el_club_de_dos_no_se_muere_en_la_segunda_ronda(): void
    {
        // La verificación nº 2 del plan, en su forma pura: A gana la ronda 1 y
        // tiene que poder proponer en la ronda 2.
        $this->assertFalse($this->reglas->mustRotate(1, [1, 2], 1));
        $this->assertFalse($this->reglas->mustRotate(2, [1, 2], 1));

        // Y con tres sí rota, que es el comportamiento que se busca.
        $this->assertTrue($this->reglas->mustRotate(1, [1, 2, 3], 1));
        $this->assertFalse($this->reglas->mustRotate(2, [1, 2, 3], 1));
    }

    // =========================================================================
    // Por qué no puedes proponer
    // =========================================================================

    /**
     * @return array<string, array{0:string, 1:bool, 2:bool, 3:?string}>
     */
    public static function motivos(): array
    {
        // fase, le toca rotar, ya propuso, motivo esperado
        return [
            'puede proponer'                    => ['proposing', false, false, null],
            'le toca rotar'                     => ['proposing', true,  false, ClubRoundResolver::REASON_ROTATION],
            'ya propuso'                        => ['proposing', false, true,  ClubRoundResolver::REASON_ALREADY_PROPOSED],
            'la ronda ya vota'                  => ['voting',    false, false, ClubRoundResolver::REASON_VOTING],
            // El orden: la fase gana a todo, y la rotación a haber propuesto.
            'vota, y además le tocaba rotar'    => ['voting',    true,  true,  ClubRoundResolver::REASON_VOTING],
            'rota, y además ya había propuesto' => ['proposing', true,  true,  ClubRoundResolver::REASON_ROTATION],
            'una ronda cerrada tampoco admite'  => ['closed',    false, false, ClubRoundResolver::REASON_VOTING],
        ];
    }

    #[Test]
    #[DataProvider('motivos')]
    public function por_que_no_puedes_proponer(string $fase, bool $rota, bool $yaPropuso, ?string $esperado): void
    {
        $this->assertSame($esperado, $this->reglas->proposalBlockReason($fase, $rota, $yaPropuso));
    }

    // =========================================================================
    // Atasco 1 — un miembro no propone nunca
    // =========================================================================

    /**
     * @return array<string, array{0:int, 1:int, 2:bool, 3:bool}>
     */
    public static function aperturaDelVoto(): array
    {
        // propuestas, con derecho a proponer, forzado por el dueño, esperado
        return [
            'nadie ha propuesto, automático'      => [0, 3, false, false],
            'nadie ha propuesto, forzado'         => [0, 3, true,  false],
            'falta uno, automático'               => [2, 3, false, false],
            'falta uno, el dueño lo fuerza'       => [2, 3, true,  true],
            'han propuesto todos'                 => [3, 3, false, true],
            'club de 1, ha propuesto él'          => [1, 1, false, true],
            'club de 8, faltan seis, forzado'     => [2, 8, true,  true],
            'club de 8, han propuesto los ocho'   => [8, 8, false, true],
        ];
    }

    #[Test]
    #[DataProvider('aperturaDelVoto')]
    public function cuando_se_abre_el_voto(int $propuestas, int $conDerecho, bool $forzado, bool $esperado): void
    {
        $this->assertSame($esperado, $this->reglas->canOpenVote($propuestas, $conDerecho, $forzado));
    }

    #[Test]
    public function una_ronda_sin_propuestas_no_se_abre_ni_forzando_pero_siempre_hay_quien_proponga(): void
    {
        // La celda «nadie propone» no tiene salida por arriba —abrir un voto
        // vacío deja la ronda clavada un escalón más allá—, así que su salida
        // es por abajo: SIEMPRE queda alguien con derecho a proponer.
        foreach (self::TAMANOS as $cuantos) {
            $this->assertFalse(
                $this->reglas->canOpenVote(0, $cuantos, true),
                "Club de {$cuantos}: se abrió un voto sin propuestas"
            );
            $this->assertNotEmpty(
                $this->reglas->eligibleProposers(self::miembros($cuantos), 1),
                "Club de {$cuantos}: ronda sin propuestas y sin nadie que pueda proponer"
            );
        }
    }

    // =========================================================================
    // Atasco 2 — un miembro no vota nunca
    // =========================================================================

    /**
     * @return array<string, array{0:int, 1:int, 2:bool, 3:bool}>
     */
    public static function cierreDelVoto(): array
    {
        // votos, miembros, forzado por el dueño, esperado
        return [
            'nadie ha votado, automático'    => [0, 3, false, false],
            'nadie ha votado, forzado'       => [0, 3, true,  false],
            'falta uno, automático'          => [2, 3, false, false],
            'falta uno, el dueño lo fuerza'  => [2, 3, true,  true],
            'han votado todos'               => [3, 3, false, true],
            'club de 1, ha votado él'        => [1, 1, false, true],
            'club de 8, falta uno, forzado'  => [7, 8, true,  true],
            'club de 8, han votado los ocho' => [8, 8, false, true],
        ];
    }

    #[Test]
    #[DataProvider('cierreDelVoto')]
    public function cuando_se_cierra_el_voto(int $votos, int $miembros, bool $forzado, bool $esperado): void
    {
        $this->assertSame($esperado, $this->reglas->canCloseVote($votos, $miembros, $forzado));
    }

    #[Test]
    public function con_un_solo_voto_el_dueno_siempre_puede_cerrar(): void
    {
        // La salida del atasco 2 en los cuatro tamaños: basta UN voto.
        foreach (self::TAMANOS as $cuantos) {
            $this->assertTrue(
                $this->reglas->canCloseVote(1, $cuantos, true),
                "Club de {$cuantos}: el dueño no pudo cerrar con un voto"
            );
        }
    }

    // =========================================================================
    // Atasco 3 — el empate
    // =========================================================================

    #[Test]
    public function un_unico_maximo_cierra_la_ronda(): void
    {
        $salida = $this->reglas->resolve([10 => 2, 11 => 1, 12 => 0], 1);

        $this->assertSame(ClubRoundResolver::ACTION_CLOSE, $salida['action']);
        $this->assertSame(10, $salida['winnerProposalId']);
        $this->assertSame([], $salida['tied']);
    }

    #[Test]
    public function sin_un_solo_voto_no_hay_nada_que_resolver(): void
    {
        $salida = $this->reglas->resolve([10 => 0, 11 => 0], 1);

        $this->assertSame(ClubRoundResolver::ACTION_NONE, $salida['action']);
        $this->assertNull($salida['winnerProposalId']);
    }

    #[Test]
    public function el_empate_del_primer_recuento_manda_a_revotar_solo_a_las_empatadas(): void
    {
        $salida = $this->reglas->resolve([10 => 2, 11 => 2, 12 => 1], 1);

        $this->assertSame(ClubRoundResolver::ACTION_REVOTE, $salida['action']);
        $this->assertNull($salida['winnerProposalId']);
        $this->assertSame([10, 11], $salida['tied']);
    }

    #[Test]
    public function el_empate_que_persiste_lo_resuelve_el_sorteo(): void
    {
        // El sorteo se fija para que el test sea determinista; en producción es
        // `random_int`. Lo que se comprueba no es QUIÉN gana sino que la ronda
        // TERMINA con un ganador de entre las empatadas.
        $salida = $this->reglas->resolve([10 => 2, 11 => 2], 2, static fn (int $n): int => 1);

        $this->assertSame(ClubRoundResolver::ACTION_CLOSE, $salida['action']);
        $this->assertSame(11, $salida['winnerProposalId']);
        $this->assertSame([10, 11], $salida['tied']);
    }

    #[Test]
    public function el_ballot_tiene_que_ser_valido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->reglas->resolve([10 => 1], 0);
    }

    // =========================================================================
    // La matriz completa: ninguna celda bloqueada
    // =========================================================================

    #[Test]
    public function ningun_cruce_deja_la_ronda_sin_salida(): void
    {
        foreach (self::TAMANOS as $cuantos) {
            $miembros = self::miembros($cuantos);
            $pueden   = $this->reglas->eligibleProposers($miembros, 1);

            // Atasco 1 · propone uno solo de los que podían → el dueño abre.
            $this->assertTrue(
                $this->reglas->canOpenVote(1, count($pueden), true),
                "Club de {$cuantos}: el dueño no pudo abrir el voto con una propuesta"
            );

            // Atasco 2 · vota uno solo → el dueño cierra.
            $this->assertTrue(
                $this->reglas->canCloseVote(1, $cuantos, true),
                "Club de {$cuantos}: el dueño no pudo cerrar con un voto"
            );

            // Atasco 3 · TODAS empatadas a un voto, que es el peor caso: no hay
            // «la menos votada» que eliminar. Primero revota, y el segundo
            // recuento termina por sorteo pase lo que pase.
            $recuento = [];
            foreach ($pueden as $i => $_) {
                $recuento[100 + $i] = 1;
            }

            if (count($recuento) === 1) {
                // Club de uno: no hay empate posible, gana su propia propuesta.
                $primera = $this->reglas->resolve($recuento, 1);
                $this->assertSame(ClubRoundResolver::ACTION_CLOSE, $primera['action']);
                $this->assertNotNull($primera['winnerProposalId']);
                continue;
            }

            $primera = $this->reglas->resolve($recuento, 1);
            $this->assertSame(
                ClubRoundResolver::ACTION_REVOTE,
                $primera['action'],
                "Club de {$cuantos}: el empate total no mandó a revotar"
            );

            // La revotación vuelve a empatar —el peor caso— y aun así cierra.
            $segunda = $this->reglas->resolve(
                array_fill_keys($primera['tied'], 1),
                2,
                static fn (int $n): int => 0
            );
            $this->assertSame(
                ClubRoundResolver::ACTION_CLOSE,
                $segunda['action'],
                "Club de {$cuantos}: el empate persistente no se resolvió por sorteo"
            );
            $this->assertNotNull(
                $segunda['winnerProposalId'],
                "Club de {$cuantos}: el sorteo no devolvió ganador"
            );
        }
    }
}
