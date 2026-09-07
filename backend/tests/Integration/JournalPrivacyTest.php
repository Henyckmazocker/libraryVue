<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * El séptimo interruptor de `user_privacy_settings`, entrando por `ActionRouter`.
 *
 * Por integración y no solo con unitarios porque lo que hay que comprobar es
 * justo lo que un mock de PDO sustituye: que la migración
 * `20260907_120000_journal_privacy` se aplicó, que la columna nace en 0, y que
 * el `INSERT … ON DUPLICATE KEY UPDATE` del repositorio la escribe de verdad.
 *
 * El caso que se lleva la atención es el último: `update_privacy_settings` sin
 * la clave `show_journal` **no puede** encender el diario. `fromArray` la lee
 * con `?? false`, y un respaldo mal elegido ahí publicaría un diario sin que
 * nadie lo pidiera — que es exactamente el riesgo que manda en este plan.
 */
class JournalPrivacyTest extends IntegrationTestCase
{
    private int $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $sufijo = bin2hex(random_bytes(4));
        $stmt = $this->pdo()->prepare('INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)');
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Quien escribe un diario']);
        $this->usuario = (int) $this->pdo()->lastInsertId();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $this->usuario]);
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

    private function leer(): array
    {
        return $this->router()->dispatch('get_privacy_settings', []);
    }

    private function guardar(array $ajustes): array
    {
        return $this->router()->dispatch('update_privacy_settings', $ajustes);
    }

    /** Los seis del feed tal y como los manda el panel, para variar solo el séptimo. */
    private function losSeisDelFeed(): array
    {
        return [
            'show_additions'        => true,
            'show_status_changes'   => true,
            'show_ratings'          => true,
            'show_notes'            => false,
            'show_reading_sessions' => true,
            'show_achievements'     => true,
        ];
    }

    private function columnaEnBd(): ?int
    {
        $stmt = $this->pdo()->prepare('SELECT show_journal FROM user_privacy_settings WHERE user_id = :u');
        $stmt->execute(['u' => $this->usuario]);
        $valor = $stmt->fetchColumn();

        return $valor === false ? null : (int) $valor;
    }

    #[Test]
    public function the_journal_is_off_for_a_user_who_never_touched_the_panel(): void
    {
        $r = $this->leer();

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertFalse($r['data']['show_journal']);
        // Sin fila todavía: el apagado sale del default del modelo, no de la BD.
        $this->assertNull($this->columnaEnBd());
    }

    #[Test]
    public function turning_it_on_survives_a_reload(): void
    {
        $guardado = $this->guardar($this->losSeisDelFeed() + ['show_journal' => true]);
        $this->assertSame('success', $guardado['status'], json_encode($guardado));

        // La fila de verdad, que es lo que un unitario con PDO mockeado no ve.
        $this->assertSame(1, $this->columnaEnBd());
        // Y la lectura siguiente, que es lo que ve el panel al recargar.
        $this->assertTrue($this->leer()['data']['show_journal']);
    }

    #[Test]
    public function turning_it_off_again_goes_back_to_zero(): void
    {
        $this->guardar($this->losSeisDelFeed() + ['show_journal' => true]);
        $this->guardar($this->losSeisDelFeed() + ['show_journal' => false]);

        $this->assertSame(0, $this->columnaEnBd());
        $this->assertFalse($this->leer()['data']['show_journal']);
    }

    #[Test]
    public function a_save_without_the_key_never_switches_the_journal_on(): void
    {
        // Un cliente viejo, o una llamada que solo quiere tocar el feed. El
        // respaldo de `fromArray` es `false` justo para esto: los otros seis
        // caen a `true` porque su ausencia no enseña nada que no se viera ya.
        $r = $this->guardar($this->losSeisDelFeed());

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertSame(0, $this->columnaEnBd());
    }

    #[Test]
    public function the_column_exists_and_defaults_to_off(): void
    {
        // La migración misma. Sin ella, todo lo de arriba fallaría con un SQL
        // roto y no señalaría a la causa.
        $stmt = $this->pdo()->query(
            "SELECT COLUMN_DEFAULT, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_privacy_settings'
               AND COLUMN_NAME = 'show_journal'"
        );
        $columna = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($columna, 'Falta la columna show_journal: ¿se aplicó la migración?');
        $this->assertSame('0', $columna['COLUMN_DEFAULT']);
        $this->assertSame('NO', $columna['IS_NULLABLE']);
    }
}
