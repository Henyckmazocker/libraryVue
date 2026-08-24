<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs an external API call with the cache as a safety net
 *
 * On success the result is cached and returned fresh. On a degradable failure
 * (network error, 429, 5xx) the last known value is served from the cache and
 * flagged as stale, instead of the empty list the services return today.
 *
 * The policy lives here and not in each service on purpose: get() deletes the
 * file when it finds it expired, so whoever reads stale data has to be the same
 * code that decides whether to read fresh data at all.
 */
final class ResilientCall
{
    /** HTTP statuses that mean "the provider failed", as opposed to "it answered no" */
    private const DEGRADABLE_STATUSES = [408, 425, 429, 500, 502, 503, 504];

    private CacheService $cache;
    private LoggerInterface $logger;

    public function __construct(CacheService $cache, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Execute $fetch, falling back to stale cache when it fails
     *
     * @param string $key Cache key
     * @param string $namespace Cache namespace (bump it whenever the cached shape changes)
     * @param int $ttl Time to live for a fresh result, in seconds
     * @param callable $fetch The API call; whatever it returns is what gets cached
     * @param int $maxAgeSeconds Hard cap for the stale fallback
     * @return array{data: mixed, stale: bool, cached_at: int|null}
     * @throws Throwable When the call fails and there is nothing usable in the cache
     */
    public function around(
        string $key,
        string $namespace,
        int $ttl,
        callable $fetch,
        int $maxAgeSeconds = CacheService::STALE_MAX_AGE
    ): array {
        $cached = $this->cache->getStale($key, $namespace, $maxAgeSeconds);

        // A fresh entry is served without hitting the API at all
        if ($cached !== null && !$cached['is_stale']) {
            return $this->fresh($cached['value'], $cached['cached_at']);
        }

        try {
            $data = $fetch();
            $this->cache->setResilient($key, $data, $ttl, $namespace);

            return $this->fresh($data, time());

        } catch (Throwable $e) {
            if (!$this->isDegradable($e)) {
                throw $e;
            }

            if ($cached === null) {
                $this->logger->warning("Resilient call failed with no stale fallback", [
                    'key' => $key,
                    'namespace' => $namespace,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

            $this->logger->warning("Resilient call degraded to stale cache", [
                'key' => $key,
                'namespace' => $namespace,
                'cached_at' => date('c', $cached['cached_at']),
                'error' => $e->getMessage()
            ]);

            return [
                'data' => $cached['value'],
                'stale' => true,
                'cached_at' => $cached['cached_at']
            ];
        }
    }

    /**
     * Whether a failure means "the provider is down" rather than "it said no"
     *
     * A 404 is a legitimate answer: serving stale data there would be a bug, not
     * resilience. Anything that is not an HTTP response at all (connection reset,
     * timeout, DNS) is degradable by definition.
     */
    private function isDegradable(Throwable $e): bool
    {
        if ($e instanceof BadResponseException) {
            return in_array($e->getResponse()->getStatusCode(), self::DEGRADABLE_STATUSES, true);
        }

        return $e instanceof \GuzzleHttp\Exception\GuzzleException
            || $e instanceof \RuntimeException;
    }

    /** @return array{data: mixed, stale: bool, cached_at: int|null} */
    private function fresh(mixed $data, ?int $cachedAt): array
    {
        return ['data' => $data, 'stale' => false, 'cached_at' => $cachedAt];
    }
}
