<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\DTO\Commands\LoginUserCommand;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\UseCases\Auth\LoginUserUseCase;
use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * `users.analytics_consent` de punta a punta: la migración
 * `20260928_120000_analytics_consent`, el UPDATE dedicado del repositorio y la
 * action `update_analytics_consent` entrando por `ActionRouter`
 * (Plan «Consentimiento de Analítica», M1).
 *
 * `login` no se puede despachar aquí (exige un `google_token` verificado contra
 * Google, ver AuthTest). Lo que devuelve en `user` es `LoginUserUseCase` +
 * `User::toArray()`, así que se ejercita eso contra la tabla real; `check_auth`
 * sí va por el router con un Bearer, como la app móvil.
 */
class AnalyticsConsentTest extends IntegrationTestCase
{
    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    private function crearUsuario(): int
    {
        $sufijo = bin2hex(random_bytes(4));
        $stmt = $this->pdo()->prepare('INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)');
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Quien decide']);

        return (int) $this->pdo()->lastInsertId();
    }

    private function conSesionJwt(int $userId): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $userId]);
    }

    private function columnas(int $userId): array
    {
        $stmt = $this->pdo()->prepare('SELECT analytics_consent, analytics_consent_at FROM users WHERE id = :u');
        $stmt->execute(['u' => $userId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    #[Test]
    public function a_fresh_login_returns_analytics_consent_null(): void
    {
        $sufijo = bin2hex(random_bytes(4));
        $user = $this->container()->get(LoginUserUseCase::class)->execute(new LoginUserCommand(
            googleId: GoogleId::fromString('google-' . $sufijo),
            email:    Email::fromString($sufijo . '@ejemplo.test'),
            name:     'Recién llegado'
        ));

        $array = $user->toArray();
        $this->assertArrayHasKey('analytics_consent', $array);
        $this->assertNull($array['analytics_consent'], 'Sin decidir tiene que ser null, no 0');

        // Y un segundo login (la rama `update()` genérica) no lo toca.
        $otra = $this->container()->get(LoginUserUseCase::class)->execute(new LoginUserCommand(
            googleId: GoogleId::fromString('google-' . $sufijo),
            email:    Email::fromString($sufijo . '@ejemplo.test'),
            name:     'Recién llegado'
        ));
        $this->assertNull($otra->toArray()['analytics_consent']);
    }

    #[Test]
    public function check_auth_returns_analytics_consent(): void
    {
        $userId = $this->crearUsuario();
        $this->conSesionJwt($userId);

        $r = $this->router()->dispatch('check_auth', []);

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertArrayHasKey('analytics_consent', $r['data']['user']);
        $this->assertNull($r['data']['user']['analytics_consent']);
    }

    #[Test]
    public function the_action_sets_it_to_one_and_back_to_zero(): void
    {
        $userId = $this->crearUsuario();
        $this->conSesionJwt($userId);

        $si = $this->router()->dispatch('update_analytics_consent', ['consent' => true]);
        $this->assertSame('success', $si['status'], json_encode($si));
        $this->assertSame(1, $si['data']['analytics_consent']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $si['data']['analytics_consent_at']);
        $this->assertSame(1, (int) $this->columnas($userId)['analytics_consent']);
        // `check_auth` lo lleva en la carga siguiente.
        $this->assertSame(1, $this->router()->dispatch('check_auth', [])['data']['user']['analytics_consent']);

        $no = $this->router()->dispatch('update_analytics_consent', ['consent' => false]);
        $this->assertSame('success', $no['status'], json_encode($no));
        $this->assertSame(0, $no['data']['analytics_consent']);
        $this->assertSame(0, (int) $this->columnas($userId)['analytics_consent']);
        $this->assertNotNull($this->columnas($userId)['analytics_consent_at']);
        $this->assertSame(0, $this->router()->dispatch('check_auth', [])['data']['user']['analytics_consent']);
    }

    #[Test]
    public function without_a_session_it_is_cut_with_401(): void
    {
        $r = $this->router()->dispatch('update_analytics_consent', ['consent' => true]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(401, $r['http_code'] ?? null);
    }

    #[Test]
    public function a_non_boolean_consent_is_a_400_and_writes_nothing(): void
    {
        $userId = $this->crearUsuario();
        $this->conSesionJwt($userId);

        foreach ([['consent' => 'x'], ['consent' => 1], []] as $cuerpo) {
            $r = $this->router()->dispatch('update_analytics_consent', $cuerpo);

            $this->assertSame('error', $r['status'], json_encode($cuerpo));
            $this->assertSame(400, $r['http_code'] ?? null, json_encode($cuerpo));
        }

        $this->assertNull($this->columnas($userId)['analytics_consent'], 'Un 400 no puede dejar una decisión escrita');
    }

    #[Test]
    public function the_columns_exist_nullable_and_default_null(): void
    {
        $stmt = $this->pdo()->query(
            "SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME IN ('analytics_consent', 'analytics_consent_at')
             ORDER BY COLUMN_NAME"
        );
        $columnas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(2, $columnas, 'Faltan columnas: ¿se aplicó la migración?');
        foreach ($columnas as $c) {
            $this->assertNull($c['COLUMN_DEFAULT'], $c['COLUMN_NAME']);
            $this->assertSame('YES', $c['IS_NULLABLE'], $c['COLUMN_NAME']);
        }
    }
}
