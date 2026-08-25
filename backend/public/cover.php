<?php

declare(strict_types=1);

/**
 * Servicio de portadas locales — la ÚNICA excepción al endpoint único.
 *
 * Todo lo demás entra por index.php con un POST y la acción en el body. Una
 * imagen no cabe ahí: un `<img src>` es un GET y necesita un cuerpo binario con
 * su Content-Type, no el {status, message, data, http_code} de BaseController.
 *
 * No pasa por bootstrap.php a propósito, por lo mismo que bin/mirror:
 * Application::bootstrap() arranca sesión y emite `Content-Type: application/json`
 * en el constructor, antes de que nadie pueda decidir nada. Lo que sí se comparte
 * es config/container.php, para que CoverStore reciba el mismo 'pdo.mirror'.
 *
 *   GET /index.php?cover=<media_type>/<entity_key>
 *     → 200 image/*  con Cache-Control: public, max-age=2592000
 *     → 302 a source_url   si aún no hay copia local
 *     → 302 + registro     si no hay fila pero la clave SÍ está en el mirror
 *     → 404                si no hay registro y la clave tampoco está
 *
 * El 302 es lo que hace esto retrocompatible: hasta que el backfill termine, el
 * navegador acaba en la misma URL de siempre y el usuario no nota nada.
 *
 * El tercer caso es la caché del catálogo: un resultado de búsqueda no está
 * guardado y no tiene fila, pero su portada SÍ se deduce de una clave del
 * mirror (un tconst, un MBID). En vez de un 404, se resuelve la URL de origen,
 * se registra con scope='catalog' y se encola la descarga para DESPUÉS de la
 * respuesta. La segunda vez que alguien mire esa portada ya sale del disco.
 * La guarda contra el gasto de cuota ajena vive en CoverStore::resolveCatalog():
 * una clave inventada no llega nunca a la red.
 *
 * Se invoca desde index.php ANTES de requerir bootstrap.php.
 */

use App\Infrastructure\Covers\CoverStore;
use App\Infrastructure\Http\PostResponse;
use App\Infrastructure\Logging\LoggingService;
use Psr\Container\ContainerInterface;

/** Un mes: son imágenes que no cambian nunca. Sin esto, cada render de la
 *  biblioteca serían N peticiones a PHP para servir lo mismo. */
const COVER_MAX_AGE = 2592000;

/** Los cinco medios del proyecto. Cualquier otra cosa es una URL manipulada. */
const COVER_MEDIA_TYPES = ['movie', 'series', 'book', 'album', 'game', 'video'];

/**
 * Sirve la portada pedida y termina el proceso.
 *
 * @param string $cover el valor crudo de ?cover=, '<media_type>/<entity_key>'
 */
function serveCover(string $cover): never
{
    // No hay sesión, ni CSRF, ni JSON: solo una imagen pública.
    header_remove('Content-Type');

    $parts = explode('/', $cover, 2);
    if (count($parts) !== 2) {
        coverNotFound();
    }

    [$mediaType, $entityKey] = $parts;
    $mediaType = strtolower(trim($mediaType));
    $entityKey = trim(urldecode($entityKey));

    if (!in_array($mediaType, COVER_MEDIA_TYPES, true) || $entityKey === '') {
        coverNotFound();
    }

    try {
        $store = coverStore();
        $row   = $store->find($mediaType, $entityKey);
    } catch (Throwable $e) {
        error_log('cover.php: ' . $e->getMessage());
        coverNotFound();
    }

    if ($row === null) {
        // No hay fila. Puede que sea catálogo: algo que no está en la
        // biblioteca pero cuya portada se deduce del mirror.
        $sourceUrl = $store->resolveCatalog($mediaType, $entityKey);

        if ($sourceUrl === null) {
            coverNotFound();
        }

        // La descarga va DESPUÉS de responder. Aquí dentro estamos en la carga
        // de un <img>: hacer esperar 1-2 s al navegador para servirle un 302
        // que igualmente iba a seguir sería pagar dos veces.
        PostResponse::defer(static function () use ($store, $mediaType, $entityKey): void {
            // Dirigida, no `fetchPending(1)`: esa se llevaría la más antigua de
            // la cola y la que se acaba de pedir seguiría sin bajar.
            $store->fetchOneNow($mediaType, $entityKey);
        });

        header('Cache-Control: no-store');
        header('Location: ' . $sourceUrl, true, 302);
        exit;
    }

    $local = $store->localPath($mediaType, $entityKey);
    if ($local === null) {
        // Registrada pero aún sin bajar (o el volumen se vació): al origen.
        // Sin caché, para que el navegador vuelva a preguntar cuando ya esté.
        header('Cache-Control: no-store');
        header('Location: ' . $row['source_url'], true, 302);
        exit;
    }

    // Servida desde disco: se anota el uso, que es lo que decide qué purga la
    // caducidad del catálogo. `touch()` escribe como mucho una vez al día.
    $store->touch($mediaType, $entityKey);

    header('Content-Type: ' . ($row['mime_type'] ?: 'image/jpeg'));
    header('Content-Length: ' . (string) filesize($local));
    header('Cache-Control: public, max-age=' . COVER_MAX_AGE);
    http_response_code(200);

    readfile($local);
    exit;
}

/**
 * Arranca lo mínimo para resolver CoverStore.
 *
 * El bucle del .env se duplica desde bootstrap.php por lo mismo que en
 * bin/mirror: tocar el arranque HTTP para ahorrarse diez líneas sale caro.
 */
function coverStore(): CoverStore
{
    return coverContainer()->get(CoverStore::class);
}

/**
 * Arranca lo mínimo para resolver del contenedor.
 *
 * Devuelve el contenedor entero, no solo CoverStore: desde que la portada de
 * catálogo se resuelve aquí, CoverStore necesita TmdbService inyectado y eso lo
 * hace config/container.php.
 */
function coverContainer(): ContainerInterface
{
    $root = dirname(__DIR__);

    require_once $root . '/vendor/autoload.php';

    $envFile = $root . '/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(trim($line), '#') === 0 || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($key, $_ENV) || $_ENV[$key] === '') {
                $_ENV[$key] = $value;
            }
        }
    }

    $_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'development';

    if (file_exists($root . '/config/helpers.php')) {
        require_once $root . '/config/helpers.php';
    }
    if (file_exists($root . '/src/Infrastructure/Logging/functions.php')) {
        require_once $root . '/src/Infrastructure/Logging/functions.php';
    }

    // La misma trampa que documenta bin/mirror: container.php llama a
    // LoggerFactory::createDatabaseLogger() en directo, y quien rellena su
    // config es LoggingService::getInstance(). Bajo Apache eso pasa de rebote
    // porque Application::bootstrap() escribe una línea de log antes de
    // construir el contenedor; aquí no pasamos por Application, así que sin
    // esto el contenedor revienta al resolver LoggerInterface (maxFiles null).
    LoggingService::getInstance();

    $containerFactory = require $root . '/config/container.php';

    return $containerFactory();
}

function coverNotFound(): never
{
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    echo "Cover not found\n";
    exit;
}
