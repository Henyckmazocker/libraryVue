<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Catalog;

use App\Infrastructure\Persistence\Catalog\MySqlAlbumCatalog;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El mapeo de fila del mirror a la forma que consume el frontend.
 *
 * Con dobles de PDO: el SQL se comprueba contra el mirror real, y lo que se
 * prueba aquí es que las claves son las que el frontend destructura. No es
 * teórico: `AlbumSearch.vue:95` lee `result.artists?.[0]?.name` y
 * `result.images?.[0]?.url`, así que si `artists` deja de ser un array de
 * objetos, la búsqueda sale sin artista y sin portada y ningún test de PHP se
 * entera.
 */
class MySqlAlbumCatalogTest extends TestCase
{
    private const MBID = 'b1392450-e666-3926-a536-22c65f834433';

    /** Una fila del mirror, con lo que se quiera sobrescrito */
    private function fila(array $cambios = []): array
    {
        return array_merge([
            'gid'                => self::MBID,
            'name'               => 'OK Computer',
            'artist_credit'      => 'Radiohead',
            'artist_gid'         => 'a74b1b7f-71a5-4011-9441-d0b5e4122711',
            'primary_type'       => 'Album',
            'first_release_date' => '1997-06-16',
            'label'              => 'Parlophone',
            'track_count'        => 12,
            'has_cover_art'      => 1,
        ], $cambios);
    }

