<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\UseCases\Search\SearchCatalogLocalUseCase;
use App\Domain\UseCases\Search\SearchCatalogRemoteUseCase;
use Psr\Log\LoggerInterface;

/**
 * El buscador general: una consulta, los seis medios.
 *
 * Vive en su propio controlador y no repartido por medio **porque su unidad es
 * la consulta, no la entidad**. Las dos acciones están partidas por VELOCIDAD y
 * no por dominio: la local sale del mirror y responde en milisegundos, la remota
 * sale a internet. Eso permite pintar media pantalla antes de que la red
 * conteste, con dos peticiones por búsqueda en vez de cinco — y de paso deja el
 * techo de 60 req/min por IP lejos, que con cinco llamadas quedaría a tiro.
 */
class SearchController extends BaseController
{
    public function __construct(
        private readonly SearchCatalogLocalUseCase $local,
        private readonly SearchCatalogRemoteUseCase $remote,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Películas, series y álbumes: todo del mirror, en milisegundos.
     *
     * No manda `stale` ni `cached_at`: lo que sirve un dump local no puede estar
     * rancio, que es justo lo que `mediaRegistry.js` ya documenta al no declarar
     * `supportsStale` en estos tres medios.
     */
    public function searchCatalogLocal(array $data): array
    {
        [$query, $limit, $error] = $this->leerEntrada($data);
        if ($error !== null) {
            return $error;
        }

        try {
            $results = $this->local->execute($query, $limit);

            return $this->successResponse('Local catalog search completed', [
                'results' => $results,
                'count' => array_sum(array_map('count', $results)),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Búsqueda local del catálogo falló', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Local catalog search failed', 500);
        }
    }

    /**
     * Libros, juegos y vídeos: los tres a la vez, no uno detrás de otro.
     *
     * `stale`, `cached_at` y `failed` van **por medio** y no sueltos, porque los
     * tres degradan por separado y quien pinte el aviso necesita saber de cuál
     * habla. Un proveedor caído no tumba la búsqueda: sale en `failed` y los
     * otros dos responden igual.
     */
    public function searchCatalogRemote(array $data): array
    {
        [$query, $limit, $error] = $this->leerEntrada($data);
        if ($error !== null) {
            return $error;
        }

        try {
            $r = $this->remote->execute($query, $limit);

            return $this->successResponse('Remote catalog search completed', [
                'results' => $r['results'],
                'count' => array_sum(array_map('count', $r['results'])),
                'stale' => $r['stale'],
                'cached_at' => array_map(
                    static fn (?int $ts): ?string => $ts !== null ? date('c', $ts) : null,
                    $r['cached_at']
                ),
                'failed' => $r['failed'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Búsqueda remota del catálogo falló', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return $this->externalServiceError('catalog search');
        }
    }

    /**
     * La misma entrada para las dos acciones, para que no se separen por
     * descuido: mismo nombre de parámetro y mismo tope.
     *
     * @return array{0: string, 1: int, 2: array|null}
     */
    private function leerEntrada(array $data): array
    {
        $query = trim((string) ($data['query'] ?? ''));
        if ($query === '') {
            return ['', 0, $this->errorResponse('Query parameter is required', 400)];
        }

        // El mismo tope de 50 que ya impone `BookController::searchWorks:484`.
        $limit = min(isset($data['limit']) ? (int) $data['limit'] : 20, 50);

        return [$query, max(1, $limit), null];
    }
}
