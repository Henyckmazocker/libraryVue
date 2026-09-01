<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Search;

use App\Domain\Services\GoogleBooksService;
use App\Domain\Services\IGDBService;
use App\Domain\Services\YouTubeService;
use App\Infrastructure\Cache\ResilientCall;
use App\Infrastructure\Http\HttpClientFactory;
use Psr\Log\LoggerInterface;

/**
 * La mitad lenta del buscador general: los tres medios que salen a internet.
 *
 * Libros, juegos y vídeos dependen de Google Books, IGDB y YouTube. Si se
 * consultaran uno detrás de otro, la respuesta costaría la SUMA y no el máximo
 * —medido el 2026-09-01: 2,7 s contra 1,7 s—, y con eso el buscador general
 * sería más lento que abrir la página del medio que te interesa.
 *
 * ## Por qué el cliente se crea AQUÍ y se les pasa
 *
 * La concurrencia de Guzzle solo aparece si las promesas salen del **mismo**
 * `Client`: cada uno trae su propio handler de cURL y `wait()` solo hace avanzar
 * el suyo. Medido: tres clientes distintos dan una ventana igual a la suma
 * (5070 ms contra 5068 ms de suma) y uno compartido la da igual a la petición
 * más larga (3305 ms). Los tres servicios se fabrican el suyo en el constructor
 * para su uso normal, y eso no cambia; aquí se les presta uno.
 *
 * Los timeouts distintos que cada servicio tiene —5 s libros, 10 s juegos y
 * vídeos— no se pierden: viajan **por petición**, dentro de cada `*Promise()`.
 *
 * ## Por qué no se comparte el HandlerStack, que también funcionaría
 *
 * `HttpClientFactory::create()` hace `push` del middleware de reintento sobre el
 * stack que le pasen (`:117`), así que tres `create()` sobre el mismo stack lo
 * dejan con la política apilada seis veces y un 429 se reintentaría en cascada.
 * Arreglarlo obligaría a tocar una fábrica que comparten nueve servicios.
 */
final class SearchCatalogRemoteUseCase
{
    /** Los mismos TTL que usa cada servicio en su camino síncrono. */
    private const TTL = ['book' => 21600, 'game' => 21600, 'video' => 1800];

