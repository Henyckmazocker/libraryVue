<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Catalog;

use App\Domain\Repository\Catalog\AlbumCatalogInterface;
use App\Infrastructure\Catalog\FallbackAlbumCatalog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Cubre las reglas de caída del decorador, con dobles.
 *
 * Es donde vive la decisión del plan entero: cuándo se sale a Spotify y cuándo
 * no. Si estas reglas se aflojan, la app vuelve a depender de una API cuyas
 * condiciones prohíben justo lo que la app hace, y nadie se entera.
 */
class FallbackAlbumCatalogTest extends TestCase
{
    private const MBID = 'b1392450-e666-3926-a536-22c65f834433';
    private const BASE62 = '2bhYZTFBsnB3IXItHQBmUV';

    /** Un catálogo de mentira que registra si le preguntaron */
    private function catalog(array $searchResult, ?array $findResult, bool $throws = false): AlbumCatalogInterface
    {
        return new class ($searchResult, $findResult, $throws) implements AlbumCatalogInterface {
            public bool $searchCalled = false;
            public bool $findCalled    = false;
            public bool $barcodeCalled = false;

            public function __construct(
                private array $searchResult,
                private ?array $findResult,
                private bool $throws
            ) {}

            public function search(string $query, int $limit = 20): array
            {
                $this->searchCalled = true;
                if ($this->throws) {
                    throw new RuntimeException('sin red');
                }

                return $this->searchResult;
            }

            public function findById(string $id): ?array
            {
                $this->findCalled = true;
                if ($this->throws) {
                    throw new RuntimeException('sin red');
                }

                return $this->findResult;
            }

            public function findByBarcode(string $barcode): ?array
            {
                $this->barcodeCalled = true;

                return $this->findResult;
            }
        };
    }

    // =========================================================================
    // search
    // =========================================================================

    #[Test]
    public function si_el_mirror_encuentra_algo_no_se_pregunta_a_spotify(): void
    {
        $local  = $this->catalog([['id' => self::MBID]], null);
        $remote = $this->catalog([['id' => 'no debería verse']], null);

        $resultado = (new FallbackAlbumCatalog($local, $remote, new NullLogger()))->search('ok computer');

        $this->assertSame([['id' => self::MBID]], $resultado);
        $this->assertFalse($remote->searchCalled, 'No se debe salir a la red si el mirror responde');
    }

    #[Test]
    public function si_el_mirror_no_encuentra_nada_se_cae_a_spotify(): void
    {
        // A diferencia de las películas, aquí SÍ se cae: el mirror solo guarda
        // release groups de tipo Album y EP, así que un single o un disco
        // recién salido está genuinamente ausente, no es que sea poco popular.
        $local  = $this->catalog([], null);
        $remote = $this->catalog([['id' => self::BASE62]], null);

        $resultado = (new FallbackAlbumCatalog($local, $remote, new NullLogger()))->search('un single de ayer');

        $this->assertSame([['id' => self::BASE62]], $resultado);
        $this->assertTrue($remote->searchCalled);
    }

    #[Test]
    public function si_spotify_revienta_se_devuelve_vacio_en_vez_de_un_500(): void
    {
        // Sin red, o sin credenciales de Spotify, la app degrada. Que la
        // búsqueda no encuentre nada es malo; que la petición muera, peor.
        $local  = $this->catalog([], null);
        $remote = $this->catalog([], null, throws: true);

        $resultado = (new FallbackAlbumCatalog($local, $remote, new NullLogger()))->search('lo que sea');

        $this->assertSame([], $resultado);
    }

    // =========================================================================
    // findById — enruta por la FORMA del id, no por un fallo
    // =========================================================================

    #[Test]
    public function un_mbid_no_se_le_pregunta_nunca_a_spotify(): void
    {
        // Spotify devolvería 404 seguro: no conoce los MBID. Preguntárselo es
        // un viaje de ida y vuelta tirado.
        $local  = $this->catalog([], ['id' => self::MBID]);
        $remote = $this->catalog([], ['id' => 'no debería verse']);

        $resultado = (new FallbackAlbumCatalog($local, $remote, new NullLogger()))->findById(self::MBID);

        $this->assertSame(['id' => self::MBID], $resultado);
        $this->assertFalse($remote->findCalled);
    }

    #[Test]
    public function un_id_de_spotify_no_se_busca_nunca_en_el_mirror(): void
    {
        // El mirror se indexa por MBID, así que la fila no existiría.
        $local  = $this->catalog([], ['id' => 'no debería verse']);
        $remote = $this->catalog([], ['id' => self::BASE62]);

        $resultado = (new FallbackAlbumCatalog($local, $remote, new NullLogger()))->findById(self::BASE62);

        $this->assertSame(['id' => self::BASE62], $resultado);
        $this->assertFalse($local->findCalled);
    }

    #[Test]
    public function un_mbid_ausente_en_el_mirror_devuelve_null_sin_tocar_la_red(): void
    {
        $local  = $this->catalog([], null);
        $remote = $this->catalog([], ['id' => 'no debería verse']);

        $resultado = (new FallbackAlbumCatalog($local, $remote, new NullLogger()))->findById(self::MBID);

        $this->assertNull($resultado);
        $this->assertFalse($remote->findCalled);
    }

    // =========================================================================
    // El discriminador
    // =========================================================================

    #[Test]
    public function el_codigo_de_barras_solo_se_busca_en_el_mirror(): void
    {
        // Preguntarle a Spotify por un barcode devolvería contenido de Spotify,
        // y el único motivo por el que este método existe es no persistirlo.
        $local  = $this->catalog([], ['id' => self::MBID]);
        $remote = $this->catalog([], ['id' => 'no debería verse']);

        $resultado = (new FallbackAlbumCatalog($local, $remote, new NullLogger()))->findByBarcode('724385522925');

        $this->assertSame(['id' => self::MBID], $resultado);
        $this->assertTrue($local->barcodeCalled);
        $this->assertFalse($remote->barcodeCalled);
    }

    #[Test]
    public function reconoce_un_mbid_y_lo_distingue_de_un_id_de_spotify(): void
    {
        $this->assertTrue(FallbackAlbumCatalog::isMusicBrainzId(self::MBID));
        $this->assertTrue(FallbackAlbumCatalog::isMusicBrainzId(strtoupper(self::MBID)));
        $this->assertFalse(FallbackAlbumCatalog::isMusicBrainzId(self::BASE62));
        $this->assertFalse(FallbackAlbumCatalog::isMusicBrainzId(''));
        // 36 caracteres pero sin la forma de un UUID
        $this->assertFalse(FallbackAlbumCatalog::isMusicBrainzId(str_repeat('a', 36)));
    }
}
