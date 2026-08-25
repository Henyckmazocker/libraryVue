<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Infrastructure\Http\HttpClientFactory;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * La política de reintento, ejercida sin red con el `MockHandler` de Guzzle.
 *
 * Lo que se comprueba aquí es lo que no se puede comprobar contra un proveedor
 * real sin agotarle la cuota: que un 429 se reintente, que un 404 no, que
 * `Retry-After` se entienda en sus dos formatos, y —lo que de verdad importa—
 * que el perfil `web` **abandone** en vez de hacer esperar al usuario.
 */
class HttpClientFactoryTest extends TestCase
{
    private function factory(): HttpClientFactory
    {
        return new HttpClientFactory(new NullLogger());
    }

    /**
     * Cliente cuyo transporte son las respuestas dadas, en ese orden.
     *
     * @param list<Response|\Throwable> $respuestas
     */
    private function client(string $perfil, array $respuestas, ?MockHandler &$mock = null): \GuzzleHttp\Client
    {
        $mock = new MockHandler($respuestas);

        return $this->factory()->create($perfil, 'LibraryVue/test', [
            'handler'     => HandlerStack::create($mock),
            'http_errors' => true,
        ]);
    }

    #[Test]
    public function the_batch_profile_retries_a_429_until_it_gets_through(): void
    {
        $client = $this->client(HttpClientFactory::PROFILE_BATCH, [
            new Response(429, ['Retry-After' => '0']),
            new Response(429, ['Retry-After' => '0']),
            new Response(200, [], '{"ok":true}'),
        ]);

        $response = $client->get('https://example.test/algo');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"ok":true}', (string) $response->getBody());
    }

    #[Test]
    public function the_web_profile_gives_up_after_a_single_retry(): void
    {
        // Tres 429 seguidos: `batch` llegaría al cuarto hueco, `web` no. Con
        // 2 intentos solo consume dos respuestas y relanza el segundo 429.
        $client = $this->client(HttpClientFactory::PROFILE_WEB, [
            new Response(429, ['Retry-After' => '0']),
            new Response(429, ['Retry-After' => '0']),
            new Response(200, [], 'no se llega aquí'),
        ], $mock);

        try {
            $client->get('https://example.test/algo');
            $this->fail('El perfil web tenía que abandonar, no llegar al 200');
        } catch (ClientException $e) {
            $this->assertSame(429, $e->getResponse()->getStatusCode());
        }

        // Sin esto el test pasaría igual si NO se reintentara nunca: lo que
        // demuestra que hubo exactamente un reintento es que se consumieron
        // dos respuestas de las tres y quedó justo el 200 sin tocar.
        $this->assertCount(1, $mock, 'Tenían que consumirse 2 respuestas: intento + reintento');
    }

    #[Test]
    public function batch_retries_a_connection_error(): void
    {
        $client = $this->client(HttpClientFactory::PROFILE_BATCH, [
            new ConnectException('DNS caído', new Request('GET', 'https://example.test/algo')),
            new Response(200, [], 'recuperado'),
        ]);

        $this->assertSame('recuperado', (string) $client->get('https://example.test/algo')->getBody());
    }

    #[Test]
    public function web_does_not_retry_a_connection_error(): void
    {
        // El tope del perfil acota el backoff, **no el timeout**: un proveedor
        // que agota los 5 s en vez de responder costaría 10,3 s si se
        // reintentara, y `runSearchStrategy` hace dos llamadas, con lo que una
        // búsqueda de libros pasaría de ~10 s a ~20 s. Medido el 2026-08-25:
        // una búsqueda real tardó 8,26 s con Google Books lento. En `web`, un
        // timeout es el final del camino y se degrada a caché stale al instante.
        $client = $this->client(HttpClientFactory::PROFILE_WEB, [
            new ConnectException('timeout', new Request('GET', 'https://example.test/algo')),
            new Response(200, [], 'no se llega aquí'),
        ], $mock);

        try {
            $client->get('https://example.test/algo');
            $this->fail('El perfil web no puede reintentar un timeout');
        } catch (ConnectException) {
            // Es lo que se espera: sube al llamante y ResilientCall degrada.
        }

        $this->assertCount(1, $mock, 'Solo podía consumirse el primer intento');
    }

    #[Test]
    public function a_404_is_not_retried_because_it_is_an_answer_not_a_failure(): void
    {
        // Si se reintentara, la segunda respuesta sería el 200 y este test
        // pasaría en verde mintiendo. Por eso la cola lleva un 200 detrás.
        $client = $this->client(HttpClientFactory::PROFILE_BATCH, [
            new Response(404),
            new Response(200, [], 'no se llega aquí'),
        ], $mock);

        try {
            $client->get('https://example.test/algo');
            $this->fail('Un 404 no se reintenta: es una respuesta, no un fallo');
        } catch (ClientException $e) {
            $this->assertSame(404, $e->getResponse()->getStatusCode());
        }

        $this->assertCount(1, $mock, 'Solo podía consumirse la respuesta del 404');
    }

    #[Test]
    public function batch_obeys_a_retry_after_within_its_cap(): void
    {
        $client = $this->client(HttpClientFactory::PROFILE_BATCH, [
            new Response(503, ['Retry-After' => '1']),
            new Response(200, [], 'tras esperar'),
        ]);

        $inicio = microtime(true);
        $response = $client->get('https://example.test/algo');
        $transcurrido = microtime(true) - $inicio;

        $this->assertSame('tras esperar', (string) $response->getBody());
        $this->assertGreaterThanOrEqual(
            0.9,
            $transcurrido,
            'El Retry-After de 1 s tiene que haberse obedecido'
        );
    }

    #[Test]
    public function web_abandons_a_retry_after_longer_than_its_cap(): void
    {
        // Éste es el caso que da nombre al riesgo del plan: obedecer un
        // Retry-After de 60 s dentro de una petición deja al usuario en blanco
        // un minuto para acabar sirviendo la misma caché stale.
        $client = $this->client(HttpClientFactory::PROFILE_WEB, [
            new Response(429, ['Retry-After' => '60']),
            new Response(200, [], 'no se llega aquí'),
        ]);

        $inicio = microtime(true);

        try {
            $client->get('https://example.test/algo');
            $this->fail('Con Retry-After de 60 s el perfil web tiene que abandonar');
        } catch (ClientException $e) {
            $this->assertSame(429, $e->getResponse()->getStatusCode());
        }

        $this->assertLessThan(
            1.0,
            microtime(true) - $inicio,
            'Abandonar significa no dormir: ni un segundo de espera'
        );
    }

    #[Test]
    public function retry_after_is_understood_as_an_http_date_too(): void
    {
        // El fallo clásico es parsear solo el formato en segundos: la fecha se
        // lee como 0, se reintenta al instante y se cobra otro 429. Aquí la
        // fecha está a 30 s vista, dentro del tope de `batch` (60 s) y fuera
        // del de `web` (1 s), así que web abandona.
        $enTreintaSegundos = gmdate('D, d M Y H:i:s \G\M\T', time() + 30);

        $client = $this->client(HttpClientFactory::PROFILE_WEB, [
            new Response(429, ['Retry-After' => $enTreintaSegundos]),
            new Response(200, [], 'no se llega aquí'),
        ]);

        $inicio = microtime(true);

        try {
            $client->get('https://example.test/algo');
            $this->fail('Una fecha HTTP a 30 s vista supera el tope de web');
        } catch (ClientException $e) {
            $this->assertSame(429, $e->getResponse()->getStatusCode());
        }

        $this->assertLessThan(
            1.0,
            microtime(true) - $inicio,
            'La fecha se leyó como 0 y se reintentó al instante: el fallo clásico'
        );
    }

    #[Test]
    public function the_web_profile_never_adds_more_than_a_second(): void
    {
        // El `Hecho cuando` del M1, cronometrado. Sin `Retry-After` el retardo
        // sale del backoff del perfil, que está topado en 1 s.
        $client = $this->client(HttpClientFactory::PROFILE_WEB, [
            new Response(503),
            new Response(503),
        ]);

        $inicio = microtime(true);

        try {
            $client->get('https://example.test/algo');
        } catch (\Throwable) {
            // El resultado da igual: lo que se mide es el reloj.
        }

        $this->assertLessThan(
            1.0,
            microtime(true) - $inicio,
            'El perfil web no puede añadir más de 1 s a una petición de usuario'
        );
    }

    #[Test]
    public function the_mandatory_user_agent_reaches_the_request(): void
    {
        // MusicBrainz rechaza al que no se identifica, y al migrar de
        // `file_get_contents` a Guzzle es justo el header que se pierde.
        $mock = new MockHandler([new Response(200)]);
        $stack = HandlerStack::create($mock);

        $client = $this->factory()->create(
            HttpClientFactory::PROFILE_BATCH,
            'LibraryVue/1.0 ( https://library.dcahomelab.com )',
            ['handler' => $stack, 'headers' => ['Accept' => 'application/json']]
        );

        $client->get('https://example.test/algo');

        $enviada = $mock->getLastRequest();
        $this->assertSame(
            'LibraryVue/1.0 ( https://library.dcahomelab.com )',
            $enviada->getHeaderLine('User-Agent')
        );
        $this->assertSame('application/json', $enviada->getHeaderLine('Accept'));
    }

    #[Test]
    public function overrides_reach_the_transport(): void
    {
        // CoverStore conserva lo suyo: la factoría le da el HandlerStack, no le
        // quita sus topes de tamaño y redirecciones. Se comprueban en las
        // opciones que llegan al transporte, no con `Client::getConfig()`, que
        // Guzzle 7.10 tiene deprecado y quita en la 8.
        $vistas = [];
        $stack = HandlerStack::create(
            function ($request, array $options) use (&$vistas) {
                $vistas = $options;

                return \GuzzleHttp\Promise\Create::promiseFor(new Response(200));
            }
        );

        $client = $this->factory()->create(HttpClientFactory::PROFILE_BATCH, 'LibraryVue/test', [
            'handler'         => $stack,
            'timeout'         => 10.0,
            'connect_timeout' => 3.0,
            'allow_redirects' => ['max' => 3],
        ]);

        $client->get('https://example.test/algo');

        $this->assertSame(10.0, $vistas['timeout']);
        $this->assertSame(3.0, $vistas['connect_timeout']);
        // Guzzle rellena aquí sus defaults (`protocols`, `strict`…); lo que
        // importa es que el tope de quien construye sobreviva a la mezcla.
        $this->assertSame(3, $vistas['allow_redirects']['max']);
    }
}
