<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Middleware;

use App\Infrastructure\Middleware\ValidationMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ValidationMiddlewareTest extends TestCase
{
    private function middleware(array $required = []): ValidationMiddleware
    {
        return new ValidationMiddleware(new NullLogger(), $required);
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
    public function lets_a_complete_request_through(): void
    {
        $calls = 0;
        $seen = null;

        $response = $this->middleware(['isbn', 'title'])->handle(
            ['action' => 'add_book', 'data' => ['isbn' => '9788401352836', 'title' => 'Dune']],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(1, $calls);
        $this->assertSame('success', $response['status']);
    }

    #[Test]
    public function blocks_a_missing_field_with_400(): void
    {
        $calls = 0;
        $seen = null;

        $response = $this->middleware(['isbn', 'title'])->handle(
            ['action' => 'add_book', 'data' => ['isbn' => '9788401352836']],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(0, $calls);
        $this->assertSame('error', $response['status']);
        // El 400 coincide con el que Application.php:124 pone por defecto, asi que este test no
        // cambia de color hoy; existe para que se note el dia que aqui haga falta un 422.
        $this->assertSame(400, $response['http_code']);
        $this->assertSame('VALIDATION_FAILED', $response['code']);
        $this->assertStringContainsString('title', $response['message']);
    }

    #[Test]
    public function treats_an_empty_string_as_missing(): void
    {
        $calls = 0;
        $seen = null;

        // ValidationMiddleware.php:42 compara con '' ademas de con isset().
        $response = $this->middleware(['title'])->handle(
            ['action' => 'add_book', 'data' => ['title' => '']],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(0, $calls);
        $this->assertSame(400, $response['http_code']);
    }

    #[Test]
    public function blocks_when_the_data_key_is_absent(): void
    {
        $calls = 0;
        $seen = null;

        $response = $this->middleware(['title'])->handle(
            ['action' => 'add_book'],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(0, $calls);
        $this->assertSame(400, $response['http_code']);
    }

    #[Test]
    public function lets_everything_through_when_nothing_is_required(): void
    {
        $calls = 0;
        $seen = null;

        $response = $this->middleware()->handle(
            ['action' => 'ping'],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(1, $calls);
        $this->assertSame('success', $response['status']);
    }

    #[Test]
    public function set_config_replaces_the_required_fields(): void
    {
        $calls = 0;
        $seen = null;

        // ActionRouter configura la instancia con la clave 'required' de config/routes.php.
        $middleware = $this->middleware();
        $middleware->setConfig(['required' => ['isbn']]);

        $response = $middleware->handle(
            ['action' => 'add_book', 'data' => []],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(0, $calls);
        $this->assertSame('VALIDATION_FAILED', $response['code']);
    }

    #[Test]
    public function preserves_the_rest_of_the_request(): void
    {
        $calls = 0;
        $seen = null;

        $this->middleware(['title'])->handle(
            ['action' => 'add_book', 'user_id' => 7, 'data' => ['title' => 'Dune']],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame('add_book', $seen['action']);
        $this->assertSame(7, $seen['user_id']);
    }
}
