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
     * La misma política que `around()`, pero para varias llamadas a la vez
     *
     * Existe porque el buscador general consulta tres proveedores de red y
     * hacerlo en serie cuesta la SUMA en vez del máximo: medido el 2026-09-01,
     * 2,7 s contra 1,7 s. La concurrencia solo aparece si las promesas salen del
     * MISMO cliente de Guzzle —cada `Client` trae su handler de cURL y `wait()`
     * solo hace avanzar el suyo—, así que quien llame construye un cliente y se
     * lo pasa a los tres.
     *
     * Va en dos fases a propósito:
     *   1. Se mira la caché de los tres. Lo fresco se sirve sin tocar la red.
     *   2. Los que quedan salen JUNTOS, y a cada respuesta se le aplica el mismo
     *      éxito-o-degradación que `around()`.
     *
     * `around()` no se toca: lo consumen nueve servicios y su contrato es el que
     * es. Esto es un hermano por lotes, no un sustituto.
     *
     * @param array<string, array{key: string, namespace: string, ttl: int, promise: callable(): \GuzzleHttp\Promise\PromiseInterface, parse: callable(mixed): mixed}> $specs
     *        Una entrada por medio. `promise` construye la petición sin esperarla;
     *        `parse` convierte la respuesta cruda en lo que se cachea.
     * @return array<string, array{data: mixed, stale: bool, cached_at: int|null, failed: bool}>
     *         Nunca lanza: un medio que falla sin caché sale con `failed: true` y
     *         `data: []`. Es la diferencia deliberada con `around()`, que sí lanza:
     *         aquí un proveedor caído no puede tumbar la búsqueda de los otros dos.
     */
    public function aroundMany(array $specs, int $maxAgeSeconds = CacheService::STALE_MAX_AGE): array
    {
        $salida = [];
        $pendientes = [];
        $cacheados = [];

        // ── Fase 1: la caché, que no cuesta red ────────────────────────────
        foreach ($specs as $medio => $spec) {
            $cached = $this->cache->getStale($spec['key'], $spec['namespace'], $maxAgeSeconds);

            if ($cached !== null && !$cached['is_stale']) {
                $salida[$medio] = $this->fresh($cached['value'], $cached['cached_at']) + ['failed' => false];
                continue;
            }

            $cacheados[$medio]  = $cached;   // puede ser null: no hay red de seguridad
            $pendientes[$medio] = $spec['promise']();
        }

        if ($pendientes === []) {
            return $salida;
        }

        // ── Fase 2: los que faltan, todos a la vez ─────────────────────────
        $resueltas = \GuzzleHttp\Promise\Utils::settle($pendientes)->wait();

        foreach ($resueltas as $medio => $resultado) {
            $spec   = $specs[$medio];
            $cached = $cacheados[$medio];

            if ($resultado['state'] === 'fulfilled') {
                try {
                    $data = $spec['parse']($resultado['value']);
                    $this->cache->setResilient($spec['key'], $data, $spec['ttl'], $spec['namespace']);
                    $salida[$medio] = $this->fresh($data, time()) + ['failed' => false];
                    continue;
                } catch (Throwable $e) {
                    // Una respuesta que llega pero no se puede leer cuenta como
                    // fallo del proveedor: cae al mismo camino de degradación.
                    $resultado['reason'] = $e;
                }
            }

            $salida[$medio] = $this->degradar($medio, $spec, $cached, $resultado['reason']);
        }

        return $salida;
    }

    /**
     * Qué se sirve cuando un proveedor falla: la caché rancia si la hay, y si no
     * una lista vacía marcada, para que la búsqueda de los otros dos siga en pie.
     *
     * @return array{data: mixed, stale: bool, cached_at: int|null, failed: bool}
     */
    private function degradar(string $medio, array $spec, ?array $cached, Throwable $e): array
    {
        if ($cached === null) {
            $this->logger->warning('Batch resilient call failed with no stale fallback', [
                'medio' => $medio,
                'key' => $spec['key'],
                'namespace' => $spec['namespace'],
                'error' => $e->getMessage(),
            ]);

            return ['data' => [], 'stale' => false, 'cached_at' => null, 'failed' => true];
        }

        $this->logger->warning('Batch resilient call degraded to stale cache', [
            'medio' => $medio,
            'key' => $spec['key'],
            'cached_at' => date('c', $cached['cached_at']),
            'error' => $e->getMessage(),
        ]);

        return [
            'data' => $cached['value'],
            'stale' => true,
            'cached_at' => $cached['cached_at'],
            'failed' => false,
        ];
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
