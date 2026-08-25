<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Services\MusicBrainzService;
use App\Infrastructure\Http\HttpClientFactory;
use App\Infrastructure\Http\RateGate;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * El servicio no tenía tests: con `file_get_contents` no había forma de
 * mockearlo sin tocar la red. Al pasar a Guzzle sí la hay, y lo primero que
 * conviene fijar es justo lo que el plan dice que **no** puede romperse — que
 * un cuerpo con clave `error` siga contando como fallo y no como álbum sin
 * pistas, que es lo que envenenaría la caché de `mb_track` para siempre.
 */
class MusicBrainzServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mbtest_' . uniqid('', true);
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    /**
     * El cliente se construye con la MISMA factoría que en producción y en el
     * mismo perfil: lo único de pega es el transporte. Así el test ejerce el
     * reintento de verdad, no una imitación.
     *
     * @param list<Response|\Throwable> $respuestas
     */
    private function service(array $respuestas, float $intervalo = 0.0, ?MockHandler &$mock = null): MusicBrainzService
    {
        $mock = new MockHandler($respuestas);

        $client = (new HttpClientFactory(new NullLogger()))->create(
            HttpClientFactory::PROFILE_BATCH,
            'LibraryVue/1.0 ( https://library.dcahomelab.com )',
            [
                'handler'     => HandlerStack::create($mock),
                'http_errors' => false,
            ]
        );

        // Intervalo 0 salvo que el test mida la puerta: si no, cada llamada
        // costaría un segundo de reloj.
        return new MusicBrainzService(
            new NullLogger(),
            new HttpClientFactory(new NullLogger()),
            $client,
            new RateGate('musicbrainz-test', $intervalo, $this->dir)
        );
    }

    #[Test]
    public function a_release_with_recordings_comes_back_decoded(): void
    {
        $service = $this->service([
            new Response(200, [], json_encode([
                'id'    => 'abc',
                'media' => [['tracks' => [['position' => 1, 'number' => 'A1']]]],
            ])),
        ]);

        $data = $service->releaseWithRecordings('abc');

        $this->assertIsArray($data);
        $this->assertSame('abc', $data['id']);
    }

    #[Test]
    public function an_error_inside_the_json_body_counts_as_a_failure(): void
    {
        // La trampa nº 2 de la cabecera del servicio: bajo carga responde 200
        // (o 503) con un cuerpo `{"error": "..."}`. Leído sin mirar, eso da una
        // lista de medios vacía, es decir «álbum sin pistas», y como `mb_track`
        // no caduca envenenaría la caché de forma permanente.
        $service = $this->service([
            new Response(200, [], json_encode(['error' => 'The MusicBrainz web server is currently busy'])),
        ]);

        $this->assertNull($service->releaseWithRecordings('abc'));
    }

    #[Test]
    public function an_unreadable_body_counts_as_a_failure(): void
    {
        $service = $this->service([new Response(200, [], 'esto no es JSON')]);

        $this->assertNull($service->releaseWithRecordings('abc'));
    }

    #[Test]
    public function a_503_is_retried_and_the_second_answer_is_used(): void
    {
        // El perfil `batch` insiste: aquí está lo que este plan añade. Antes,
        // un 503 era el final del camino.
        $service = $this->service([
            new Response(503, ['Retry-After' => '0'], '{"error":"busy"}'),
            new Response(200, [], json_encode(['id' => 'abc'])),
        ]);

        $data = $service->releaseWithRecordings('abc');

        $this->assertIsArray($data);
        $this->assertSame('abc', $data['id']);
    }

    #[Test]
    public function the_mandatory_user_agent_survived_the_move_to_guzzle(): void
    {
        // Es el header que se pierde al migrar de `file_get_contents` a Guzzle
        // si nadie mira, y MusicBrainz bloquea a quien no se identifica. Aquí
        // se deja que el servicio construya su cliente de verdad —sin costura—
        // para que lo que se lea sea el User-Agent que él elige.
        $mock = new MockHandler([new Response(200, [], '{}')]);

        $service = new MusicBrainzService(
            new NullLogger(),
            new HttpClientFactory(new NullLogger()),
            null,
            new RateGate('musicbrainz-test', 0.0, $this->dir)
        );

        // Se le cambia el transporte por reflexión, dejando intacto todo lo que
        // el servicio decidió al construirlo (perfil, cabeceras, timeouts).
        $rp = new \ReflectionProperty($service, 'client');
        $rp->setAccessible(true);
        $original = $rp->getValue($service);
        $rc = new \ReflectionProperty($original, 'config');
        $rc->setAccessible(true);
        $cfg = $rc->getValue($original);
        $cfg['handler'] = HandlerStack::create($mock);
        $rp->setValue($service, new \GuzzleHttp\Client($cfg));

        $service->releaseWithRecordings('abc');

        $this->assertStringContainsString(
            'LibraryVue',
            $mock->getLastRequest()->getHeaderLine('User-Agent')
        );
        $this->assertStringContainsString(
            'library.dcahomelab.com',
            $mock->getLastRequest()->getHeaderLine('User-Agent'),
            'MusicBrainz pide una forma de contacto, no solo el nombre'
        );
    }

    #[Test]
    public function two_calls_in_a_row_are_separated_by_the_gate(): void
    {
        // La única forma de comprobar el throttle: la API no dice si te has
        // pasado hasta que te bloquea. Intervalo corto para no gastar reloj.
        $service = $this->service([
            new Response(200, [], '{"id":"a"}'),
            new Response(200, [], '{"id":"b"}'),
        ], 0.4);

        $service->releaseWithRecordings('a');

        $inicio = microtime(true);
        $service->releaseWithRecordings('b');

        $this->assertGreaterThan(
            0.3,
            microtime(true) - $inicio,
            'La segunda llamada tenía que esperar su turno'
        );
    }
}
