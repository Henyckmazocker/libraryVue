<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cache;

use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Cache\ResilientCall;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ResilientCallTest extends TestCase
{
    private string $dir;
    private CacheService $cache;
    private ResilientCall $resilient;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/resilient_test_' . uniqid('', true);
        $this->cache = new CacheService($this->dir, new NullLogger());
        $this->resilient = new ResilientCall($this->cache, new NullLogger());
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*/*.json') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->dir . '/*', GLOB_ONLYDIR) ?: [] as $subdir) {
            @rmdir($subdir);
        }
        @rmdir($this->dir);
    }

    /** Deja una entrada ya caducada en la caché, como la que dejaría una búsqueda de hace días. */
    private function seedStale(string $key, mixed $value, string $namespace = 'provider'): void
    {
        $dir = $this->dir . '/' . $namespace;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir . '/' . $key . '.json', json_encode([
            'key' => $key,
            'namespace' => $namespace,
            'value' => $value,
            'created_at' => time() - 86400,
            'expires_at' => time() - 3600
        ]));
    }

    private function httpError(int $status): ClientException|ServerException
    {
        $request = new Request('GET', '/volumes');
        $response = new Response($status);

        return $status >= 500
            ? new ServerException("HTTP {$status}", $request, $response)
            : new ClientException("HTTP {$status}", $request, $response);
    }

    #[Test]
    public function returns_fresh_data_and_caches_it_on_success(): void
    {
        $result = $this->resilient->around('dune', 'provider', 3600, fn() => ['fresh']);

        $this->assertSame(['fresh'], $result['data']);
        $this->assertFalse($result['stale']);
        $this->assertSame(['fresh'], $this->cache->get('dune', 'provider'));
    }

    #[Test]
    public function serves_a_valid_cache_entry_without_calling_the_api(): void
    {
        $this->cache->set('dune', ['cached'], 3600, 'provider');
        $calls = 0;

        $result = $this->resilient->around('dune', 'provider', 3600, function () use (&$calls) {
            $calls++;
            return ['fresh'];
        });

        $this->assertSame(0, $calls);
        $this->assertSame(['cached'], $result['data']);
        $this->assertFalse($result['stale']);
    }

    #[Test]
    public function calls_the_api_when_the_entry_is_stale_and_prefers_the_fresh_answer(): void
    {
        $this->seedStale('dune', ['old']);

        $result = $this->resilient->around('dune', 'provider', 3600, fn() => ['fresh']);

        $this->assertSame(['fresh'], $result['data']);
        $this->assertFalse($result['stale']);
    }

    #[Test]
    public function degrades_to_stale_data_on_a_429(): void
    {
        $this->seedStale('dune', ['old']);

        $result = $this->resilient->around('dune', 'provider', 3600, fn() => throw $this->httpError(429));

        $this->assertSame(['old'], $result['data']);
        $this->assertTrue($result['stale']);
        $this->assertSame(time() - 86400, $result['cached_at']);
    }

    #[Test]
    public function degrades_to_stale_data_on_a_5xx(): void
    {
        $this->seedStale('dune', ['old']);

        $result = $this->resilient->around('dune', 'provider', 3600, fn() => throw $this->httpError(503));

        $this->assertSame(['old'], $result['data']);
        $this->assertTrue($result['stale']);
    }

    #[Test]
    public function degrades_to_stale_data_when_the_connection_fails(): void
    {
        $this->seedStale('dune', ['old']);

        $result = $this->resilient->around('dune', 'provider', 3600, function () {
            throw new ConnectException('Could not resolve host', new Request('GET', '/volumes'));
        });

        $this->assertSame(['old'], $result['data']);
        $this->assertTrue($result['stale']);
    }

    #[Test]
    public function does_not_degrade_on_a_404_even_with_stale_data_available(): void
    {
        $this->seedStale('dune', ['old']);

        $this->expectException(ClientException::class);

        $this->resilient->around('dune', 'provider', 3600, fn() => throw $this->httpError(404));
    }

    #[Test]
    public function propagates_the_failure_when_there_is_nothing_stale_to_serve(): void
    {
        $this->expectException(ServerException::class);

        $this->resilient->around('dune', 'provider', 3600, fn() => throw $this->httpError(503));
    }

    #[Test]
    public function propagates_the_failure_when_the_stale_entry_is_past_the_cap(): void
    {
        $dir = $this->dir . '/provider';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/dune.json', json_encode([
            'key' => 'dune',
            'namespace' => 'provider',
            'value' => ['ancient'],
            'created_at' => time() - 2678400,
            'expires_at' => time() - 2674800
        ]));

        $this->expectException(ServerException::class);

        $this->resilient->around('dune', 'provider', 3600, fn() => throw $this->httpError(503));
    }

    #[Test]
    public function does_not_overwrite_the_stale_entry_when_the_call_fails(): void
    {
        $this->seedStale('dune', ['old']);

        try {
            $this->resilient->around('dune', 'provider', 3600, fn() => throw $this->httpError(429));
        } catch (\Throwable) {
            $this->fail('No debería propagar: hay dato rancio que servir');
        }

        // El segundo intento sigue encontrando lo rancio, no una entrada vacía
        $result = $this->resilient->around('dune', 'provider', 3600, fn() => throw $this->httpError(429));
        $this->assertSame(['old'], $result['data']);
    }
}