    private function pdoQueDevuelve(array $filas): PDO
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($filas);
        $stmt->method('fetch')->willReturn($filas[0] ?? false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    // =========================================================================
    // La forma que espera el frontend
    // =========================================================================

    #[Test]
    public function el_artista_llega_como_array_de_objetos(): void
    {
        $resultado = (new MySqlAlbumCatalog($this->pdoQueDevuelve([$this->fila()])))->search('ok computer');

        $this->assertSame('Radiohead', $resultado[0]['artists'][0]['name']);
        $this->assertSame('a74b1b7f-71a5-4011-9441-d0b5e4122711', $resultado[0]['artists'][0]['id']);
    }

    #[Test]
    public function la_portada_sale_de_cover_art_archive_por_mbid(): void
    {
        $resultado = (new MySqlAlbumCatalog($this->pdoQueDevuelve([$this->fila()])))->search('ok computer');

        $this->assertSame(
            'https://coverartarchive.org/release-group/' . self::MBID . '/front-500',
            $resultado[0]['images'][0]['url']
        );
    }

    #[Test]
    public function sin_portada_el_array_de_imagenes_va_vacio_y_no_con_una_url_rota(): void
    {
        // has_cover_art existe justamente para no emitir una URL que va a
        // devolver 404 en la cara del usuario.
        $resultado = (new MySqlAlbumCatalog(
            $this->pdoQueDevuelve([$this->fila(['has_cover_art' => 0])])
        ))->search('lo que sea');

        $this->assertSame([], $resultado[0]['images']);
    }

    #[Test]
    public function el_mbid_va_en_id_porque_es_la_identidad(): void
    {
        $resultado = (new MySqlAlbumCatalog($this->pdoQueDevuelve([$this->fila()])))->search('ok computer');

        $this->assertSame(self::MBID, $resultado[0]['id']);
    }

    // =========================================================================
    // La precisión de la fecha, que albums.release_date_precision ya modela
    // =========================================================================

    #[Test]
    public function deduce_la_precision_de_la_fecha_por_su_longitud(): void
    {
        // Pares y no un array asociativo: PHP convertiría la clave '1997' en
        // int, y la columna del mirror es VARCHAR — PDO siempre da string.
        $casos = [['1997', 'year'], ['1997-06', 'month'], ['1997-06-16', 'day']];

        foreach ($casos as [$fecha, $precision]) {
            $resultado = (new MySqlAlbumCatalog(
                $this->pdoQueDevuelve([$this->fila(['first_release_date' => $fecha])])
            ))->search('computer');

            $this->assertSame($fecha, $resultado[0]['release_date']);
            $this->assertSame($precision, $resultado[0]['release_date_precision']);
        }
    }

    #[Test]
    public function sin_fecha_no_hay_precision(): void
    {
        $resultado = (new MySqlAlbumCatalog(
            $this->pdoQueDevuelve([$this->fila(['first_release_date' => null])])
        ))->search('computer');

        $this->assertNull($resultado[0]['release_date']);
        $this->assertNull($resultado[0]['release_date_precision']);
    }

    // =========================================================================
    // Lo que MusicBrainz no tiene
    // =========================================================================

    #[Test]
    public function popularity_y_duration_ms_llegan_nulos_pero_llegan(): void
    {
        // No existen en MusicBrainz. Se emiten como null en vez de omitirse
        // para que el frontend siga encontrando la clave: los pinta bajo v-if
        // (`AlbumDetailView.vue:54`) y nada ordena por ellos.
        $resultado = (new MySqlAlbumCatalog($this->pdoQueDevuelve([$this->fila()])))->search('ok computer');

        $this->assertArrayHasKey('popularity', $resultado[0]);
        $this->assertArrayHasKey('duration_ms', $resultado[0]);
        $this->assertNull($resultado[0]['popularity']);
        $this->assertNull($resultado[0]['duration_ms']);
    }

    #[Test]
    public function el_tipo_de_album_baja_a_minusculas_como_en_spotify(): void
    {
        $resultado = (new MySqlAlbumCatalog(
            $this->pdoQueDevuelve([$this->fila(['primary_type' => 'EP'])])
        ))->search('computer');

        $this->assertSame('ep', $resultado[0]['album_type']);
    }

    // =========================================================================
    // findById
    // =========================================================================

    #[Test]
    public function find_by_id_devuelve_la_misma_forma_que_search(): void
    {
        $catalog = new MySqlAlbumCatalog($this->pdoQueDevuelve([$this->fila()]));

        $this->assertSame($catalog->search('ok computer')[0], $catalog->findById(self::MBID));
    }

    #[Test]
    public function find_by_id_devuelve_null_si_no_existe(): void
    {
        $this->assertNull((new MySqlAlbumCatalog($this->pdoQueDevuelve([])))->findById(self::MBID));
    }

    // =========================================================================
    // findByBarcode — el puente que evita persistir catálogo de Spotify
    // =========================================================================

    #[Test]
    public function un_barcode_que_apunta_a_un_solo_album_resuelve(): void
    {
        $resultado = (new MySqlAlbumCatalog(
            $this->pdoQueDevuelve([$this->fila()])
        ))->findByBarcode('724385522925');

        $this->assertSame(self::MBID, $resultado['id']);
    }

    #[Test]
    public function un_barcode_ambiguo_no_resuelve(): void
    {
        // Medido sobre el dump real: hay un barcode compartido por 98 release
        // groups. Coger el más reeditado metería en la biblioteca un álbum que
        // no es el del usuario, y equivocarse de disco no se deshace solo.
        $resultado = (new MySqlAlbumCatalog(
            $this->pdoQueDevuelve([$this->fila(), $this->fila(['gid' => 'otro'])])
        ))->findByBarcode('724382781226');

        $this->assertNull($resultado);
    }

    #[Test]
    public function un_barcode_implausible_ni_se_consulta(): void
    {
        // MusicBrainz acepta lo que le pongan y tiene filas con 000000000000.
        // Un UPC/EAN real son 8 dígitos o más y no es todo ceros.
        foreach (['', '   ', '0', '000000000000', '1234567', 'abcdefgh', '12-34-5678'] as $basura) {
            $pdo = $this->createMock(PDO::class);
            $pdo->expects($this->never())->method('prepare');

            $this->assertNull(
                (new MySqlAlbumCatalog($pdo))->findByBarcode($basura),
                "El barcode '{$basura}' no debería llegar a consultarse"
            );
        }
    }

    #[Test]
    public function una_busqueda_vacia_no_llega_a_consultar(): void
    {
        // Un MATCH contra una cadena vacía es un full scan que no encuentra
        // nada; BooleanQueryBuilder devuelve '' y aquí se corta antes.
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('prepare');

        $this->assertSame([], (new MySqlAlbumCatalog($pdo))->search('a'));
    }
}
