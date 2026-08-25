<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * El pipeline de punta a punta, sin datos.
 *
 * Estos tres van primero a propósito: no necesitan fixtures y validan que la
 * infraestructura funciona antes de complicarse. Y cubren justo lo que ningún
 * mock de PDO puede — que la acción esté declarada en los **tres** sitios que
 * exige el `CLAUDE.md`: `config/routes.php`, el `match`/`getController` de
 * `ActionRouter` y el método del controller. Tenerlo mal en uno de los tres da
 * 1180 unitarios verdes y una acción que no responde. Ya pasó con `check_auth`
 * (Roadmap #12) y lo descubrió David conduciendo la app, no la suite.
 */
class PipelineTest extends IntegrationTestCase
{
    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    #[Test]
    public function ping_answers_through_the_whole_pipeline(): void
    {
        // Si esto pasa, están bien las tres cosas: la ruta declarada, el
        // `match` que la resuelve a LibraryController, y el método `ping()`.
        $r = $this->router()->dispatch('ping', []);

        $this->assertSame('success', $r['status'], 'ping tiene que responder success');
    }

    #[Test]
    public function an_unknown_action_is_an_error_and_not_a_500(): void
    {
        // Una acción que no existe es culpa de quien llama, no del servidor.
        $r = $this->router()->dispatch('esta_accion_no_existe_jamas', []);

        $this->assertSame('error', $r['status']);
        $this->assertNotSame(500, $r['http_code'] ?? null, 'Una acción desconocida no puede ser un 500');
    }

    #[Test]
    public function a_protected_action_without_credentials_is_cut_with_401(): void
    {
        // `AuthenticationMiddleware` acepta sesión **o** Bearer. Aquí no hay
        // ninguna de las dos: `$_SESSION` lo limpia el tearDown de la clase
        // base y no se manda cabecera.
        $r = $this->router()->dispatch('get_library', []);

        $this->assertSame('error', $r['status']);
        $this->assertSame(401, $r['http_code'] ?? null);
    }

    #[Test]
    public function a_protected_action_without_csrf_token_is_cut_with_403(): void
    {
        // Lo que arregló el plan de higiene del backend: `CSRFMiddleware`
        // devuelve **403**, no el 400 de antes. Con sesión puesta a mano, que
        // es lo que activa la rama de CSRF —con JWT se omite a propósito
        // (`CSRFMiddleware.php:23`)—.
        //
        // La clave es `user_data.id`, no `user_id`: es lo que mira
        // `AuthenticationMiddleware.php:27`. Escribir `$_SESSION['user_id']`
        // deja el test pasando por 401 en vez de por 403 y parece que el CSRF
        // no protege — descubierto escribiendo este test.
        $_SESSION['user_data'] = ['id' => 1];

        $r = $this->router()->dispatch('add_book', ['book' => ['isbn' => '9780000000001']]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code'] ?? null, 'Sin csrf_token el pipeline corta con 403');
    }
}
