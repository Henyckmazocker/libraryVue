<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\RateLimit;

use App\Infrastructure\RateLimit\FileRateLimitStore;
use App\Infrastructure\RateLimit\RateLimitMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RateLimitMiddlewareTest extends TestCase
{
    private string $dir;
    private FileRateLimitStore $store;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ratelimit_mw_test_' . uniqid('', true);
        $this->store = new FileRateLimitStore($this->dir, new NullLogger());

        // Deterministic IP so ip-based keys are stable across runs.
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $_ENV['RATE_LIMIT_ENABLED'] = 'true';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        unset($_ENV['RATE_LIMIT_ENABLED']);
    }

    private function middleware(array $config): RateLimitMiddleware
    {
        $mw = new RateLimitMiddleware($this->store, new NullLogger());
        $mw->setConfig($config);
        return $mw;
    }

    /** @return callable Records invocations and returns a sentinel response. */
    private function passthrough(int &$calls): callable
    {
        return function (array $request) use (&$calls): array {
            $calls++;
            return ['status' => 'success', 'http_code' => 200];
        };
    }

    #[Test]
    public function allows_requests_under_the_limit(): void
    {
        $mw = $this->middleware(['limit' => 3, 'window' => 60, 'by' => 'ip']);
        $request = ['action' => 'login'];
        $calls = 0;

        for ($i = 0; $i < 3; $i++) {
            $response = $mw->handle($request, $this->passthrough($calls));
            $this->assertSame(200, $response['http_code']);
        }

        $this->assertSame(3, $calls);
    }

    #[Test]
    public function blocks_requests_over_the_limit_with_429(): void
    {
        $mw = $this->middleware(['limit' => 2, 'window' => 60, 'by' => 'ip']);
        $request = ['action' => 'login'];
        $calls = 0;

        $mw->handle($request, $this->passthrough($calls));
        $mw->handle($request, $this->passthrough($calls));
        $blocked = $mw->handle($request, $this->passthrough($calls));

        $this->assertSame(429, $blocked['http_code']);
        $this->assertSame('error', $blocked['status']);
        // Next handler must not run once the limit is exceeded.
        $this->assertSame(2, $calls);
    }

    #[Test]
    public function passes_through_when_disabled(): void
    {
        $_ENV['RATE_LIMIT_ENABLED'] = 'false';
        $mw = $this->middleware(['limit' => 1, 'window' => 60, 'by' => 'ip']);
        $request = ['action' => 'login'];
        $calls = 0;

        for ($i = 0; $i < 5; $i++) {
            $response = $mw->handle($request, $this->passthrough($calls));
            $this->assertSame(200, $response['http_code']);
        }

        $this->assertSame(5, $calls);
    }

    #[Test]
    public function keys_independently_by_user(): void
    {
        $mw = $this->middleware(['limit' => 1, 'window' => 60, 'by' => 'user']);
        $calls = 0;

        $first = $mw->handle(['action' => 'add_book', 'user_id' => 1], $this->passthrough($calls));
        // Different user → separate counter, still allowed.
        $second = $mw->handle(['action' => 'add_book', 'user_id' => 2], $this->passthrough($calls));
        // Same user as first → over the limit of 1.
        $third = $mw->handle(['action' => 'add_book', 'user_id' => 1], $this->passthrough($calls));

        $this->assertSame(200, $first['http_code']);
        $this->assertSame(200, $second['http_code']);
        $this->assertSame(429, $third['http_code']);
        $this->assertSame(2, $calls);
    }
}
