<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Cache\CacheService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * El `Hecho cuando` del M2: las dos acciones del buscador general responden.
 *
 * Lo que estos tests cubren, y ningún mock puede: que `search_catalog_local` y
 * `search_catalog_remote` estén declaradas en los **tres** sitios que hay que
 * tocar en este repo —`config/routes.php`, el `match` de `ActionRouter` y el
 * método del controller—, y que el sobre llegue hasta la respuesta con las
 * claves y los tipos del contrato. Es exactamente el fallo típico de aquí: la
 * primera versión de estas dos acciones tenía ruta y controlador y respondía
 * *«Controller method not mapped»* porque faltaba el tercero.
 *
 * **La tanda remota no sale a la red, y no por comodidad.**
 * `ResilientCall::aroundMany()` atiende una entrada de caché fresca sin llegar a
 * construir la promesa, igual que `around()` (`ResilientCall.php:56-58`). Sembrar
 * la caché es lo que hace estos tests deterministas: sin eso el veredicto
 * dependería de si Google Books, IGDB y YouTube responden hoy — y Google Books,
 * el 2026-09-01, devolvía 503 con clave y 429 por cuota diaria sin ella.
 *
 * **La tanda local sí toca MySQL**, el mirror de dev, porque su gracia es
 * precisamente el SQL contra el esquema real. Por eso se afirma sobre la FORMA
 * de la respuesta y no sobre títulos concretos: el contenido del mirror depende
 * de qué dumps estén importados, y un test que exija «Dune» se rompe el día que
 * alguien reimporte.
 */
class CatalogSearchTest extends IntegrationTestCase
{
    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    private function cache(): CacheService
    {
        return $this->container()->get(CacheService::class);
    }

    // ── La tanda local ──────────────────────────────────────────────────────

    #[Test]
    public function local_search_answers_with_the_three_mirror_media(): void
    {
        $r = $this->router()->dispatch('search_catalog_local', ['query' => 'dune', 'limit' => 5]);

        $this->assertSame('success', $r['status'], 'La acción tiene que estar en los tres sitios');
        $this->assertArrayHasKey('results', $r['data']);

        // Los tres medios que sirve el mirror, siempre presentes aunque vengan
        // vacíos: el frontend itera las claves y una ausente lo rompería.
        foreach (['movie', 'series', 'album'] as $medio) {
            $this->assertArrayHasKey($medio, $r['data']['results'], "Falta el medio {$medio}");
            $this->assertIsArray($r['data']['results'][$medio]);
        }

        $this->assertSame(
            array_sum(array_map('count', $r['data']['results'])),
            $r['data']['count'],
            '`count` es el total de los tres, no el de uno'
        );
    }

    #[Test]
    public function local_search_does_not_send_freshness_keys(): void
    {
        $r = $this->router()->dispatch('search_catalog_local', ['query' => 'dune', 'limit' => 5]);

        // Lo que sirve un dump local no puede estar rancio. Es la misma razón por
        // la que `mediaRegistry.js` no declara `supportsStale` en estos tres.
        $this->assertArrayNotHasKey('stale', $r['data']);
        $this->assertArrayNotHasKey('cached_at', $r['data']);
    }

    #[Test]
    public function local_search_rejects_an_empty_query(): void
    {
        $r = $this->router()->dispatch('search_catalog_local', ['query' => '   ']);

        $this->assertSame('error', $r['status']);
        $this->assertSame(400, $r['http_code']);
    }

    // ── La tanda remota ─────────────────────────────────────────────────────

