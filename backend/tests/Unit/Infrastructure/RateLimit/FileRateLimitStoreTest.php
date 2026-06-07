<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\RateLimit;

use App\Infrastructure\RateLimit\FileRateLimitStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FileRateLimitStoreTest extends TestCase
{
    private string $dir;
    private FileRateLimitStore $store;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ratelimit_test_' . uniqid('', true);
        $this->store = new FileRateLimitStore($this->dir, new NullLogger());
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    #[Test]
    public function creates_the_storage_directory(): void
    {
        $this->assertDirectoryExists($this->dir);
    }

    #[Test]
    public function increments_count_within_the_window(): void
    {
        $first = $this->store->hit('key', 60);
        $second = $this->store->hit('key', 60);
        $third = $this->store->hit('key', 60);

        $this->assertSame(1, $first['count']);
        $this->assertSame(2, $second['count']);
        $this->assertSame(3, $third['count']);
        $this->assertSame($first['reset_at'], $third['reset_at']);
    }

    #[Test]
    public function keeps_counters_isolated_per_key(): void
    {
        $this->store->hit('alpha', 60);
        $this->store->hit('alpha', 60);
        $beta = $this->store->hit('beta', 60);

        $this->assertSame(1, $beta['count']);
    }

    #[Test]
    public function resets_when_the_window_has_expired(): void
    {
        // Pre-write a counter whose window already expired.
        file_put_contents(
            $this->dir . '/key.json',
            json_encode(['count' => 99, 'reset_at' => time() - 10])
        );

        $result = $this->store->hit('key', 60);

        $this->assertSame(1, $result['count']);
        $this->assertGreaterThan(time(), $result['reset_at']);
    }

    #[Test]
    public function gc_removes_expired_counter_files(): void
    {
        file_put_contents($this->dir . '/expired.json', json_encode(['count' => 1, 'reset_at' => time() - 10]));
        $this->store->hit('active', 60);

        $removed = $this->store->gc();

        $this->assertSame(1, $removed);
        $this->assertFileDoesNotExist($this->dir . '/expired.json');
        $this->assertFileExists($this->dir . '/active.json');
    }
}
