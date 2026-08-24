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
