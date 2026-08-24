<?php

namespace App\Infrastructure\Cache;

use Psr\Log\LoggerInterface;

/**
 * Simple file-based cache service
 * Stores cached data as JSON files with TTL support
 */
class CacheService
{
    private string $cacheDir;
    private LoggerInterface $logger;

    /** Hard cap for stale reads: 30 days, the limit the YouTube API terms impose on stored data */
    public const STALE_MAX_AGE = 2592000;

    public function __construct(string $cacheDir, LoggerInterface $logger)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->logger = $logger;

        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get cached data if it exists and is not expired
     *
     * @param string $key Cache key (will be sanitized)
     * @param string $namespace Optional namespace for organizing cache files
     * @return mixed|null Cached data or null if not found/expired
     */
    public function get(string $key, string $namespace = ''): mixed
    {
        $filePath = $this->getCacheFilePath($key, $namespace);

        if (!file_exists($filePath)) {
            $this->logger->debug("Cache miss", ['key' => $key, 'namespace' => $namespace]);
            return null;
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (!$data || !isset($data['expires_at'], $data['value'])) {
            $this->logger->warning("Cache corrupted", ['key' => $key, 'namespace' => $namespace]);
            @unlink($filePath);
            return null;
        }

        // Check expiration
        if ($data['expires_at'] < time()) {
            $this->logger->debug("Cache expired", ['key' => $key, 'namespace' => $namespace]);
            @unlink($filePath);
            return null;
        }

        $this->logger->debug("Cache hit", ['key' => $key, 'namespace' => $namespace]);
        return $data['value'];
    }

    /**
     * Store data in cache with TTL
     *
     * @param string $key Cache key
     * @param mixed $value Data to cache (must be JSON serializable)
     * @param int $ttl Time to live in seconds
     * @param string $namespace Optional namespace
     * @return bool Success status
     */
    public function set(string $key, mixed $value, int $ttl = 3600, string $namespace = ''): bool
    {
        $filePath = $this->getCacheFilePath($key, $namespace);
        $dir = dirname($filePath);

        // Ensure namespace directory exists
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'key' => $key,
            'namespace' => $namespace,
            'value' => $value,
            'created_at' => time(),
            'expires_at' => time() + $ttl
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $success = file_put_contents($filePath, $json, LOCK_EX) !== false;

        if ($success) {
            $this->logger->debug("Cache stored", [
                'key' => $key,
                'namespace' => $namespace,
                'ttl' => $ttl,
                'expires_at' => date('Y-m-d H:i:s', $data['expires_at'])
            ]);
        } else {
            $this->logger->error("Cache store failed", ['key' => $key, 'namespace' => $namespace]);
        }

        return $success;
    }

    /**
     * Read a cache entry even if it expired, without deleting it
     *
     * Unlike get(), this never unlinks the file: the whole point is to keep the
     * stale copy around as a safety net for when the upstream API fails.
     *
     * @param string $key Cache key
     * @param string $namespace Optional namespace
     * @param int $maxAgeSeconds Hard cap measured from created_at (default 30 days)
     * @return array{value: mixed, cached_at: int, is_stale: bool}|null Null if missing, corrupted or older than the cap
     */
    public function getStale(string $key, string $namespace = '', int $maxAgeSeconds = self::STALE_MAX_AGE): ?array
    {
        $filePath = $this->getCacheFilePath($key, $namespace);

        if (!file_exists($filePath)) {
            $this->logger->debug("Stale cache miss", ['key' => $key, 'namespace' => $namespace]);
            return null;
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (!is_array($data) || !array_key_exists('value', $data) || !isset($data['expires_at'], $data['created_at'])) {
            $this->logger->warning("Stale cache corrupted", ['key' => $key, 'namespace' => $namespace]);
            return null;
        }

        $age = time() - (int)$data['created_at'];
        if ($age > $maxAgeSeconds) {
            $this->logger->debug("Stale cache too old", [
                'key' => $key,
                'namespace' => $namespace,
                'age_seconds' => $age,
                'max_age_seconds' => $maxAgeSeconds
            ]);
            return null;
        }

        $isStale = $data['expires_at'] < time();

        $this->logger->debug("Stale cache hit", [
            'key' => $key,
            'namespace' => $namespace,
            'is_stale' => $isStale,
            'age_seconds' => $age
        ]);

        return [
            'value' => $data['value'],
            'cached_at' => (int)$data['created_at'],
            'is_stale' => $isStale
        ];
    }

    /**
     * Store data meant to survive expiration as a fallback
     *
     * Identical to set(); it exists so the call site says out loud that this
     * entry is written to be readable by getStale() after its TTL runs out.
     *
     * @param string $key Cache key
     * @param mixed $value Data to cache (must be JSON serializable)
     * @param int $ttl Time to live in seconds
     * @param string $namespace Optional namespace
     * @return bool Success status
     */
    public function setResilient(string $key, mixed $value, int $ttl, string $namespace = ''): bool
    {
        return $this->set($key, $value, $ttl, $namespace);
    }

    /**
     * Delete cached item
     *
     * @param string $key Cache key
     * @param string $namespace Optional namespace
     * @return bool Success status
     */
    public function delete(string $key, string $namespace = ''): bool
    {
        $filePath = $this->getCacheFilePath($key, $namespace);

        if (file_exists($filePath)) {
            $success = @unlink($filePath);
            $this->logger->debug("Cache deleted", ['key' => $key, 'namespace' => $namespace]);
            return $success;
        }

        return true;
    }

    /**
     * Clear all cache in a namespace or entire cache
     *
     * @param string $namespace Optional namespace to clear
     * @return int Number of files deleted
     */
    public function clear(string $namespace = ''): int
    {
        $dir = $namespace ? $this->cacheDir . '/' . $this->sanitizeNamespace($namespace) : $this->cacheDir;
        $count = 0;

        if (!is_dir($dir)) {
            return 0;
        }

        $files = glob($dir . '/*.json');
        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }

        $this->logger->info("Cache cleared", ['namespace' => $namespace ?: 'all', 'files_deleted' => $count]);
        return $count;
    }

    /**
     * Clean cache entries past the stale safety net
     *
     * Deliberately measured against created_at and STALE_MAX_AGE, not expires_at:
     * an entry past its TTL is still the fallback getStale() serves when the API
     * fails, so deleting it here would quietly remove the safety net.
     *
     * @param string $namespace Optional namespace to clean
     * @param int $maxAgeSeconds Hard cap measured from created_at (default 30 days)
     * @return int Number of files deleted
     */
    public function cleanExpired(string $namespace = '', int $maxAgeSeconds = self::STALE_MAX_AGE): int
    {
        $dir = $namespace ? $this->cacheDir . '/' . $this->sanitizeNamespace($namespace) : $this->cacheDir;
        $count = 0;

        if (!is_dir($dir)) {
            return 0;
        }

        // Recursively find all .json files
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $data = json_decode($content, true);

            if (!$data || !isset($data['created_at'])) {
                continue;
            }

            if ((time() - (int)$data['created_at']) > $maxAgeSeconds) {
                if (@unlink($file->getPathname())) {
                    $count++;
                }
            }
        }

        if ($count > 0) {
            $this->logger->info("Stale cache cleaned", [
                'namespace' => $namespace ?: 'all',
                'files_deleted' => $count
            ]);
        }

        return $count;
    }

