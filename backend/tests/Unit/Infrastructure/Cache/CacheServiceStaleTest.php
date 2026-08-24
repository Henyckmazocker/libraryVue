<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cache;

use App\Infrastructure\Cache\CacheService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CacheServiceStaleTest extends TestCase
{
    private string $dir;
    private CacheService $cache;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cache_stale_test_' . uniqid('', true);
        $this->cache = new CacheService($this->dir, new NullLogger());
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*/*.json') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->dir . '/*', GLOB_ONLYDIR) ?: [] as $subdir) {
            @rmdir($subdir);
        }
        @rmdir($this->dir);
    }

    /** Escribe una entrada con las marcas de tiempo que haga falta, sin pasar por set(). */
    private function writeEntry(string $key, mixed $value, int $createdAgo, int $expiredAgo, string $namespace = 'ns'): string
    {
        $dir = $this->dir . '/' . $namespace;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $key . '.json';
        file_put_contents($path, json_encode([
            'key' => $key,
            'namespace' => $namespace,
            'value' => $value,
            'created_at' => time() - $createdAgo,
            'expires_at' => time() - $expiredAgo
        ]));

        return $path;
    }

    #[Test]
    public function returns_an_expired_entry_within_the_cap_flagged_as_stale(): void
    {
        $this->writeEntry('books', ['dune'], 86400, 3600);

        $result = $this->cache->getStale('books', 'ns');

        $this->assertNotNull($result);
        $this->assertSame(['dune'], $result['value']);
        $this->assertTrue($result['is_stale']);
        $this->assertSame(time() - 86400, $result['cached_at']);
    }

    #[Test]
    public function flags_a_still_valid_entry_as_not_stale(): void
    {
        $this->cache->set('books', ['dune'], 3600, 'ns');

        $result = $this->cache->getStale('books', 'ns');

        $this->assertNotNull($result);
        $this->assertSame(['dune'], $result['value']);
        $this->assertFalse($result['is_stale']);
    }

    #[Test]
    public function refuses_an_entry_older_than_the_cap(): void
    {
        // 31 días, uno más que el tope por defecto
        $this->writeEntry('books', ['dune'], 2678400, 2674800);

        $this->assertNull($this->cache->getStale('books', 'ns'));
    }

    #[Test]
    public function honours_a_custom_cap(): void
    {
        $this->writeEntry('books', ['dune'], 86400, 3600);

        $this->assertNull($this->cache->getStale('books', 'ns', 3600));
        $this->assertNotNull($this->cache->getStale('books', 'ns', 172800));
    }

    #[Test]
    public function returns_null_for_a_corrupted_entry_without_throwing(): void
    {
        mkdir($this->dir . '/ns', 0755, true);
        file_put_contents($this->dir . '/ns/books.json', '{not json at all');

        $this->assertNull($this->cache->getStale('books', 'ns'));
    }

    #[Test]
    public function returns_null_when_the_entry_does_not_exist(): void
    {
        $this->assertNull($this->cache->getStale('nothing', 'ns'));
    }

    #[Test]
    public function does_not_delete_the_file_it_reads(): void
    {
        $path = $this->writeEntry('books', ['dune'], 86400, 3600);

        $this->cache->getStale('books', 'ns');

        $this->assertFileExists($path);
        // Y sigue siendo legible una segunda vez
        $this->assertNotNull($this->cache->getStale('books', 'ns'));
    }

    #[Test]
    public function keeps_a_corrupted_file_instead_of_unlinking_it(): void
    {
        mkdir($this->dir . '/ns', 0755, true);
        $path = $this->dir . '/ns/books.json';
        file_put_contents($path, '{not json at all');

        $this->cache->getStale('books', 'ns');

        $this->assertFileExists($path);
    }

    #[Test]
    public function distinguishes_a_cached_null_from_a_missing_entry(): void
    {
        $this->writeEntry('books', null, 86400, 3600);

        $result = $this->cache->getStale('books', 'ns');

        $this->assertNotNull($result);
        $this->assertNull($result['value']);
    }

    #[Test]
    public function set_resilient_writes_an_entry_get_stale_can_read(): void
    {
        $this->cache->setResilient('books', ['dune'], 1, 'ns');

        $result = $this->cache->getStale('books', 'ns');

        $this->assertNotNull($result);
        $this->assertSame(['dune'], $result['value']);
    }

    #[Test]
    public function get_still_deletes_an_expired_entry(): void
    {
        $path = $this->writeEntry('books', ['dune'], 86400, 3600);

        $this->assertNull($this->cache->get('books', 'ns'));
        $this->assertFileDoesNotExist($path);
    }

    #[Test]
    public function clean_expired_spares_stale_entries_within_the_cap(): void
    {
        $stale = $this->writeEntry('recent', ['dune'], 86400, 3600);
        $tooOld = $this->writeEntry('ancient', ['dune'], 2678400, 2674800);

        $deleted = $this->cache->cleanExpired('ns');

        $this->assertSame(1, $deleted);
        $this->assertFileExists($stale);
        $this->assertFileDoesNotExist($tooOld);
    }
}
