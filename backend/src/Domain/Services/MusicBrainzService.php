<?php

declare(strict_types=1);

namespace App\Domain\Services;

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
 */
class MusicBrainzService
{
    private const BASE_URL = 'https://musicbrainz.org/ws/2';

    /** Holgado a propósito: la mediana ronda los 8 s y las colas largas son normales */
    private const TIMEOUT = 60;

    /**
     * MusicBrainz pide aplicación, versión y forma de contacto
     *
     * Si el proyecto se despliega en otro sitio, esto es lo que hay que cambiar
     * para que el administrador de MusicBrainz sepa a quién escribir.
     */
    private const USER_AGENT = 'LibraryVue/1.0 ( https://library.dcahomelab.com )';

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
     * GET con el User-Agent puesto y sin dejar que un fallo tumbe al llamante
     */
    private function get(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => "User-Agent: " . self::USER_AGENT . "\r\nAccept: application/json\r\n",
                'timeout'       => self::TIMEOUT,
                // Un 503 trae cuerpo con el motivo, y ese cuerpo es justo lo que
                // hace falta para distinguirlo de un álbum sin pistas.
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            $this->logger->warning('musicbrainz: la llamada falló', ['url' => $url]);

            return null;
        }

        return $body;
    }
}
