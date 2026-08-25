<?php

declare(strict_types=1);

namespace App\Infrastructure\Covers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PDO;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Http\HttpClientFactory;
use RuntimeException;
use Throwable;

/**
 * Copia local de las portadas de lo que el usuario guarda en su biblioteca.
 *
 * El mirror de catálogos dejó las búsquedas funcionando sin red, pero las
 * carátulas seguían viniendo de CDN ajenos. Esta clase las baja al disco y
 * lleva el registro de qué hay bajado, de dónde y cuándo, en la tabla
 * cover_file de library_mirror (es caché reconstruible: ahí es donde vive lo
 * regenerable).
 *
 * El reparto es la partición en juego: register() se llama DENTRO del flujo de
 * guardado y solo escribe una fila; fetchPending() es lo que sale a la red, y
 * corre después de haber respondido al cliente o desde `bin/mirror
 * covers:backfill`. Un fallo de descarga nunca puede afectar al resultado de
 * guardar.
 */
class CoverStore
{
    /** Más allá de esto, la URL se da por muerta y el backfill la ignora. */
    public const MAX_ATTEMPTS = 3;

    /** Un póster de TMDB en w500 no llega a 200 KB; 5 MB es techo de seguridad. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const TIMEOUT         = 10.0;
    private const CONNECT_TIMEOUT = 3.0;
    private const MAX_REDIRECTS   = 3;

    private Client $client;

    public function __construct(
        private readonly PDO $mirror,
        private readonly LoggerInterface $logger,
        private readonly string $basePath = '/var/www/html/storage/covers',
        ?Client $client = null,
        ?HttpClientFactory $http = null
    ) {
        // Perfil `batch`: descargar una portada siempre pasa en diferido
        // (`PostResponse`) o dentro de `covers:backfill`, nunca dentro de la
        // petición de un usuario, así que aquí sí se puede insistir de verdad.
        //
        // La factoría llega por `container.php` de forma **explícita**: PHP-DI
        // no autowirea parámetros opcionales —comprobado el 2026-08-25—, así
        // que sin esa línea esto se quedaría en null. El `?Client` de delante se
        // queda porque es la costura que usa CoverStoreTest.
        //
        // Y si no llega ninguno de los dos, **revienta aquí**. Un respaldo a
        // `new Client` sin política sería lo peor posible: portadas
        // descargándose sin reintento y sin que nadie se entere hasta que un
        // proveedor empiece a cortar.
        $this->client = $client ?? $http?->create(
            HttpClientFactory::PROFILE_BATCH,
            'LibraryVue/1.0 (Educational Project)',
            [
                'timeout'         => self::TIMEOUT,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'allow_redirects' => ['max' => self::MAX_REDIRECTS],
            ]
        ) ?? throw new RuntimeException(
            'CoverStore necesita un HttpClientFactory o un Client: revisa el cableado de container.php'
        );
    }

    /**
     * Registra la intención de tener esta portada. NO descarga.
     *
     * Es lo único que se llama desde el flujo de guardado, y por eso es un
     * INSERT ... ON DUPLICATE KEY UPDATE: barato, idempotente y sin red. Si la
     * URL de origen cambia, se actualiza y se reabre la ventana de intentos;
     * si es la misma, la fila no se toca (así un segundo usuario que guarda la
     * misma película no resetea los intentos de una URL rota).
     *
     * El orden de las asignaciones NO es cosmético: MySQL las evalúa de
     * izquierda a derecha y las posteriores ven el valor ya actualizado. Con
     * source_url el primero, las tres comparaciones de debajo lo compararían
     * consigo mismo y una URL nueva jamás resetearía storage_path. Va el último.
     */
    public function register(string $mediaType, string $entityKey, string $sourceUrl): void
    {
        if ($mediaType === '' || $entityKey === '' || $sourceUrl === '') {
            return;
        }

        try {
            $stmt = $this->mirror->prepare(
                'INSERT INTO cover_file (media_type, entity_key, source_url)
                 VALUES (:media_type, :entity_key, :source_url)
                 ON DUPLICATE KEY UPDATE
                   storage_path = IF(source_url = VALUES(source_url), storage_path, NULL),
                   attempts     = IF(source_url = VALUES(source_url), attempts, 0),
                   last_error   = IF(source_url = VALUES(source_url), last_error, NULL),
                   source_url   = VALUES(source_url)'
            );
            $stmt->execute([
                'media_type' => $mediaType,
                'entity_key' => $entityKey,
                'source_url' => $sourceUrl,
            ]);
        } catch (Throwable $e) {
            // Registrar una portada nunca puede tumbar un guardado.
            $this->logger->warning('CoverStore: no se pudo registrar la portada', [
                'media_type' => $mediaType,
                'entity_key' => $entityKey,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Descarga las pendientes. Devuelve cuántas bajó.
     *
     * Pendiente = sin storage_path y con menos de MAX_ATTEMPTS intentos. Cada
     * fallo se anota en la propia fila, que es lo que evita reintentar para
     * siempre una URL que da 404.
     *
     * El orden es `attempts ASC`, no solo por id: manda las ya fallidas al final
     * de la cola para que un lote entero de URLs muertas no tape a las que
     * nunca se han intentado.
     */
    public function fetchPending(int $limit = 50): int
    {
        if ($limit < 1) {
            return 0;
        }

        try {
            $stmt = $this->mirror->prepare(
                'SELECT id, media_type, entity_key, source_url
                   FROM cover_file
                  WHERE storage_path IS NULL
                    AND attempts < :max_attempts
                  ORDER BY attempts ASC, id ASC
                  LIMIT ' . $limit
            );
            $stmt->execute(['max_attempts' => self::MAX_ATTEMPTS]);
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error('CoverStore: no se pudieron leer las pendientes', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }

        $fetched = 0;
        foreach ($pending as $row) {
            if ($this->fetchOne((int) $row['id'], $row['source_url'])) {
                $fetched++;
            }
        }

        return $fetched;
    }

    /**
     * Cuántas portadas quedan por bajar.
     *
     * La necesita el backfill para informar de verdad: `fetchPending()` devuelve
     * las que BAJÓ, así que un 0 suyo tanto significa «no había nada» como «lo
     * intenté todo y falló». Son cosas muy distintas para quien mira la salida.
     */
    public function countPending(): int
    {
        try {
            $stmt = $this->mirror->prepare(
                'SELECT COUNT(*)
                   FROM cover_file
                  WHERE storage_path IS NULL
                    AND attempts < :max_attempts'
            );
            $stmt->execute(['max_attempts' => self::MAX_ATTEMPTS]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $this->logger->error('CoverStore: no se pudieron contar las pendientes', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /** Ruta local si existe, null si no. */
    public function localPath(string $mediaType, string $entityKey): ?string
    {
        $row = $this->find($mediaType, $entityKey);
        if ($row === null || $row['storage_path'] === null) {
            return null;
        }

        $path = $this->basePath . '/' . $row['storage_path'];

        return is_file($path) ? $path : null;
    }

    /**
     * La fila de cover_file de esta entidad, o null.
     *
     * La necesita el endpoint de servicio: sin copia local, redirige a
     * source_url, y para eso tiene que leer la fila entera.
     *
     * @return array{storage_path: ?string, source_url: string, mime_type: ?string}|null
     */
    public function find(string $mediaType, string $entityKey): ?array
    {
        try {
            $stmt = $this->mirror->prepare(
                'SELECT storage_path, source_url, mime_type
                   FROM cover_file
                  WHERE media_type = :media_type AND entity_key = :entity_key'
            );
            $stmt->execute([
                'media_type' => $mediaType,
                'entity_key' => $entityKey,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error('CoverStore: no se pudo leer la portada', [
                'media_type' => $mediaType,
                'entity_key' => $entityKey,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }

        return $row === false ? null : $row;
    }

    /**
     * Ruta de reparto a partir de la URL de origen.
     *
     * Los dos primeros caracteres del sha1 hacen de subdirectorio para no
     * dejar 10.000 ficheros en un mismo sitio: 'ab/ab3f9c...e1.jpg'.
     */
    public function relativePathFor(string $sourceUrl, string $mimeType): string
    {
        $hash = sha1($sourceUrl);

        return substr($hash, 0, 2) . '/' . $hash . '.' . self::extensionFor($mimeType);
    }

    /** Extensión de fichero para un Content-Type de imagen. */
    public static function extensionFor(string $mimeType): string
    {
        $type = strtolower(trim(explode(';', $mimeType)[0]));

        return match ($type) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            default      => 'jpg',
        };
    }

    /** Baja una portada y actualiza su fila. true si acabó en disco. */
    private function fetchOne(int $id, string $sourceUrl): bool
    {
        try {
            $response = $this->client->request('GET', $sourceUrl);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('HTTP ' . $status);
            }

            $mimeType = $response->getHeaderLine('Content-Type');
            if (!str_starts_with(strtolower(trim($mimeType)), 'image/')) {
                // Antes de escribir nada al disco: un CDN caído devuelve una
                // página de error con 200 y Content-Type text/html.
                throw new RuntimeException('Content-Type no es imagen: ' . ($mimeType ?: 'vacío'));
            }

            $body = (string) $response->getBody();
            $bytes = strlen($body);
            if ($bytes === 0) {
                throw new RuntimeException('Respuesta vacía');
            }
            if ($bytes > self::MAX_BYTES) {
                throw new RuntimeException('Supera los ' . self::MAX_BYTES . ' bytes: ' . $bytes);
            }

            $relative = $this->relativePathFor($sourceUrl, $mimeType);
            $this->write($relative, $body);

            $stmt = $this->mirror->prepare(
                'UPDATE cover_file
                    SET storage_path = :storage_path,
                        mime_type    = :mime_type,
                        bytes        = :bytes,
                        fetched_at   = NOW(),
                        attempts     = attempts + 1,
                        last_error   = NULL
                  WHERE id = :id'
            );
            $stmt->execute([
                'storage_path' => $relative,
                'mime_type'    => strtolower(trim(explode(';', $mimeType)[0])),
                'bytes'        => $bytes,
                'id'           => $id,
            ]);

            return true;
        } catch (GuzzleException | Throwable $e) {
            $this->recordFailure($id, $e->getMessage());
            return false;
        }
    }

    /** Escribe el fichero creando su subdirectorio de reparto. */
    private function write(string $relativePath, string $body): void
    {
        $full = $this->basePath . '/' . $relativePath;
        $dir  = dirname($full);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio ' . $dir);
        }

        // Escritura atómica: un fichero a medias servido por el endpoint es una
        // imagen rota que además queda cacheada un mes en el navegador.
        $tmp = $full . '.tmp';
        if (file_put_contents($tmp, $body) === false || !rename($tmp, $full)) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo escribir ' . $full);
        }
    }

    private function recordFailure(int $id, string $error): void
    {
        try {
            $stmt = $this->mirror->prepare(
                'UPDATE cover_file
                    SET attempts   = attempts + 1,
                        last_error = :last_error
                  WHERE id = :id'
            );
            $stmt->execute([
                'last_error' => substr($error, 0, 255),
                'id'         => $id,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('CoverStore: no se pudo anotar el fallo', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
