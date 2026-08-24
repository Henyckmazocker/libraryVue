<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Middleware;

use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Infrastructure\Middleware\AdminMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AdminMiddlewareTest extends TestCase
{
    private function user(bool $isAdmin): User
    {
        return new User(
            7,
            GoogleId::fromString('123456789012345678901'),
            Email::fromString('someone@gmail.com'),
            'Someone',
            null,
            null, null, null, null, true, null, null,
            $isAdmin
        );
    }

    /** @param User|null $found Lo que devuelve findById() */
    private function middleware(?User $found): AdminMiddleware
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findById')->willReturn($found);

        return new AdminMiddleware(new NullLogger(), $repository);
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
    public function lets_an_admin_through(): void
    {
        $calls = 0;
        $seen = null;

        $response = $this->middleware($this->user(true))->handle(
            ['action' => 'libraryx_get_urls', 'user_id' => 7],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(1, $calls);
        $this->assertSame('success', $response['status']);
    }

    #[Test]
    public function blocks_a_regular_user_with_403(): void
    {
        $calls = 0;
        $seen = null;

        $response = $this->middleware($this->user(false))->handle(
            ['action' => 'libraryx_get_urls', 'user_id' => 7],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(0, $calls);
        $this->assertSame('error', $response['status']);
        // 'http_code' y no 'code': Application.php:124 solo lee 'http_code'.
        $this->assertSame(403, $response['http_code']);
    }

    #[Test]
    public function blocks_a_missing_user_with_403(): void
    {
        $calls = 0;
        $seen = null;

        $response = $this->middleware(null)->handle(
            ['action' => 'libraryx_get_urls', 'user_id' => 999],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(0, $calls);
        $this->assertSame(403, $response['http_code']);
    }

    #[Test]
    public function blocks_when_authentication_middleware_is_missing(): void
    {
        $calls = 0;
        $seen = null;

        // Sin 'user_id' en el request: la ruta no encadeno AuthenticationMiddleware delante.
        $response = $this->middleware($this->user(true))->handle(
            ['action' => 'libraryx_get_urls'],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame(0, $calls);
        $this->assertSame(403, $response['http_code']);
    }

    #[Test]
    public function fills_the_user_key_the_controllers_read(): void
    {
        $calls = 0;
        $seen = null;

        $this->middleware($this->user(true))->handle(
            ['action' => 'libraryx_get_urls', 'user_id' => 7],
            $this->passthrough($calls, $seen)
        );

        // ActionRouter.php:503-504 pasa $request['user'] a LibraryXController; nadie mas escribe
        // esa clave, asi que sin esto las dos rutas responden 403 incluso al administrador.
        $this->assertIsArray($seen['user']);
        $this->assertSame('someone@gmail.com', $seen['user']['email']);
        $this->assertTrue($seen['user']['is_admin']);
    }

    #[Test]
    public function preserves_the_rest_of_the_request(): void
    {
        $calls = 0;
        $seen = null;

        $this->middleware($this->user(true))->handle(
            ['action' => 'libraryx_get_urls', 'user_id' => 7, 'auth_method' => 'jwt'],
            $this->passthrough($calls, $seen)
        );

        $this->assertSame('libraryx_get_urls', $seen['action']);
        $this->assertSame(7, $seen['user_id']);
        $this->assertSame('jwt', $seen['auth_method']);
    }
}
