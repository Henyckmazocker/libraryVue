<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Cache\CacheService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * El `Hecho cuando` del M1: las dos búsquedas externas devuelven el sobre.
 *
 * **Ningún test de aquí sale a la red, y no por comodidad.** `ResilientCall`
 * atiende una entrada de caché **fresca sin llamar al proveedor siquiera**
 * (`ResilientCall.php:56-58`), así que sembrar la caché a mano es lo que hace
 * estos tests deterministas: sin el sembrado, el veredicto dependería de si
 * IGDB y YouTube responden hoy, y `stale` saldría `true` o `false` según el día.
 *
 * Lo que cubren es justo lo que ningún mock puede: que las dos acciones estén
 * declaradas en los **tres** sitios (`config/routes.php`, el `match` de
 * `ActionRouter` y el método del controller) y que las claves nuevas lleguen
 * hasta la respuesta con el nombre y el tipo correctos.
 *
 * Lo que **no** se cubre aquí es la rama `stale: true`: forzar un fallo del
 * proveedor exigiría sustituir el servicio en el contenedor, y `GameController`
 * y `VideoController` ya están cacheados por `ActionRouter` (`:638-654`, `??=`)
 * en cuanto otro fichero de la suite despacha `add_game` o `add_video`, así que
 * el resultado dependería del orden de los tests. Esa rama se prueba, y de forma
 * determinista, en `tests/Unit/Controllers/SearchStalePropagationTest.php`.
 */
class SearchStaleTest extends IntegrationTestCase
{
    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    private function cache(): CacheService
    {
        return $this->container()->get(CacheService::class);
    }

    /**
     * La clave que construyen los dos servicios: 'search_' + md5(query_limite).
     *
     * Se repite aquí a propósito en vez de exponerla desde el servicio: si
     * alguien la cambia, este test tiene que fallar, no adaptarse en silencio.
     */
    private function searchKey(string $query, int $limit): string
    {
        return 'search_' . md5($query . '_' . $limit);
    }

    #[Test]
    public function igdb_search_answers_with_the_freshness_envelope(): void
    {
        $juegos = [['id' => 1022, 'name' => 'The Legend of Zelda']];

        // TTL amplio => entrada fresca => ResilientCall responde sin red.
        $this->cache()->set($this->searchKey('zelda', 20), $juegos, 3600, 'igdb');

        $r = $this->router()->dispatch('search_igdb_games', ['query' => 'zelda', 'limit' => 20]);

        $this->assertSame('success', $r['status']);
        $this->assertSame($juegos, $r['data']['games']);
        $this->assertSame(1, $r['data']['count']);

        // Las dos claves del plan, con el tipo exacto.
        $this->assertArrayHasKey('stale', $r['data']);
        $this->assertArrayHasKey('cached_at', $r['data']);
        $this->assertFalse($r['data']['stale'], 'Una copia dentro de su TTL no es rancia');
        $this->assertIsString($r['data']['cached_at']);
        $this->assertNotFalse(
            strtotime($r['data']['cached_at']),
            'cached_at viaja en ISO 8601 (date("c")), no como epoch'
        );
    }

    #[Test]
    public function youtube_search_answers_with_the_freshness_envelope(): void
    {
        $videos = [['id' => 'dQw4w9WgXcQ', 'title' => 'Un vídeo']];

        $this->cache()->set($this->searchKey('un video', 10), $videos, 3600, 'youtube');

        $r = $this->router()->dispatch('search_youtube_videos', ['q' => 'un video', 'maxResults' => 10]);

        $this->assertSame('success', $r['status']);

        // `data` es un MAPA desde el M1, no la lista pelada de antes: es la
        // enmienda del 2026-08-26 y lo que consume `VideoSearch.vue:76`.
        $this->assertArrayHasKey('videos', $r['data'], 'data.videos, no la lista en la raíz de data');
        $this->assertSame($videos, $r['data']['videos']);
        $this->assertSame(1, $r['data']['count']);

        $this->assertArrayHasKey('stale', $r['data']);
        $this->assertArrayHasKey('cached_at', $r['data']);
        $this->assertFalse($r['data']['stale']);
        $this->assertIsString($r['data']['cached_at']);
    }

    #[Test]
    public function an_empty_query_is_rejected_before_any_provider_is_touched(): void
    {
        // La verificación nº2 del plan por su lado barato: el sobre no puede
        // haber convertido un error de siempre en una franja sobre cero
        // resultados. Sin query no hay ni caché que consultar.
        foreach ([['search_igdb_games', []], ['search_youtube_videos', []]] as [$accion, $payload]) {
            $r = $this->router()->dispatch($accion, $payload);

            $this->assertSame('error', $r['status'], "{$accion} sin query tiene que ser error");
            $this->assertSame(400, $r['http_code'] ?? null);
            $this->assertArrayNotHasKey('stale', (array) ($r['data'] ?? []));
        }
    }
}
