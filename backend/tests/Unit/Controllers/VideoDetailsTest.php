<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\VideoController;
use App\Domain\Services\YouTubeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `get_youtube_video_details`, la acción que le faltaba al medio.
 *
 * Vídeos era el único de los cinco cuya ficha no se podía pedir por su id: solo
 * había búsqueda por texto, así que la ficha de un vídeo que no tuvieras
 * guardado no se podía pintar. El método del servicio ya existía sin ningún
 * consumidor; esto fija lo que el controller pone a su alrededor.
 *
 * Lo que se prueba aquí son las tres respuestas y el envoltorio, no la llamada a
 * YouTube: el servicio es un doble. Que la acción esté declarada en los tres
 * sitios se comprobó con `curl` contra el backend de dev, que es lo único que lo
 * ve —un mock no distingue una acción bien cableada de una a medias—.
 */
class VideoDetailsTest extends TestCase
{
    private function controller(?array $ficha): VideoController
    {
        $youtube = $this->createMock(YouTubeService::class);
        $youtube->method('getVideoDetails')->willReturn($ficha);

        // Catorce dependencias, y solo participa la última: las demás van como
        // dobles por lo que exige el constructor, no porque el test las use.
        return new VideoController(
            $this->createMock(\App\Domain\UseCases\Videos\AddVideoUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\DeleteVideoUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\UpdateVideoRatingUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\UpdateVideoUserStatusesUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\GetVideosUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\GetVideoAllowedStatusesUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\EditUserVideoUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\GetTrendingVideosUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\AddVideoNoteUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\GetVideoNotesUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\UpdateVideoNoteUseCase::class),
            $this->createMock(\App\Domain\UseCases\Videos\DeleteVideoNoteUseCase::class),
            $this->createMock(\App\Domain\Repository\Video\VideoTagRepositoryInterface::class),
            $youtube
        );
    }

    #[Test]
    public function returns_the_video_wrapped_under_its_key(): void
    {
        $ficha = [
            'youtube_id'   => 'dQw4w9WgXcQ',
            'title'        => 'Un vídeo',
            'channel_name' => 'Un canal',
            'cover_url'    => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
        ];

        $r = $this->controller($ficha)->getVideoDetails(['youtubeId' => 'dQw4w9WgXcQ']);

        $this->assertSame('success', $r['status']);
        // El envoltorio importa: el `enrich` del registry lee `data.video`.
        $this->assertSame($ficha, $r['data']['video']);
    }

    #[Test]
    public function accepts_the_three_spellings_of_the_id(): void
    {
        $ficha = ['youtube_id' => 'abc', 'title' => 'Un vídeo'];

        foreach (['youtubeId', 'youtube_id', 'videoId'] as $clave) {
            $r = $this->controller($ficha)->getVideoDetails([$clave => 'abc']);
            $this->assertSame('success', $r['status'], "Falla con la clave $clave");
        }
    }

    #[Test]
    public function missing_id_is_a_400_not_a_lookup(): void
    {
        $r = $this->controller(null)->getVideoDetails([]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(400, $r['http_code']);
    }

    #[Test]
    public function unknown_video_is_a_404(): void
    {
        // Un vídeo que YouTube no tiene no es un fallo del proveedor: el
        // servicio devuelve null y eso es una respuesta, no una degradación.
        $r = $this->controller(null)->getVideoDetails(['youtubeId' => 'noExiste']);

        $this->assertSame('error', $r['status']);
        $this->assertSame(404, $r['http_code']);
    }
}
