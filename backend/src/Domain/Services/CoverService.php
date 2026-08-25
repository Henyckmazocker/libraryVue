<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Infrastructure\Covers\CoverStore;
use App\Infrastructure\Http\PostResponse;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * CoverService
 *
 * El envoltorio que usan los use cases de añadir a biblioteca para quedarse con
 * una copia local de la portada. Mismo papel que FeedEventService: se inyecta,
 * se llama con una línea y **se traga sus propios errores**, porque una portada
 * jamás puede tumbar un guardado.
 *
 * El reparto es lo importante:
 *
 *   - `register()` corre DENTRO de la petición y solo escribe una fila. No sale
 *     a la red, así que no añade latencia medible.
 *   - la descarga se encola con PostResponse y corre con la conexión ya cerrada.
 *     Si falla, la fila se queda pendiente y la recoge `bin/mirror
 *     covers:backfill`.
 */
class CoverService
{
    /**
     * Cuántas se intentan bajar tras la respuesta.
     *
     * Cinco y no todas: es trabajo que corre con el proceso de Apache aún
     * ocupado, y lo que no entre aquí lo recoge el backfill sin prisa.
     */
    private const BATCH_AFTER_RESPONSE = 5;

    /**
     * Una sola descarga encolada por petición.
     *
     * `LibraryController::importData()` llama al use case de añadir en bucle:
     * sin esto, importar 200 ítems encolaría 200 tareas de 5 descargas cada una
     * y el proceso de Apache se quedaría minutos ocupado después de responder.
     * El resto lo recoge `bin/mirror covers:backfill`, que para eso está.
     */
    private bool $deferred = false;

    public function __construct(
        private readonly CoverStore      $covers,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Registra la portada de un ítem recién guardado y programa su descarga.
     *
     * @param string      $mediaType 'movie' | 'series' | 'book' | 'album' | 'game' | 'video'
     * @param string      $entityKey la clave con la que el frontend pedirá la portada
     * @param string|null $sourceUrl la URL remota; si no hay, no hay nada que hacer
     */
    /**
     * Reaprovecha la portada que ya se bajó cuando el ítem estaba en el catálogo.
     *
     * Se llama **en lugar de** `recordCover()` cuando devuelve `true`, y eso no
     * es cosmético: `register()` hace `ON DUPLICATE KEY UPDATE` y, al ver una
     * `source_url` distinta —la fila promovida guarda la URL **resuelta**
     * (`archive.org/download/…`) y el álbum trae la de la búsqueda
     * (`coverartarchive.org/…`)— haría exactamente lo que documenta:
     * `storage_path = NULL`, `attempts = 0`. La portada se volvería a bajar y
     * habría dos copias del mismo JPEG en disco, que es justo lo que este
     * mecanismo existe para evitar. Medido el 2026-08-25.
     *
     * Como todo lo de esta clase, se traga sus errores: una portada no tumba un
     * guardado.
     *
     * @return bool si se reaprovechó una fila de catálogo; si es `false`, quien
     *              llama tiene que registrar la portada como siempre
     */
    public function promoteCatalogCover(string $mediaType, string $catalogKey, string $libraryKey): bool
    {
        try {
            return $this->covers->promoteToLibrary($mediaType, $catalogKey, $libraryKey);
        } catch (Throwable $e) {
            $this->logger->warning('CoverService: no se pudo reaprovechar la portada de catálogo', [
                'media_type'  => $mediaType,
                'catalog_key' => $catalogKey,
                'error'       => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function recordCover(string $mediaType, string $entityKey, ?string $sourceUrl): void
    {
        if ($sourceUrl === null || $sourceUrl === '') {
            return;
        }

        try {
            $this->covers->register($mediaType, $entityKey, $sourceUrl);

            if (!$this->deferred) {
                $this->deferred = true;
                PostResponse::defer(function (): void {
                    $this->covers->fetchPending(self::BATCH_AFTER_RESPONSE);
                });
            }
        } catch (Throwable $e) {
            $this->logger->warning('CoverService: no se pudo programar la portada', [
                'media_type' => $mediaType,
                'entity_key' => $entityKey,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
