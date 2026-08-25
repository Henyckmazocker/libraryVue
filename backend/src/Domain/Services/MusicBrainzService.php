<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Infrastructure\Http\HttpClientFactory;
use App\Infrastructure\Http\RateGate;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Cliente de la API web de MusicBrainz (ws/2)
 *
 * Solo habla HTTP y devuelve el JSON decodificado: quién lo llama y qué hace
 * con la respuesta es cosa de [[AlbumTrackService]]. Existe aparte para que el
 * mapeo se pueda testear sin red y para que las tres trampas de esta API vivan
 * en un sitio:
 *
 *   1. **El User-Agent es obligatorio.** MusicBrainz degrada o bloquea a los
 *      clientes que no se identifican con aplicación y forma de contacto. No es
 *      cortesía, es su política de uso.
 *   2. **Los errores vienen DENTRO del JSON.** Bajo carga responde un cuerpo con
 *      clave `error` ("The MusicBrainz web server is currently…"). Parseado sin
 *      mirar, eso da una lista de medios vacía — es decir, un álbum "sin
 *      pistas". Medido el 2026-08-23: así apareció The Dark Side of the Moon
 *      con 0 pistas cuando en realidad tiene 10. Confundir eso con un álbum
 *      genuinamente vacío envenena la caché de forma permanente.
 *   3. **Es lenta y muy variable.** Medido sobre la misma consulta: 4,2 s,
 *      6,8 s, 19,2 s, 23,9 s y 45,2 s. De ahí el timeout generoso, y de ahí que
 *      nadie deba llamar a esto dentro de la petición de un usuario.
 *
 * Y una cuarta que hasta 2026-08-25 no se respetaba: **su política de uso es de
 * 1 petición por segundo**. Con una llamada por petición diferida el proyecto se
 * salvaba de milagro, pero `bin/mirror tracks:backfill` pide en bucle y ahí no
 * había nada que lo frenara. De ahí el [[RateGate]], que cruza procesos porque
 * cada petición de Apache es uno distinto.
 */
class MusicBrainzService
{
    private const BASE_URL = 'https://musicbrainz.org/ws/2';

    /** Holgado a propósito: la mediana ronda los 8 s y las colas largas son normales */
    private const TIMEOUT = 60;

    /** Su política de uso: una petición por segundo, ni una más. */
    private const MIN_INTERVAL = 1.0;

    /**
     * MusicBrainz pide aplicación, versión y forma de contacto
     *
     * Si el proyecto se despliega en otro sitio, esto es lo que hay que cambiar
     * para que el administrador de MusicBrainz sepa a quién escribir.
     */
    private const USER_AGENT = 'LibraryVue/1.0 ( https://library.dcahomelab.com )';

    private LoggerInterface $logger;

    private Client $client;

    private RateGate $gate;

    /**
     * @param Client|null   $client costura de test, igual que en `CoverStore`:
     *                              `HttpClientFactory` es `final` y no se puede
     *                              extender para colarle un `MockHandler`
     * @param RateGate|null $gate   PHP-DI no autowirea opcionales, así que en
     *                              producción llega null y se construye el de
     *                              abajo; los tests le pasan uno con intervalo 0
     */
    public function __construct(
        LoggerInterface $logger,
        HttpClientFactory $http,
        ?Client $client = null,
        ?RateGate $gate = null
    ) {
        $this->logger = $logger;

        // Perfil `batch`: nadie llama aquí dentro de la petición de un usuario
        // —la de las pistas va diferida con PostResponse—, así que se puede
        // insistir de verdad y obedecer un `Retry-After` largo.
        $this->client = $client ?? $http->create(HttpClientFactory::PROFILE_BATCH, self::USER_AGENT, [
            'timeout'         => self::TIMEOUT,
            'connect_timeout' => 10.0,
            // Equivale al `ignore_errors` del stream_context de antes: un 503
            // trae cuerpo con el motivo, y ese cuerpo es justo lo que permite
            // distinguirlo de un álbum sin pistas (trampa nº 2).
            'http_errors'     => false,
            'headers'         => ['Accept' => 'application/json'],
        ]);

        $this->gate = $gate ?? new RateGate('musicbrainz', self::MIN_INTERVAL);
    }

    /**
     * Una release con sus pistas, por MBID
     *
     * Se pide la release y no el release group a propósito: consultar el grupo
     * entero (`/release?release-group=…`) no termina en discos muy reeditados
     * —medido, más de 2 minutos en The Dark Side of the Moon, con 151
     * reediciones—. Por eso el mirror guarda `canonical_release_gid`.
     *
     * @return array<string,mixed>|null El JSON decodificado, o null si la
     *         llamada falló o la API devolvió un error en el cuerpo
     */
    public function releaseWithRecordings(string $releaseGid): ?array
    {
        $url = sprintf('%s/release/%s?inc=recordings&fmt=json', self::BASE_URL, urlencode($releaseGid));

        $body = $this->get($url);
        if ($body === null) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->logger->warning('musicbrainz: respuesta ilegible', ['release' => $releaseGid]);

            return null;
        }

        // La trampa nº 2. Un cuerpo con `error` es un fallo del servidor, no un
        // álbum sin pistas, y tiene que salir por el mismo sitio que un timeout.
        if (isset($data['error'])) {
            $this->logger->warning('musicbrainz: error en el cuerpo', [
                'release' => $releaseGid,
                'error'   => mb_substr((string) $data['error'], 0, 120),
            ]);

            return null;
        }

        return $data;
    }

    /**
     * GET con la puerta de cadencia delante y sin dejar que un fallo tumbe al llamante
     *
     * La espera va **antes** de la llamada y no después: lo que la puerta
     * reserva es el turno de salida, así que dormir primero es lo que garantiza
     * que dos procesos no llamen dentro del mismo segundo.
     */
    private function get(string $url): ?string
    {
        $espera = $this->gate->wait();

        if ($espera > 0.0) {
            $this->logger->info('musicbrainz: esperando su turno', ['espera' => round($espera, 3)]);
        }

        try {
            $response = $this->client->get($url);
        } catch (\Throwable $e) {
            $this->logger->warning('musicbrainz: la llamada falló', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return (string) $response->getBody();
    }
}