    /**
     * Get cache file path for key and namespace
     */
    private function getCacheFilePath(string $key, string $namespace): string
    {
        $sanitizedKey = $this->sanitizeKey($key);
        
        if ($namespace) {
            $sanitizedNamespace = $this->sanitizeNamespace($namespace);
            return $this->cacheDir . '/' . $sanitizedNamespace . '/' . $sanitizedKey . '.json';
        }

        return $this->cacheDir . '/' . $sanitizedKey . '.json';
    }

    /**
     * Sanitize cache key to valid filename
     */
    private function sanitizeKey(string $key): string
    {
        // Replace invalid filename characters
        $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        
        // Limit length and add hash suffix for uniqueness
        if (strlen($sanitized) > 200) {
            $hash = md5($key);
            $sanitized = substr($sanitized, 0, 180) . '_' . $hash;
        }

        return $sanitized;
    }

    /**
     * Sanitize namespace to valid directory name
     */
    private function sanitizeNamespace(string $namespace): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $namespace);
    }

    /**
     * Get cache statistics
     */
    public function getStats(string $namespace = ''): array
    {
        $dir = $namespace ? $this->cacheDir . '/' . $this->sanitizeNamespace($namespace) : $this->cacheDir;

        if (!is_dir($dir)) {
            return [
                'total_files' => 0,
                'expired_files' => 0,
                'total_size' => 0
            ];
        }

        $totalFiles = 0;
        $expiredFiles = 0;
        $totalSize = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $totalFiles++;
            $totalSize += $file->getSize();

            $content = file_get_contents($file->getPathname());
            $data = json_decode($content, true);

            if ($data && isset($data['expires_at']) && $data['expires_at'] < time()) {
                $expiredFiles++;
            }
        }

        return [
            'total_files' => $totalFiles,
            'expired_files' => $expiredFiles,
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2)
        ];
    }
}
