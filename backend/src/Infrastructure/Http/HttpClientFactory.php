<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Única fábrica de clientes HTTP salientes.
 *
 * Antes de esto, cada servicio hacía su propio `new Client([...])` con sus
 * constantes, y no había ni clase base ni trait: escribir una política —un
 * timeout, un `User-Agent`, un reintento— costaba nueve ediciones o ninguna. Y
 * era «ninguna»: no había un solo reintento ni un solo `sleep` en todo
 * `backend/src`, así que un 429 de un proveedor se degradaba directamente a
 * caché caducada sin haber esperado medio segundo a que se le pasara.
 *
 * El reintento va **en el transporte**, no en `ResilientCall`. Son dos capas
 * distintas a propósito: aquí se insiste, y por encima `ResilientCall` decide
 * si lo que ya falló de verdad se degrada a caché stale. Mezclarlas serviría la
 * caché stale con retraso, que es lo peor de los dos mundos.
 *
 * ## Los dos perfiles, y por qué no hay uno solo
 *
 * Un backoff exponencial de manual —1 s, 2 s, 4 s— es correcto en un proceso
 * por lotes e inaceptable dentro de una búsqueda que responde en 0,010 s.
 * `Retry-After` empeora la trampa: un proveedor puede pedir 60 s, y obedecerle
 * dentro de una petición HTTP deja al usuario mirando una pantalla en blanco
 * un minuto para acabar sirviendo la misma caché stale que se habría servido al
 * instante.
 *
 * | perfil  | intentos | espera             | tope total | ¿reintenta un timeout? |
 * |---------|----------|--------------------|------------|------------------------|
 * | `web`   | 2        | 250 ms + jitter    | 1 s        | **no**                 |
 * | `batch` | 5        | 1-16 s exponencial | 60 s       | sí                     |
 *
 * En `web`, un `Retry-After` mayor que el tope **no se obedece: se abandona** y
 * quien llame caerá a su caché stale. El proveedor está diciendo «no vuelvas en
 * un minuto» y el usuario no puede esperar un minuto.
 */
final class HttpClientFactory
{
    public const PROFILE_WEB   = 'web';
    public const PROFILE_BATCH = 'batch';

    /**
     * Códigos que merecen otro intento.
     *
     * 429 es cuota y 502/503/504 son el proveedor caído o saturado: los cuatro
     * suelen arreglarse solos en cientos de milisegundos. **Ningún otro 4xx
     * entra**: un 404 es una respuesta, no un fallo, y `ResilientCall` lo
     * relanza a propósito en vez de degradarlo (`ResilientCallTest.php:149`).
     */
    private const RETRYABLE_STATUSES = [429, 502, 503, 504];

    /**
     * `retry_connect` es lo que impide que este middleware doble la espera del
     * usuario. Medido el 2026-08-25: un proveedor que **agota el timeout** en
     * vez de responder cuesta 5 s por intento, así que reintentar convierte los
     * 5 s del peor caso en 10,3 s — y `runSearchStrategy` de Google Books hace
     * dos llamadas, con lo que una búsqueda pasaría de ~10 s a ~20 s. El tope
     * del perfil acota el **backoff**, no el timeout, así que no protege de
     * esto. En `web` un timeout es el final del camino y se degrada a caché
     * stale al instante; en `batch` sí se reintenta, que es donde una conexión
     * cortada suele ser transitoria y nadie está esperando.
     *
     * @var array<string, array{attempts:int, base:float, cap:float, retry_connect:bool}>
     */
    private const PROFILES = [
        self::PROFILE_WEB => [
            'attempts'      => 2,
            'base'          => 0.25,
            'cap'           => 1.0,
            'retry_connect' => false,
        ],
        self::PROFILE_BATCH => [
            'attempts'      => 5,
            'base'          => 1.0,
            'cap'           => 60.0,
            'retry_connect' => true,
        ],
    ];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @param string                                          $userAgent obligatorio: MusicBrainz
     *                                                                   rechaza al que no se
     *                                                                   identifica, y al migrar de
     *                                                                   `file_get_contents` a Guzzle
     *                                                                   es justo el header que se
     *                                                                   pierde de vista
     * @param array<string, mixed>                            $overrides opciones de Guzzle que
     *                                                                   manda quien construye
     *                                                                   (timeouts, redirecciones…)
     */
    public function create(string $profile, string $userAgent, array $overrides = []): Client
    {
        $config = self::PROFILES[$profile] ?? self::PROFILES[self::PROFILE_WEB];

        $stack = $overrides['handler'] ?? HandlerStack::create();

        // Con nombre: un `HandlerStack` sin nombrar se imprime como
        // `Name: ''` y no hay forma de saber desde fuera si la política está
        // puesta. Queda por debajo de `http_errors`, así que el decider ve la
        // respuesta cruda —el 429— antes de que Guzzle la convierta en excepción.
        $stack->push(
            Middleware::retry(
                $this->decider($profile, $config),
                $this->delay($profile, $config)
            ),
            'retry:' . $profile
        );

        // El `User-Agent` del parámetro manda sobre el de `overrides`: es
        // obligatorio justamente para que no se pueda perder de vista.
        $cabeceras = ['User-Agent' => $userAgent] + ($overrides['headers'] ?? []);

        unset($overrides['headers'], $overrides['handler']);

        return new Client([
            'handler' => $stack,
            'headers' => $cabeceras,
        ] + $overrides);
    }

