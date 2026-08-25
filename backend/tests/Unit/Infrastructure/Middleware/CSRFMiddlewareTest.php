<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Middleware;

use App\Infrastructure\Middleware\CSRFMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CSRFMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // El middleware lee $_SESSION directamente (CSRFMiddleware.php:35); la sesion la
        // arranca Application::bootstrap(), que aqui no corre.
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function middleware(): CSRFMiddleware
    {
        return new CSRFMiddleware(new NullLogger());
    }

    /** @return callable Registra las invocaciones y captura el request que recibe. */
    private function passthrough(int &$calls, ?array &$seen): callable
    {
        return function (array $request) use (&$calls, &$seen): array {
            $calls++;
            $seen = $request;
            return ['status' => 'success', 'http_code' => 200];
        };
    }

    #[Test]
    public function lets_a_matching_token_through(): void
    {
        $calls = 0;
        $seen = null;
        $_SESSION['csrf_token'] = 'abc123';

        $response = $this->middleware()->handle(
            ['action' => 'add_book', 'csrf_token' => 'abc123'],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(1, $calls);
        $this->assertSame('success', $response['status']);
    }

    #[Test]
    public function blocks_a_missing_token_with_403(): void
    {
        $calls = 0;
        $seen = null;
        $_SESSION['csrf_token'] = 'abc123';

        $response = $this->middleware()->handle(
            ['action' => 'add_book'],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(0, $calls);
        $this->assertSame('error', $response['status']);
        // 'http_code' y no 'code': Application.php:124 solo lee 'http_code'. Sin el, este
        // fallo salia al navegador como 400.
        $this->assertSame(403, $response['http_code']);
        $this->assertSame('CSRF_INVALID', $response['code']);
    }

    #[Test]
    public function blocks_a_different_token_with_403(): void
    {
        $calls = 0;
        $seen = null;
        $_SESSION['csrf_token'] = 'abc123';

        $response = $this->middleware()->handle(
            ['action' => 'add_book', 'csrf_token' => 'otro'],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(0, $calls);
        $this->assertSame(403, $response['http_code']);
        $this->assertSame('CSRF_INVALID', $response['code']);
    }

    #[Test]
    public function blocks_when_the_session_has_no_token(): void
    {
        $calls = 0;
        $seen = null;

        // Sin $_SESSION['csrf_token']: sesion recien creada o expirada.
        $response = $this->middleware()->handle(
            ['action' => 'add_book', 'csrf_token' => 'abc123'],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(0, $calls);
        $this->assertSame(403, $response['http_code']);
    }

    #[Test]
    public function skips_the_check_for_jwt_requests(): void
    {
        $calls = 0;
        $seen = null;

        // La rama de CSRFMiddleware.php:23, de la que depende la app movil: con Bearer no hay
        // cookie de sesion, asi que no hay token que comparar y la comprobacion se omite.
        $response = $this->middleware()->handle(
            ['action' => 'add_book', 'auth_method' => 'jwt'],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(1, $calls);
        $this->assertSame('success', $response['status']);
    }

    #[Test]
    public function preserves_the_rest_of_the_request(): void
    {
        $calls = 0;
        $seen = null;
        $_SESSION['csrf_token'] = 'abc123';

        $this->middleware()->handle(
            ['action' => 'add_book', 'csrf_token' => 'abc123', 'user_id' => 7],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame('add_book', $seen['action']);
        $this->assertSame(7, $seen['user_id']);
    }
}
