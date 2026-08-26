<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\DTO\Queries\GetLastFmStatsQuery;
use App\Domain\Model\User;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\LastFmService;
use App\Domain\UseCases\Albums\GetListeningStatsUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * El `Hecho cuando` del M2: `get_listening_stats` dice de cuándo son sus datos.
 *
 * **Es UNA llamada por petición, no diez**, y eso es la enmienda del M2 del
 * 2026-08-26: el `statsType` lo elige el selector de `ListeningStats.vue:218-231`
 * y `useListeningStats.js:44-48` manda uno cada vez, así que el `match` del use
 * case ejecuta exactamente una rama. No hay peor caso que agregar: la frescura
 * de la respuesta es la de esa llamada.
 */
class GetListeningStatsFreshnessTest extends TestCase
{
    private function useCase(LastFmService $lastFm): GetListeningStatsUseCase
    {
        $usuario = $this->createMock(User::class);
        $usuario->method('getLastFmUsername')->willReturn('dcahomelab');

        $usuarios = $this->createMock(UserRepositoryInterface::class);
        $usuarios->method('findById')->willReturn($usuario);

        return new GetListeningStatsUseCase($usuarios, $lastFm, new NullLogger());
    }

    private function lastFm(string $metodo, array $sobre): LastFmService
    {
        $doble = $this->createMock(LastFmService::class);
        $doble->method($metodo)->willReturn($sobre);

        return $doble;
    }

    #[Test]
    public function a_stale_answer_reaches_the_dashboard_with_its_date(): void
    {
        $cuando = 1754216527; // 2025-08-03T11:42:07+00:00

        $lastFm = $this->lastFm('getWeeklyAlbumChartResilient', [
            'data'      => ['weeklyalbumchart' => ['album' => []]],
            'stale'     => true,
            'cached_at' => $cuando,
        ]);

        $r = $this->useCase($lastFm)->execute(
            new GetLastFmStatsQuery(userId: 1, statsType: 'weekly_album_chart')
        );

        $this->assertTrue($r['stale']);
        $this->assertSame(date('c', $cuando), $r['cached_at']);
    }

    #[Test]
    public function a_fresh_answer_says_so_instead_of_omitting_the_key(): void
    {
        $lastFm = $this->lastFm('getWeeklyArtistChartResilient', [
            'data'      => ['weeklyartistchart' => ['artist' => []]],
            'stale'     => false,
            'cached_at' => 1754216527,
        ]);

        $r = $this->useCase($lastFm)->execute(
            new GetLastFmStatsQuery(userId: 1, statsType: 'weekly_artist_chart')
        );

        $this->assertArrayHasKey('stale', $r);
        $this->assertFalse($r['stale']);
        $this->assertIsString($r['cached_at']);
    }

    #[Test]
    public function a_stale_answer_without_a_timestamp_sends_null_and_not_todays_date(): void
    {
        // date('c', null) daría hoy, que es peor que no decir nada: el aviso
        // afirmaría que la copia acaba de bajarse.
        $lastFm = $this->lastFm('getWeeklyAlbumChartResilient', [
            'data' => [], 'stale' => true, 'cached_at' => null,
        ]);

        $r = $this->useCase($lastFm)->execute(
            new GetLastFmStatsQuery(userId: 1, statsType: 'weekly_album_chart')
        );

        $this->assertTrue($r['stale']);
        $this->assertNull($r['cached_at']);
    }

    #[Test]
    public function album_info_carries_the_flags_too(): void
    {
        // La rama de un solo álbum vive fuera del `match` (`:81`) y es la que
        // alimenta `AlbumLastFmCard.vue`. Se le olvida con facilidad.
        $cuando = 1754216527;

        $doble = $this->createMock(LastFmService::class);
        $doble->method('getAlbumInfoResilient')->willReturn([
            'data' => ['album' => ['name' => 'Kind of Blue']], 'stale' => true, 'cached_at' => $cuando,
        ]);
        $doble->method('parseAlbumInfo')->willReturn(['name' => 'Kind of Blue']);

        $r = $this->useCase($doble)->execute(new GetLastFmStatsQuery(
            userId: 1, statsType: 'album_info', artist: 'Miles Davis', album: 'Kind of Blue'
        ));

        $this->assertTrue($r['stale']);
        $this->assertSame(date('c', $cuando), $r['cached_at']);
        $this->assertSame(['name' => 'Kind of Blue'], $r['data']);
    }

    #[Test]
    public function an_album_missing_from_lastfm_is_not_reported_as_stale(): void
    {
        // Un álbum que Last.fm no tiene es una RESPUESTA, no una degradación:
        // marcarlo rancio pintaría un aviso de proveedor caído sobre un 404.
        $doble = $this->createMock(LastFmService::class);
        $doble->method('getAlbumInfoResilient')
              ->willThrowException(new \RuntimeException('Album not found'));

        $r = $this->useCase($doble)->execute(new GetLastFmStatsQuery(
            userId: 1, statsType: 'album_info', artist: 'Nadie', album: 'Nada'
        ));

        $this->assertNull($r['data']);
        $this->assertFalse($r['stale']);
        $this->assertNull($r['cached_at']);
    }
}
