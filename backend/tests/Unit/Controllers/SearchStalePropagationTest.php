<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\GameController;
use App\Controllers\VideoController;
use App\Domain\Services\IGDBService;
use App\Domain\Services\YouTubeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La rama que los tests de integración no pueden forzar: `stale: true`.
 *
 * Provocar una degradación de verdad exige que el proveedor falle, y eso en
 * integración depende de la red y del orden de los tests (ver la cabecera de
 * `tests/Integration/SearchStaleTest.php`). Aquí el servicio es un doble y
 * devuelve el sobre que `ResilientCall` devolvería tras caer a la caché rancia,
 * así que lo que se prueba es exactamente lo que este hito añadió: que el
 * controller **no se coma** los dos campos por el camino.
 *
 * Los dos casos de `cached_at` importan por igual. Que sea `null` con
 * `stale: true` no es un imposible: pasa con una copia guardada sin marca de
 * tiempo, y el frontend tiene que poder distinguirlo para no pintar
 * `Invalid Date`.
 */
class SearchStalePropagationTest extends TestCase
{
    private function gameController(array $sobre): GameController
    {
        $igdb = $this->createMock(IGDBService::class);
        $igdb->method('searchGamesResilient')->willReturn($sobre);

        // Trece dependencias, y solo una participa: las demás van como dobles
        // por lo que exige el constructor, no porque el test las use.
        return new GameController(
            $this->createMock(\App\Domain\UseCases\Games\AddGameUseCase::class),
            $this->createMock(\App\Domain\UseCases\Games\DeleteGameUseCase::class),
            $this->createMock(\App\Domain\UseCases\Games\UpdateGameRatingUseCase::class),
            $this->createMock(\App\Domain\UseCases\Games\UpdateGameUserStatusesUseCase::class),
            $this->createMock(\App\Domain\UseCases\Games\GetGamesUseCase::class),
            $this->createMock(\App\Domain\UseCases\Games\GetGameAllowedStatusesUseCase::class),
            $this->createMock(\App\Infrastructure\Middleware\AuthMiddleware::class),
            $this->createMock(\App\Domain\UseCases\Games\EditUserGameUseCase::class),
            $this->createMock(\App\Domain\Repository\Game\GameTagRepositoryInterface::class),
            $this->createMock(\App\Domain\Repository\Game\GameNoteRepositoryInterface::class),
            $this->createMock(\App\Domain\UseCases\Games\AddGameNoteUseCase::class),
            $igdb,
            $this->createMock(\App\Domain\UseCases\Games\GetTrendingGamesUseCase::class)
        );
    }

    private function videoController(array $sobre): VideoController
    {
        $youtube = $this->createMock(YouTubeService::class);
        $youtube->method('searchVideosResilient')->willReturn($sobre);

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
    public function igdb_search_carries_the_stale_flag_and_its_date(): void
    {
        $cuando = 1754216527; // 2025-08-03T11:42:07+00:00

        $r = $this->gameController([
            'data'      => [['id' => 1022, 'name' => 'The Legend of Zelda']],
            'stale'     => true,
            'cached_at' => $cuando,
        ])->searchIGDBGames(['query' => 'zelda']);

        $this->assertSame('success', $r['status']);
        $this->assertTrue($r['data']['stale']);
        $this->assertSame(date('c', $cuando), $r['data']['cached_at']);
        $this->assertCount(1, $r['data']['games'], 'La degradación sirve datos, no una lista vacía');
    }

    #[Test]
    public function youtube_search_carries_the_stale_flag_and_its_date(): void
    {
        $cuando = 1754216527;

        $r = $this->videoController([
            'data'      => [['id' => 'dQw4w9WgXcQ', 'title' => 'Un vídeo']],
            'stale'     => true,
            'cached_at' => $cuando,
        ])->searchVideos(['q' => 'un video']);

        $this->assertSame('success', $r['status']);
        $this->assertTrue($r['data']['stale']);
        $this->assertSame(date('c', $cuando), $r['data']['cached_at']);
        $this->assertCount(1, $r['data']['videos']);
    }

    #[Test]
    public function a_stale_answer_without_a_timestamp_sends_null_and_not_a_broken_date(): void
    {
        // date('c', null) daría la fecha de HOY, que es la peor respuesta
        // posible: el aviso mentiría diciendo que la copia es de hace un rato.
        $juegos = $this->gameController([
            'data' => [], 'stale' => true, 'cached_at' => null,
        ])->searchIGDBGames(['query' => 'zelda']);

        $videos = $this->videoController([
            'data' => [], 'stale' => true, 'cached_at' => null,
        ])->searchVideos(['q' => 'un video']);

        $this->assertTrue($juegos['data']['stale']);
        $this->assertNull($juegos['data']['cached_at']);
        $this->assertTrue($videos['data']['stale']);
        $this->assertNull($videos['data']['cached_at']);
    }

    #[Test]
    public function a_fresh_answer_says_so_explicitly_instead_of_omitting_the_key(): void
    {
        // El plan lo fija: `stale` es siempre booleano, nunca ausente. Un campo
        // que a veces no está obliga al frontend a distinguir «fresco» de «no lo
        // sé», y no hay diferencia útil entre las dos.
        $juegos = $this->gameController([
            'data' => [], 'stale' => false, 'cached_at' => 1754216527,
        ])->searchIGDBGames(['query' => 'zelda']);

        $videos = $this->videoController([
            'data' => [], 'stale' => false, 'cached_at' => 1754216527,
        ])->searchVideos(['q' => 'un video']);

        $this->assertArrayHasKey('stale', $juegos['data']);
        $this->assertFalse($juegos['data']['stale']);
        $this->assertArrayHasKey('stale', $videos['data']);
        $this->assertFalse($videos['data']['stale']);
    }
}