    /**
     * ¿Otro intento?
     *
     * Guzzle llama a esto con `$reintento = 0` antes del primer reintento, así
     * que la comparación es con `attempts - 1`: un perfil de 2 intentos
     * reintenta una vez.
     *
     * **El tope de `Retry-After` se decide aquí, no en el retardo.** El retardo
     * solo dice cuánto esperar; devolver 0 desde allí no cancela nada, hace que
     * Guzzle reintente al instante, que es exactamente lo contrario de lo que
     * se busca. Abandonar es no reintentar.
     */
    private function decider(string $profile, array $config): callable
    {
        $logger = $this->logger;

        return static function (
            int $reintento,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?\Throwable $error = null
        ) use ($profile, $config, $logger): bool {
            if ($reintento >= $config['attempts'] - 1) {
                return false;
            }

            // Conexión que ni llega a establecerse: DNS, TLS, red caída, o el
            // timeout agotado. Solo en `batch`: ver la nota de PROFILES.
            if ($error instanceof ConnectException) {
                return $config['retry_connect'];
            }

            if ($response === null
                || !in_array($response->getStatusCode(), self::RETRYABLE_STATUSES, true)
            ) {
                return false;
            }

            $retryAfter = self::parseRetryAfter($response);

            // El proveedor pide más de lo que este perfil aguanta: se abandona
            // y quien llame caerá a su caché stale. En `web` es lo correcto —
            // «no vuelvas en un minuto» y el usuario no puede esperar un minuto.
            if ($retryAfter !== null && $retryAfter > $config['cap']) {
                $logger->info('http: Retry-After por encima del tope del perfil, se abandona', [
                    'profile'     => $profile,
                    'retry_after' => $retryAfter,
                    'cap'         => $config['cap'],
                ]);

                return false;
            }

            return true;
        };
    }

    /**
     * Cuántos **milisegundos** esperar antes del siguiente intento.
     *
     * Guzzle llama a esto ya con el contador incrementado: el primer reintento
     * llega como `$reintento = 1`, de ahí el `- 1` del exponente para que esa
     * primera espera sea exactamente la base del perfil.
     *
     * El logging es `info`, no `debug`, y es deliberado: un reintento
     * silencioso es un reintento que nadie descubre hasta que la cuota se
     * agota.
     */
    private function delay(string $profile, array $config): callable
    {
        $logger = $this->logger;

        return static function (int $reintento, ?ResponseInterface $response = null) use ($profile, $config, $logger): int {
            $retryAfter = self::parseRetryAfter($response);

            // Si viene, manda: el proveedor sabe mejor que nosotros. Que no se
            // pase del tope ya lo ha comprobado el decider.
            if ($retryAfter !== null) {
                $logger->info('http: reintento obedeciendo Retry-After', [
                    'profile'     => $profile,
                    'intento'     => $reintento,
                    'retry_after' => $retryAfter,
                ]);

                return (int) round($retryAfter * 1000);
            }

            // Exponencial con jitter. El jitter no es adorno: sin él, dos
            // peticiones que chocan con el mismo 429 reintentan a la vez y
            // vuelven a chocar.
            $espera = $config['base'] * (2 ** max(0, $reintento - 1));
            $espera = min($espera + random_int(0, 100) / 1000, $config['cap']);

            $logger->info('http: reintento con backoff', [
                'profile' => $profile,
                'intento' => $reintento,
                'espera'  => round($espera, 3),
            ]);

            return (int) round($espera * 1000);
        };
    }

    /**
     * `Retry-After` viene en dos formatos y hay que entender los dos.
     *
     * Segundos (`Retry-After: 30`) o fecha HTTP
     * (`Retry-After: Wed, 21 Oct 2026 07:28:00 GMT`). Parsear solo el primero
     * es el fallo clásico: la fecha se lee como 0, se reintenta al instante y
     * se cobra otro 429.
     *
     * @return float|null segundos, o null si no viene o no se entiende
     */
    private static function parseRetryAfter(?ResponseInterface $response): ?float
    {
        if ($response === null || !$response->hasHeader('Retry-After')) {
            return null;
        }

        $valor = trim($response->getHeaderLine('Retry-After'));

        if ($valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            return max(0.0, (float) $valor);
        }

        $fecha = strtotime($valor);

        if ($fecha === false) {
            return null;
        }

        return max(0.0, (float) ($fecha - time()));
    }
}
