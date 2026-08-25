<?php

declare(strict_types=1);

namespace App\Infrastructure\Covers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PDO;
use Psr\Log\LoggerInterface;
use App\Domain\Services\TmdbService;
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

    /** La misma que emite MySqlAlbumCatalog:29. Aquí solo se sigue su 307. */
    private const CAA_URL = 'https://coverartarchive.org/release-group/%s/front-500';

    private Client $client;

    public function __construct(
        private readonly PDO $mirror,
        private readonly LoggerInterface $logger,
        private readonly string $basePath = '/var/www/html/storage/covers',
        ?Client $client = null,
        ?HttpClientFactory $http = null,
        private readonly ?TmdbService $tmdb = null
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
    /**
     * Registra una portada de **catálogo** —algo que no está en la biblioteca—
     * y devuelve su `source_url`, o null si no hay nada que registrar.
     *
     * Es lo que convierte una búsqueda en portadas locales. Y es también la
     * única parte de esta clase que sale a la red **dentro** de una petición,
     * así que lleva dos guardas que no son opcionales:
     *
     *  1. **La clave tiene que existir en el mirror.** Sin esto, cualquiera que
     *     pida `?cover=movie/tt9999999` en bucle dispara una llamada a TMDB por
     *     petición: un vector de gasto de cuota ajena servido en bandeja. La
     *     comprobación es un `SELECT` de 1-4 ms contra la clave primaria y
     *     convierte un id inventado en un 404 barato.
     *  2. **Si ya hay fila, no se resuelve nada.** Se devuelve su `source_url`
     *     tal cual: resolver otra vez sería pagar la red por un dato que ya está.
     *
     * Solo `movie` y `album` tienen resolución: son los dos medios cuya portada
     * se deduce de una clave del mirror. Libros, juegos y vídeos se buscan
     * contra APIs sin dump y su URL no es deducible sin llamarlas antes.
     *
     * @return string|null la URL de origen, o null si la clave no existe en el
     *                     mirror o el medio no se puede resolver (→ 404)
     */
    public function resolveCatalog(string $mediaType, string $entityKey): ?string
    {
        if ($entityKey === '') {
            return null;
        }

        // Guarda 2: si ya está registrada, no se toca la red.
        $existente = $this->find($mediaType, $entityKey);
        if ($existente !== null) {
            return $existente['source_url'];
        }

        // Guarda 1: la clave tiene que existir en el mirror ANTES de salir.
        $url = match ($mediaType) {
            'movie'  => $this->resolveMoviePoster($entityKey),
            'album'  => $this->resolveAlbumCover($entityKey),
            default  => null,
        };

        if ($url === null) {
            return null;
        }

        try {
            $stmt = $this->mirror->prepare(
                "INSERT INTO cover_file (media_type, entity_key, source_url, scope)
                 VALUES (:media_type, :entity_key, :source_url, 'catalog')
                 ON DUPLICATE KEY UPDATE source_url = VALUES(source_url)"
            );
            $stmt->execute([
                'media_type' => $mediaType,
                'entity_key' => $entityKey,
                'source_url' => $url,
            ]);
        } catch (Throwable $e) {
            // Si no se puede registrar, todavía se puede redirigir al origen:
            // se devuelve la URL igual y el usuario ve su portada.
            $this->logger->warning('CoverStore: no se pudo registrar la portada de catálogo', [
                'media_type' => $mediaType,
                'entity_key' => $entityKey,
                'error'      => $e->getMessage(),
            ]);
        }

        return $url;
    }

    /**
     * El póster de una película, y de paso la ficha completa en `tmdb_title`.
     *
     * **Son dos llamadas a TMDB a propósito, no una.** `findByImdbId` solo trae
     * `tmdb_id`, `media_type`, `title`, `overview` y `poster_path`; `director` y
     * `total_seasons` salen de `details()`. Escribir la fila a medias no sería
     * neutro sino una regresión: `TmdbMovieCatalog::readCached()` devuelve lo que
     * encuentre en cuanto está dentro de su ventana de 5 meses, **sin comprobar
     * si los campos están rellenos**, así que un póster mirado en una búsqueda
     * dejaría la ficha de esa película sin sinopsis y sin director durante cinco
     * meses. Medido: ~0,45 s las dos, una sola vez por película, y solo en las
     * que de verdad se ven (el `loading="lazy"` de los carruseles).
     */
    private function resolveMoviePoster(string $tconst): ?string
    {
        // ¿Está ya cacheada la ficha? Entonces ni TMDB ni nada.
        try {
            $stmt = $this->mirror->prepare(
                'SELECT poster_path FROM tmdb_title WHERE tconst = :tconst'
            );
            $stmt->execute(['tconst' => $tconst]);
            $cacheado = $stmt->fetchColumn();

            if (is_string($cacheado) && $cacheado !== '') {
                return TmdbService::IMAGE_BASE . $cacheado;
            }

            // La guarda anti-cuota: el tconst tiene que existir en el mirror.
            $stmt = $this->mirror->prepare(
                'SELECT 1 FROM imdb_title WHERE tconst = :tconst'
            );
            $stmt->execute(['tconst' => $tconst]);

            if ($stmt->fetchColumn() === false) {
                return null;
            }
        } catch (Throwable $e) {
            $this->logger->error('CoverStore: fallo consultando el mirror por el póster', [
                'tconst' => $tconst,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }

        if ($this->tmdb === null) {
            return null;
        }

        $hit = $this->tmdb->findByImdbId($tconst);
        if ($hit === null || ($hit['poster_path'] ?? null) === null) {
            return null;
        }

        $this->storeTmdbTitle($tconst, $hit);

        return TmdbService::IMAGE_BASE . $hit['poster_path'];
    }

    /**
     * Escribe la ficha COMPLETA en `tmdb_title`, con la segunda llamada incluida.
     *
     * Mismo upsert que `TmdbMovieCatalog::store()`, a propósito: son la misma
     * tabla y el mismo contrato. `poster_path` se guarda **relativo**, que es lo
     * que esa columna declara; el host lo pone quien la lee.
     */
    private function storeTmdbTitle(string $tconst, array $hit): void
    {
        try {
            $detalles = $this->tmdb->details($hit['tmdb_id'], $hit['media_type']);
            $esTv     = $hit['media_type'] === 'tv';

            $stmt = $this->mirror->prepare(
                'INSERT INTO tmdb_title
                     (tconst, tmdb_id, media_type, title_es, overview_es, poster_path,
                      director, total_seasons, cached_at)
                 VALUES (:tconst, :tmdb_id, :media_type, :title_es, :overview_es, :poster_path,
                         :director, :total_seasons, NOW())
                 ON DUPLICATE KEY UPDATE
                     tmdb_id       = VALUES(tmdb_id),
                     media_type    = VALUES(media_type),
                     title_es      = VALUES(title_es),
                     overview_es   = VALUES(overview_es),
                     poster_path   = VALUES(poster_path),
                     director      = VALUES(director),
                     total_seasons = VALUES(total_seasons),
                     cached_at     = NOW()'
            );
            $stmt->execute([
                'tconst'        => $tconst,
                'tmdb_id'       => $hit['tmdb_id'],
                'media_type'    => $hit['media_type'],
                'title_es'      => $hit['title'],
                'overview_es'   => $hit['overview'],
                'poster_path'   => $hit['poster_path'],
                'director'      => $this->directorDe($detalles ?? []),
                'total_seasons' => $esTv && isset($detalles['number_of_seasons'])
                    ? (int) $detalles['number_of_seasons']
                    : null,
            ]);
        } catch (Throwable $e) {
            // Que no se pueda calentar la caché de la ficha no impide servir la
            // portada, que es lo que ha pedido el navegador.
            $this->logger->warning('CoverStore: no se pudo cachear la ficha de TMDB', [
                'tconst' => $tconst,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /** El director sale del crew de `details()`; en una serie no hay. */
    private function directorDe(array $detalles): ?string
    {
        foreach ($detalles['credits']['crew'] ?? [] as $miembro) {
            if (($miembro['job'] ?? null) === 'Director') {
                return $miembro['name'] ?? null;
            }
        }

        return null;
    }

    /**
     * La carátula de un álbum, siguiendo **un solo salto** del Cover Art Archive.
     *
     * La cadena real son dos: `coverartarchive.org` responde 307 a
     * `archive.org/download/mbid-…` (0,255 s medidos) y ese responde 302 a un
     * nodo de almacenamiento concreto (0,536 s más). **Se para en el primero**:
     * el segundo destino es un host que rota —medido: `dn710907.ca.archive.org`—
     * y guardarlo metería una URL efímera en una tabla con caducidad de 60 días.
     * La del primer salto es canónica y estable, cuesta un tercio, y
     * `fetchPending()` ya sigue hasta MAX_REDIRECTS al descargar.
     */
    private function resolveAlbumCover(string $mbid): ?string
    {
        // La guarda anti-cuota, aquí doble: que el álbum exista **y** que
        // MusicBrainz diga que tiene portada. Sin lo segundo, CAA responde 404
        // y se habría pagado el viaje para nada.
        try {
            $stmt = $this->mirror->prepare(
                'SELECT 1 FROM mb_release_group WHERE gid = :gid AND has_cover_art = 1'
            );
            $stmt->execute(['gid' => $mbid]);

            if ($stmt->fetchColumn() === false) {
                return null;
            }
        } catch (Throwable $e) {
            $this->logger->error('CoverStore: fallo consultando el mirror por la carátula', [
                'mbid'  => $mbid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $url = sprintf(self::CAA_URL, $mbid);

        try {
            $respuesta = $this->client->head($url, [
                'allow_redirects' => false,
                'http_errors'     => false,
            ]);
        } catch (Throwable $e) {
            $this->logger->warning('CoverStore: CAA no respondió', [
                'mbid'  => $mbid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $destino = $respuesta->getHeaderLine('Location');

        // Si CAA deja de redirigir algún día, la URL original sigue sirviendo:
        // es la que se usaba antes de este plan.
        return $destino !== '' ? $destino : $url;
    }

    /**
     * ¿Hay otra fila apuntando a este mismo fichero?
     *
     * `relativePathFor()` hashea la URL de origen, así que el fichero es
     * compartido siempre que dos filas tengan la misma `source_url`. Sin esta
     * comprobación, purgar una fila de catálogo puede dejar a una de biblioteca
     * sin su imagen y sin forma de recuperarla.
     */
    private function pathIsShared(string $storagePath, int $exceptId): bool
    {
        try {
            $stmt = $this->mirror->prepare(
                'SELECT 1 FROM cover_file
                  WHERE storage_path = :storage_path AND id <> :id
                  LIMIT 1'
            );
            $stmt->execute(['storage_path' => $storagePath, 'id' => $exceptId]);

            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            // Ante la duda, NO se borra: un fichero huérfano ocupa disco, uno
            // borrado de más deja una portada rota sin forma de recuperarla.
            $this->logger->warning('CoverStore: no se pudo comprobar si el fichero es compartido', [
                'storage_path' => $storagePath,
                'error'        => $e->getMessage(),
            ]);

            return true;
        }
    }

    /**
     * Convierte la fila de catálogo de un ítem en su fila de biblioteca.
     *
     * Cuando alguien guarda un álbum que ya había visto en una búsqueda, su
     * carátula **ya está en disco** bajo la clave del catálogo (el MBID). El
     * guardado la registraría otra vez bajo la clave de biblioteca (el id
     * interno de MySQL) y la bajaría de nuevo: dos filas y dos copias del mismo
     * JPEG. Reetiquetar la que hay sale gratis.
     *
     * @param string $catalogKey la clave con la que se registró en la búsqueda
     * @param string $libraryKey la clave con la que la pedirá la biblioteca
     * @return bool si se reetiquetó algo
     */
    public function promoteToLibrary(string $mediaType, string $catalogKey, string $libraryKey): bool
    {
        if ($catalogKey === '' || $libraryKey === '' || $catalogKey === $libraryKey) {
            return false;
        }

        try {
            $stmt = $this->mirror->prepare(
                "UPDATE cover_file
                    SET scope = 'library', entity_key = :library_key
                  WHERE media_type = :media_type
                    AND entity_key = :catalog_key
                    AND scope = 'catalog'"
            );
            $stmt->execute([
                'media_type'  => $mediaType,
                'catalog_key' => $catalogKey,
                'library_key' => $libraryKey,
            ]);

            if ($stmt->rowCount() > 0) {
                $this->logger->info('CoverStore: portada de catálogo reetiquetada a biblioteca', [
                    'media_type'  => $mediaType,
                    'catalog_key' => $catalogKey,
                    'library_key' => $libraryKey,
                ]);

                return true;
            }
        } catch (Throwable $e) {
            // El caso esperado es el choque con `UNIQUE (media_type, entity_key)`:
            // ya existe una fila de biblioteca con esa clave. No es un fallo —
            // significa que no hay nada que promover— y desde luego no puede
            // tumbar un guardado.
            $this->logger->info('CoverStore: no se pudo reetiquetar la portada de catálogo', [
                'media_type'  => $mediaType,
                'catalog_key' => $catalogKey,
                'library_key' => $libraryKey,
                'motivo'      => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Baja **esta** portada concreta, ahora. Devuelve si lo consiguió.
     *
     * `fetchPending()` no vale para esto: ordena por `attempts ASC, id ASC` y se
     * llevaría la más antigua de la cola, no la que se acaba de registrar. Quien
     * acaba de pedir una portada de catálogo quiere que la **suya** esté en
     * disco para la siguiente visita, no la de otro.
     */
    public function fetchOneNow(string $mediaType, string $entityKey): bool
    {
        try {
            $stmt = $this->mirror->prepare(
                'SELECT id, source_url
                   FROM cover_file
                  WHERE media_type = :media_type
                    AND entity_key = :entity_key
                    AND storage_path IS NULL
                    AND attempts < :max_attempts'
            );
            $stmt->execute([
                'media_type'   => $mediaType,
                'entity_key'   => $entityKey,
                'max_attempts' => self::MAX_ATTEMPTS,
            ]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error('CoverStore: no se pudo leer la portada a bajar', [
                'media_type' => $mediaType,
                'entity_key' => $entityKey,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }

        if ($fila === false) {
            return false;
        }

        return $this->fetchOne((int) $fila['id'], $fila['source_url']);
    }

    /**
     * Marca la portada como usada, como mucho una vez al día.
     *
     * `last_access` es lo que decide qué purga el catálogo, así que hay que
     * escribirlo — pero una biblioteca con 40 ítems son 40 imágenes por render,
     * y un UPDATE por imagen servida sería absurdo para un dato cuya
     * granularidad útil son los días. De ahí el `last_access < CURDATE()`: la
     * primera petición del día escribe, las demás no tocan nada.
     */
    public function touch(string $mediaType, string $entityKey): void
    {
        try {
            $stmt = $this->mirror->prepare(
                'UPDATE cover_file
                    SET last_access = NOW()
                  WHERE media_type = :media_type
                    AND entity_key = :entity_key
                    AND (last_access IS NULL OR last_access < CURDATE())'
            );
            $stmt->execute([
                'media_type' => $mediaType,
                'entity_key' => $entityKey,
            ]);
        } catch (Throwable $e) {
            // Anotar el acceso jamás puede impedir que se sirva la imagen.
            $this->logger->warning('CoverStore: no se pudo anotar el acceso', [
                'media_type' => $mediaType,
                'entity_key' => $entityKey,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Borra portadas de catálogo caducadas. Devuelve cuántas borró.
     *
     * **Nunca toca `scope = 'library'`**, y esa es la razón de ser de la
     * columna: la biblioteca del usuario tiene su portada garantizada en disco
     * y no está acotada por tiempo, mientras que el catálogo son 2 M de
     * portadas potenciales que no pueden crecer sin techo.
     *
     * Dos poblaciones distintas, y conviene no confundirlas:
     *
     *   1. **Bajadas y sin usar** desde hace `$days`. Se van con su fichero.
     *   2. **Quemadas**: sin `storage_path` y con los intentos agotados. Se van
     *      también, y por un motivo que no es el espacio — mientras la fila
     *      exista, `fetchPending()` la ignora (`attempts >= MAX_ATTEMPTS`) y
     *      `cover.php` responde 302 al origen para siempre. Borrarla deja que
     *      la siguiente petición la registre limpia y vuelva a intentarlo.
     *
     * Lo que **no** se toca es lo que está en vuelo: registrado hace un momento,
     * sin fichero todavía y con intentos por gastar. Eso es trabajo pendiente
     * de `covers:backfill`, no basura. Por eso no vale un COALESCE con fecha
     * cero: dejaría purgable todo lo recién registrado.
     */
    public function purgeCatalog(int $days = 60): int
    {
        if ($days < 1) {
            return 0;
        }

        try {
            $stmt = $this->mirror->prepare(
                "SELECT id, storage_path
                   FROM cover_file
                  WHERE scope = 'catalog'
                    AND (
                          (storage_path IS NOT NULL
                             AND COALESCE(last_access, fetched_at) < NOW() - INTERVAL :days DAY)
                       OR (storage_path IS NULL AND attempts >= :max_attempts)
                        )"
            );
            $stmt->execute([
                'days'         => $days,
                'max_attempts' => self::MAX_ATTEMPTS,
            ]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error('CoverStore: no se pudo listar el catálogo caducado', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        $borradas = 0;

        foreach ($filas as $fila) {
            // El fichero primero: si el DELETE falla, la fila sigue apuntando a
            // algo que ya no está y `localPath()` cae a 302, que es degradar.
            // Al revés dejaría el fichero huérfano en el volumen para siempre.
            //
            // Pero SOLO si no lo comparte nadie. `relativePathFor()` hashea la
            // **URL de origen**, así que dos filas con la misma `source_url`
            // apuntan al MISMO fichero — el caso típico: un álbum visto en una
            // búsqueda (catálogo) y luego guardado (biblioteca). Borrarlo aquí
            // dejaría a la fila de biblioteca con `storage_path` apuntando a un
            // hueco, y eso **no falla de forma ruidosa**: `localPath()` haría
            // `is_file()` → null → 302 al CDN, y como `fetchPending()` filtra
            // por `storage_path IS NULL`, nadie la volvería a bajar **nunca**.
            if ($fila['storage_path'] !== null && !$this->pathIsShared($fila['storage_path'], (int) $fila['id'])) {
                $ruta = $this->basePath . '/' . $fila['storage_path'];
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }

            try {
                $del = $this->mirror->prepare('DELETE FROM cover_file WHERE id = :id');
                $del->execute(['id' => $fila['id']]);
                $borradas++;
            } catch (Throwable $e) {
                $this->logger->warning('CoverStore: no se pudo borrar la fila caducada', [
                    'id'    => $fila['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($borradas > 0) {
            $this->logger->info('CoverStore: catálogo purgado', [
                'borradas' => $borradas,
                'dias'     => $days,
            ]);
        }

        return $borradas;
    }

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
