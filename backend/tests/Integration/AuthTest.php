<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Autenticación contra la tabla `users` de verdad.
 *
 * El plan pedía aquí un test de `login` con credenciales buenas y malas, y **no
 * es escribible**: este proyecto no tiene login por contraseña. `AuthController::login`
 * (`:37-44`) exige un `google_token` verificado criptográficamente contra Google,
 * así que sin red no hay forma de ejercitarlo — y falsificar el verificador
 * convertiría esto en un test unitario con otro nombre.
 *
 * Se ejercita **la misma capa** por la rama que sí se puede: el JWT que usa la
 * app móvil. `JWTService` firma en local, `AuthenticationMiddleware` lo valida
 * (`:44-58`) y el usuario sale de la tabla real. Decidido con David el
 * 2026-08-25.
 */
class AuthTest extends IntegrationTestCase
{
    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    /** Un usuario de verdad en la base de test. Se revierte con la transacción. */
    private function crearUsuario(bool $admin = false): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name, is_admin)
             VALUES (:google_id, :email, :name, :is_admin)'
        );
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute([
            'google_id' => 'google-' . $sufijo,
            'email'     => $sufijo . '@ejemplo.test',
            'name'      => 'Usuario de prueba',
            'is_admin'  => $admin ? 1 : 0,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** Deja una cabecera Bearer como la que manda Capacitor. */
    private function conBearer(string $token): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    #[Test]
    public function a_valid_jwt_authenticates_against_the_real_users_table(): void
    {
        $userId = $this->crearUsuario();

        $token = $this->container()->get(JWTService::class)->generate([
            'user_id' => $userId,
            'email'   => 'da-igual@ejemplo.test',
        ]);
        $this->conBearer($token);

        $r = $this->router()->dispatch('check_auth', []);

        $this->assertSame('success', $r['status'], 'Un JWT válido tiene que pasar el pipeline');
    }

    #[Test]
    public function a_forged_jwt_is_cut_with_401(): void
    {
        // Firmado con otro secreto: la validación tiene que rechazarlo. Si esto
        // pasara en verde, cualquiera podría entrar fabricando su propio token.
        $this->conBearer('eyJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjoxfQ.firma-inventada');

        $r = $this->router()->dispatch('check_auth', []);

        $this->assertSame('error', $r['status']);
        $this->assertSame(401, $r['http_code'] ?? null);
    }

    #[Test]
    public function a_non_admin_cannot_reach_an_admin_action(): void
    {
        // `AdminMiddleware` consulta `users.is_admin` en la base, así que este
        // test recorre la columna que añadió una migración — otra cosa que un
        // mock de PDO daría por buena sin comprobar.
        $userId = $this->crearUsuario(admin: false);
        $this->conBearer($this->container()->get(JWTService::class)->generate(['user_id' => $userId]));

        $r = $this->router()->dispatch('libraryx_get_urls', []);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code'] ?? null, 'Sin is_admin, AdminMiddleware corta con 403');
    }

    #[Test]
    public function an_admin_does_reach_it(): void
    {
        // La otra mitad: sin esto, el test de arriba pasaría igual si la acción
        // estuviera rota para todo el mundo.
        $userId = $this->crearUsuario(admin: true);
        $this->conBearer($this->container()->get(JWTService::class)->generate(['user_id' => $userId]));

        $r = $this->router()->dispatch('libraryx_get_urls', []);

        $this->assertNotSame(403, $r['http_code'] ?? null, 'Con is_admin no puede cortar AdminMiddleware');
    }
}
