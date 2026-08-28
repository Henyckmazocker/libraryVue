<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Services\YouTubeService;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Cache\ResilientCall;
use App\Infrastructure\Http\HttpClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Lo que sale de `getVideoDetails`, que hasta el 2026-08-27 no lo miraba nadie.
 *
 * El método llevaba escrito desde el principio pero **sin un solo consumidor**,
 * así que su salida nunca se había pintado. Al exponerlo como
 * `get_youtube_video_details` se vio en pantalla lo que YouTube manda de verdad:
 * `duration` en ISO 8601. Se formatea en el servicio y no en la vista porque este
 * campo no solo se pinta — se guarda tal cual en `videos.duration` al dar de alta,
 * y ahí lo leen la ficha, la fila de la biblioteca y el carrusel.
 */
class YouTubeDetailsTest extends TestCase
{
    private function service(array $item): YouTubeService
    {
        // La caché siempre falla: lo que se prueba es la normalización, no el
        // camino corto.
        $cache = $this->createMock(CacheService::class);
        $cache->method('get')->willReturn(null);

        $_ENV['YOUTUBE_API_KEY'] = 'test-key';

        $service = new YouTubeService(
            $cache,
            // Real, no doble: `ResilientCall` es `final`, y de todas formas
            // `getVideoDetails` no pasa por él —solo la búsqueda lo usa—.
            new ResilientCall($cache, new NullLogger()),
            new NullLogger(),
            new HttpClientFactory(new NullLogger())
        );

        // Mismo apaño que `MusicBrainzServiceTest.php:144-151`: se le cambia el
        // transporte por reflexión, dejando intacto todo lo que el servicio
        // decidió al construirlo. `HttpClientFactory` es `final` y no se puede
        // doblar, y el servicio no reenvía overrides.
        $mock = new MockHandler([new Response(200, [], json_encode(['items' => [$item]]))]);
        $rp   = new \ReflectionProperty($service, 'client');
        $rp->setAccessible(true);
        $rc = new \ReflectionProperty($rp->getValue($service), 'config');
        $rc->setAccessible(true);
        $cfg            = $rc->getValue($rp->getValue($service));
        $cfg['handler'] = HandlerStack::create($mock);
        $rp->setValue($service, new Client($cfg));

        return $service;
    }

    private function conDuracion(string $iso): array
    {
        return [
            'id'             => 'abc',
            'snippet'        => ['title' => 'Un vídeo', 'channelTitle' => 'Un canal'],
            'contentDetails' => ['duration' => $iso],
            'statistics'     => ['viewCount' => '10'],
        ];
    }

    #[Test]
    public function duration_is_human_readable_not_iso(): void
    {
        $r = $this->service($this->conDuracion('PT3M34S'))->getVideoDetails('abc');

        $this->assertSame('3:34', $r['duration']);
        // Los segundos siguen siendo el número de siempre: quien ordene o sume
        // no depende del formato.
        $this->assertSame(214, $r['duration_seconds']);
    }

    #[Test]
    public function an_hour_long_video_gains_its_hours_field(): void
    {
        $r = $this->service($this->conDuracion('PT1H2M5S'))->getVideoDetails('abc');

        $this->assertSame('1:02:05', $r['duration']);
        $this->assertSame(3725, $r['duration_seconds']);
    }

    #[Test]
    public function seconds_under_a_minute_keep_their_leading_zero(): void
    {
        $r = $this->service($this->conDuracion('PT42S'))->getVideoDetails('abc');

        $this->assertSame('0:42', $r['duration']);
    }

    #[Test]
    public function a_video_without_duration_says_null_not_zero(): void
    {
        // Un directo en curso no trae `duration`. Un '0:00' ahí sería mentira, y
        // además el `v-if="item.duration"` de la ficha lo pintaría.
        $item = $this->conDuracion('PT0S');
        unset($item['contentDetails']['duration']);

        $r = $this->service($item)->getVideoDetails('abc');

        $this->assertNull($r['duration']);
        $this->assertNull($r['duration_seconds']);
    }
}