    public function __construct(
        private readonly GoogleBooksService $books,
        private readonly IGDBService $games,
        private readonly YouTubeService $videos,
        private readonly ResilientCall $resilient,
        private readonly HttpClientFactory $http,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Reintenta una promesa que falló con un error degradable
     *
     * **Solo para libros, y con motivo medido.** Google Books devuelve
     * `503 Service temporarily unavailable` con `reason: backendFailed` de forma
     * intermitente y muy a menudo: el 2026-09-01, de ocho consultas nuevas,
     * **dos** entraban al primer intento y **cuatro no entraban nunca** en tres.
     * Cada fallo dejaba `/search` sin libros y sin cachear nada, así que —a
     * diferencia de `/books`, que tiene meses de caché a la que caer— no se
     * calentaba jamás y el usuario veía el aviso en toda consulta nueva.
     *
     * El perfil `web` de `HttpClientFactory` ya reintenta una vez a los 250 ms, y
     * **no se toca**: lo comparten nueve servicios y subirle los intentos
     * alargaría el peor caso de todos. Esto son dos intentos MÁS, solo aquí.
     *
     * Reintentar en serie dentro de la promesa **no rompe la concurrencia** con
     * juegos y vídeos: los tres siguen saliendo del mismo cliente y `settle`
     * espera a los tres a la vez. Lo único que crece es la ventana cuando libros
     * falla, y ese es el precio que se paga a cambio de que aparezcan.
     *
     * Lo que NO se hace es reintentar cualquier fallo: un 404 es una respuesta.
     * Se apoya en la misma idea que `isDegradable()` de `ResilientCall`.
     */
    private function conReintento(callable $crear, int $intentosExtra = 2): \GuzzleHttp\Promise\PromiseInterface
    {
        $promesa = $crear();

        for ($i = 0; $i < $intentosExtra; $i++) {
            $promesa = $promesa->otherwise(function (\Throwable $e) use ($crear) {
                if (!$this->mereceOtroIntento($e)) {
                    throw $e;
                }

                $this->logger->info('Búsqueda general: reintentando libros', [
                    'error' => substr($e->getMessage(), 0, 120),
                ]);

                return $crear();
            });
        }

        return $promesa;
    }

    /** Un 5xx o un problema de red merecen otro intento; un 404 es una respuesta. */
    private function mereceOtroIntento(\Throwable $e): bool
    {
        if ($e instanceof \GuzzleHttp\Exception\BadResponseException) {
            return in_array($e->getResponse()->getStatusCode(), [429, 500, 502, 503, 504], true);
        }

        return $e instanceof \GuzzleHttp\Exception\ConnectException;
    }

    /**
     * @return array{
     *     results: array<string, array>,
     *     stale: array<string, bool>,
     *     cached_at: array<string, int|null>,
     *     failed: list<string>
     * }
     */
    public function execute(string $query, int $limit = 20): array
    {
        // UN cliente para los tres. Sin `base_uri` ni timeout propios: cada
        // servicio pone la URL completa y su timeout en la petición.
        $cliente = $this->http->create(
            HttpClientFactory::PROFILE_WEB,
            'LibraryVue/1.0 (Educational Project)',
            ['headers' => ['Accept' => 'application/json']]
        );

        $specs = [];

        $specs['book'] = [
            'key' => 'search_general_' . md5($query . '_' . $limit),
            // Namespace propio y con versión: lo que se cachea aquí son FILAS ya
            // mapeadas, no los volúmenes crudos de `googlebooks`. Se subió a `_v2`
            // el 2026-09-01 al cambiar esa forma, que es la convención del repo:
            // una caché con la forma vieja serviría libros que no se pintan.
            'namespace' => 'googlebooks_rows_v2',
            'ttl' => self::TTL['book'],
            'promise' => fn () => $this->conReintento(
                fn () => $this->books->searchBooksPromise($cliente, $query, $limit)
            ),
            'parse' => fn ($r) => $this->books->parseVolumesResponse($r),
        ];

        $specs['game'] = [
            'key' => $this->games->searchCacheKey($query, $limit),
            'namespace' => 'igdb',
            'ttl' => self::TTL['game'],
            'promise' => fn () => $this->games->searchGamesPromise($cliente, $query, $limit),
            'parse' => fn ($r) => $this->games->parseGamesResponse($r),
        ];

        // Vídeos se cae de la tanda si no hay clave configurada, que es lo mismo
        // que hace su camino síncrono: un medio menos, no un error.
        $promesaVideos = $this->videos->searchVideosPromise($cliente, $query, $limit);
        if ($promesaVideos !== null) {
            $specs['video'] = [
                'key' => 'search_' . md5($query . '_' . $limit),
                'namespace' => 'youtube',
                'ttl' => self::TTL['video'],
                'promise' => fn () => $this->videos->searchVideosPromise($cliente, $query, $limit),
                'parse' => fn ($r) => $this->videos->parseSearchResponse($r),
            ];
        } else {
            $this->logger->info('Búsqueda general: vídeos fuera de la tanda, sin YOUTUBE_API_KEY');
        }

        $inicio = microtime(true);
        $tanda  = $this->resilient->aroundMany($specs);
        $this->logger->info('Búsqueda general remota completada', [
            'query' => $query,
            'ms' => round((microtime(true) - $inicio) * 1000),
            'medios' => array_keys($tanda),
        ]);

        $results = [];
        $stale = [];
        $cachedAt = [];
        $failed = [];

        foreach (['book', 'game', 'video'] as $medio) {
            if (!isset($tanda[$medio])) {
                // No se pidió (vídeos sin clave): ni resultados ni fallo.
                $results[$medio] = [];
                continue;
            }

            $r = $tanda[$medio];
            $results[$medio]  = $r['data'];
            $stale[$medio]    = $r['stale'];
            $cachedAt[$medio] = $r['cached_at'];

            if ($r['failed']) {
                $failed[] = $medio;
            }
        }

        return [
            'results' => $results,
            'stale' => $stale,
            'cached_at' => $cachedAt,
            'failed' => $failed,
        ];
    }
}