    /**
     * Siembra las tres cachés para que `aroundMany` responda sin tocar la red.
     * Las claves son las que construye `SearchCatalogRemoteUseCase`; se repiten
     * aquí a propósito, para que cambiarlas rompa este test en vez de que se
     * adapte en silencio.
     */
    private function sembrarLosTres(string $query, int $limit): array
    {
        // Los libros se cachean **ya mapeados a fila**, no como volúmenes crudos
        // de Google: es lo que espera `search.transform` del registry, y servir la
        // forma cruda era el bug que dejaba `/search` sin un solo libro. El
        // namespace lleva versión por eso mismo — una caché con la forma vieja
        // devolvería libros que no se pintan.
        $libros  = [['isbn' => '9780441013593', 'title' => 'Dune', 'author' => ['Frank Herbert'],
                     'cover_i' => 'https://books.google.com/x.jpg', 'publisher' => 'Ace',
                     'pages' => 688, 'genres' => ['Fiction']]];
        $juegos  = [['id' => 1, 'name' => 'Dune II']];
        $videos  = [['youtube_id' => 'abc', 'title' => 'Dune trailer']];

        $this->cache()->set('search_general_' . md5($query . '_' . $limit), $libros, 3600, 'googlebooks_rows_v2');
        $this->cache()->set('search_' . md5($query . '_' . $limit), $juegos, 3600, 'igdb');
        $this->cache()->set('search_' . md5($query . '_' . $limit), $videos, 3600, 'youtube');

        return ['book' => $libros, 'game' => $juegos, 'video' => $videos];
    }

    #[Test]
    public function remote_search_answers_with_the_three_network_media(): void
    {
        $sembrado = $this->sembrarLosTres('dune', 20);

        $r = $this->router()->dispatch('search_catalog_remote', ['query' => 'dune', 'limit' => 20]);

        $this->assertSame('success', $r['status'], 'La acción tiene que estar en los tres sitios');

        foreach ($sembrado as $medio => $esperado) {
            $this->assertSame($esperado, $r['data']['results'][$medio], "El medio {$medio} no llegó entero");
        }
    }

    #[Test]
    public function remote_search_reports_freshness_per_medium_and_not_loose(): void
    {
        $this->sembrarLosTres('dune', 20);

        $r = $this->router()->dispatch('search_catalog_remote', ['query' => 'dune', 'limit' => 20]);

        // Por medio y no sueltos: los tres degradan por separado y quien pinte el
        // aviso necesita saber de cuál habla.
        $this->assertIsArray($r['data']['stale']);
        $this->assertIsArray($r['data']['cached_at']);

        foreach (['book', 'game', 'video'] as $medio) {
            $this->assertArrayHasKey($medio, $r['data']['stale']);
            $this->assertFalse($r['data']['stale'][$medio], 'Una copia dentro de su TTL no es rancia');
            $this->assertIsString($r['data']['cached_at'][$medio]);
            $this->assertNotFalse(
                strtotime($r['data']['cached_at'][$medio]),
                'cached_at viaja en ISO 8601 (date("c")), no como epoch'
            );
        }
    }

    #[Test]
    public function remote_search_reports_no_failures_when_everything_answers(): void
    {
        $this->sembrarLosTres('dune', 20);

        $r = $this->router()->dispatch('search_catalog_remote', ['query' => 'dune', 'limit' => 20]);

        // `failed` es una lista de medios, no un booleano suelto: un proveedor
        // caído no puede tumbar la búsqueda de los otros dos.
        $this->assertIsArray($r['data']['failed']);
        $this->assertSame([], $r['data']['failed']);
    }

    #[Test]
    public function remote_search_rejects_an_empty_query(): void
    {
        $r = $this->router()->dispatch('search_catalog_remote', ['query' => '']);

        $this->assertSame('error', $r['status']);
        $this->assertSame(400, $r['http_code']);
    }

    #[Test]
    public function both_actions_cap_the_limit_the_same_way(): void
    {
        // El mismo tope de 50 que ya impone `BookController::searchWorks:484`.
        // Se comprueba por el lado local, que es el que no necesita red: pedir
        // 9999 tiene que responder, no reventar ni traer 9999 filas.
        $r = $this->router()->dispatch('search_catalog_local', ['query' => 'dune', 'limit' => 9999]);

        $this->assertSame('success', $r['status']);
        foreach ($r['data']['results'] as $medio => $items) {
            $this->assertLessThanOrEqual(50, count($items), "El medio {$medio} ignoró el tope");
        }
    }
}
