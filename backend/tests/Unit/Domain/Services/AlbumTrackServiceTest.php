<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Services\AlbumTrackService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El aplanado de los medios de una release a una lista de pistas.
 *
 * Es la única parte pura del servicio y la única donde hay un error fácil de
 * cometer y difícil de ver: **un álbum doble tiene dos medios y cada uno
 * empieza otra vez por la pista 1**. Respetar el `position` que da la API haría
 * colisionar la clave primaria de `mb_track` y perdería medio disco en
 * silencio.
 */
class AlbumTrackServiceTest extends TestCase
{
    /** Un medio con las pistas que se le den, en la forma que devuelve la API */
    private function medium(array ...$tracks): array
    {
        return ['tracks' => $tracks];
    }

    private function track(int $position, string $number, string $title, ?int $length = 200000): array
    {
        return [
            'position' => $position,
            'number'   => $number,
            'title'    => $title,
            'length'   => $length,
            'id'       => 'rec-' . $number,
        ];
    }

    // =========================================================================
    // El caso normal
    // =========================================================================

    #[Test]
    public function mapea_los_cinco_campos_que_la_tabla_guarda(): void
    {
        $pistas = AlbumTrackService::flattenTracks([
            'media' => [$this->medium($this->track(1, '1', 'Airbag', 284400))],
        ]);

        $this->assertCount(1, $pistas);
        $this->assertSame(1, $pistas[0]['position']);
        $this->assertSame('1', $pistas[0]['number']);
        $this->assertSame('Airbag', $pistas[0]['title']);
        $this->assertSame(284400, $pistas[0]['length_ms']);
        $this->assertSame('rec-1', $pistas[0]['recording_gid']);
    }

    #[Test]
    public function una_release_sin_medios_no_da_pistas(): void
    {
        $this->assertSame([], AlbumTrackService::flattenTracks([]));
        $this->assertSame([], AlbumTrackService::flattenTracks(['media' => []]));
    }

    // =========================================================================
    // El álbum doble: la razón de ser de este test
    // =========================================================================

    #[Test]
    public function un_album_de_dos_discos_numera_de_forma_continua(): void
    {
        // Los dos medios empiezan por position 1 en la API. Si se respetara,
        // las tres pistas del segundo disco pisarían a las del primero en la PK
        // (release_group_gid, position) y el álbum perdería medio contenido.
        $pistas = AlbumTrackService::flattenTracks([
            'media' => [
                $this->medium(
                    $this->track(1, '1', 'Disco uno, pista uno'),
                    $this->track(2, '2', 'Disco uno, pista dos')
                ),
                $this->medium(
                    $this->track(1, '1', 'Disco dos, pista uno'),
                    $this->track(2, '2', 'Disco dos, pista dos')
                ),
            ],
        ]);

        $this->assertCount(4, $pistas);
        $this->assertSame([1, 2, 3, 4], array_column($pistas, 'position'));
        $this->assertSame('Disco dos, pista uno', $pistas[2]['title']);
    }

    #[Test]
    public function el_number_impreso_se_conserva_tal_cual(): void
    {
        // En un vinilo la primera de la cara B es 'B1'. position se renumera,
        // number no: es lo que el usuario ve escrito en el disco.
        $pistas = AlbumTrackService::flattenTracks([
            'media' => [
                $this->medium($this->track(1, 'A1', 'Cara A, uno')),
                $this->medium($this->track(1, 'B1', 'Cara B, uno')),
            ],
        ]);

        $this->assertSame(['A1', 'B1'], array_column($pistas, 'number'));
        $this->assertSame([1, 2], array_column($pistas, 'position'));
    }

    // =========================================================================
    // Datos incompletos, que MusicBrainz permite
    // =========================================================================

    #[Test]
    public function una_pista_sin_duracion_se_guarda_igual_con_length_nulo(): void
    {
        $pistas = AlbumTrackService::flattenTracks([
            'media' => [['tracks' => [['position' => 1, 'number' => '1', 'title' => 'Sin medir']]]],
        ]);

        $this->assertCount(1, $pistas);
        $this->assertNull($pistas[0]['length_ms']);
        $this->assertNull($pistas[0]['recording_gid']);
    }

    #[Test]
    public function una_pista_sin_titulo_se_descarta_y_no_gasta_posicion(): void
    {
        // Una fila sin título no se puede enseñar, y si consumiera posición
        // dejaría un hueco en la numeración.
        $pistas = AlbumTrackService::flattenTracks([
            'media' => [['tracks' => [
                ['position' => 1, 'number' => '1', 'title' => 'Buena'],
                ['position' => 2, 'number' => '2'],
                ['position' => 3, 'number' => '3', 'title' => ''],
                ['position' => 4, 'number' => '4', 'title' => 'Otra buena'],
            ]]],
        ]);

        $this->assertSame(['Buena', 'Otra buena'], array_column($pistas, 'title'));
        $this->assertSame([1, 2], array_column($pistas, 'position'));
    }

    // =========================================================================
    // Contra la respuesta real de la API
    // =========================================================================

    #[Test]
    public function aplana_la_respuesta_real_de_ok_computer(): void
    {
        $fixture = __DIR__ . '/fixtures/musicbrainz-release-ok-computer.json';
        $release = json_decode((string) file_get_contents($fixture), true)['releases'][0];

        $pistas = AlbumTrackService::flattenTracks($release);

        // 12 es el track_count que el mirror ya tiene guardado para este álbum.
        $this->assertCount(12, $pistas);
        $this->assertSame('Airbag', $pistas[0]['title']);
        $this->assertSame('Paranoid Android', $pistas[1]['title']);
        $this->assertSame(range(1, 12), array_column($pistas, 'position'));
        $this->assertNotNull($pistas[0]['length_ms']);
    }
}
