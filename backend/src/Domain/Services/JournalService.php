<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * JournalService
 *
 * El emisor del diario. Mismo papel y mismas reglas que
 * {@see FeedEventService}: los casos de uso que marcan algo como consumido lo
 * inyectan y llaman a `record()`; **los errores se registran y no suben**, para
 * que un fallo del diario no tumbe la operación de la que cuelga (marcar un
 * libro como leído tiene que funcionar aunque el diario esté roto).
 *
 * Lo que este servicio **no** hace es decidir si hubo transición. Eso vive en
 * cada caso de uso, que es el único que conoce los estados previos, igual que la
 * guarda de privacidad de `recordNotesUpdated()` vive en cada `Add*NoteUseCase`:
 * esconder una regla dentro de una clase que se traga sus errores convierte un
 * fallo silencioso en una decisión silenciosa.
 */
class JournalService
{
    public function __construct(
        private readonly JournalRepositoryInterface $journal,
        private readonly LoggerInterface            $logger
    ) {}

    /**
     * Apunta que el usuario consumió algo un día.
     *
     * @param string      $media     uno de los seis de `JournalEntry::MEDIA_*`
     * @param string      $entityId  el identificador EXTERNO (ISBN-13, imdbID, id de IGDB…)
     * @param string      $entryDate `YYYY-MM-DD`; admite fechas futuras a propósito
     * @param string      $source    `JournalEntry::SOURCE_*`
     * @param string|null $sourceId  NULL solo en `manual`; en los demás, la clave estable
     *                               que evita el duplicado (id de sesión, `imdb:temporada`,
     *                               `medio:id:día`)
     */
    public function record(
        int     $userId,
        string  $media,
        string  $entityId,
        string  $title,
        ?string $cover,
        string  $entryDate,
        ?float  $rating,
        string  $source,
        ?string $sourceId
    ): void {
        try {
            $this->journal->add(new JournalEntry(
                id:          null,
                userId:      $userId,
                media:       $media,
                entityId:    $entityId,
                entityTitle: $title,
                entityCover: $cover,
                entryDate:   $entryDate,
                rating:      $rating,
                source:      $source,
                sourceId:    $sourceId
            ));
        } catch (Throwable $e) {
            // El diario no es crítico — se registra y se sigue
            $this->logger->warning('JournalService: failed to record entry', [
                'media'     => $media,
                'entity_id' => $entityId,
                'source'    => $source,
                'user_id'   => $userId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Apunta en el diario **solo si el ítem acaba de pasar a consumido**.
     *
     * Aquí vive la respuesta al riesgo que mandaba el plan: los cinco
     * `Update<Medio>UserStatusesUseCase` reciben el CONJUNTO ENTERO de estados y
     * lo sustituyen, así que no saben qué cambió. Sin esta comparación, reabrir
     * el selector de una película ya vista y volver a guardar cualquier otra
     * cosa apuntaría que la viste hoy, y un mes de trastear dejaría el diario
     * inservible.
     *
     * Se compara contra los estados **previos**, que hay que leer antes del
     * `updateStatuses`. Que `array_intersect` devuelva dos disparadores —un juego
     * marcado a la vez como `played` y `completed`— da igual: la clave
     * `medio:id:día` deduplica y sale una entrada.
     *
     * @param string[] $previos estados que tenía el ítem antes de guardar
     * @param string[] $nuevos  el conjunto que llega en el comando
     */
    public function recordIfConsumed(
        int     $userId,
        string  $media,
        string  $entityId,
        string  $title,
        ?string $cover,
        array   $previos,
        array   $nuevos
    ): void {
        $anadidos     = array_diff($nuevos, $previos);
        $disparadores = array_intersect($anadidos, JournalEntry::CONSUMED_STATUSES[$media] ?? []);

        if ($disparadores === []) {
            return;
        }

        $this->recordFromStatus($userId, $media, $entityId, $title, $cover);
    }

    /**
     * Atajo para el origen `status`: la clave de duplicado es
     * `medio:id:día`, de modo que marcar y desmarcar el mismo día no apunta dos
     * veces, pero hacerlo otro día sí crea su entrada.
     */
    public function recordFromStatus(
        int     $userId,
        string  $media,
        string  $entityId,
        string  $title,
        ?string $cover,
        ?string $entryDate = null
    ): void {
        $date = $entryDate ?? date('Y-m-d');

        $this->record(
            userId:    $userId,
            media:     $media,
            entityId:  $entityId,
            title:     $title,
            cover:     $cover,
            entryDate: $date,
            rating:    null,
            source:    JournalEntry::SOURCE_STATUS,
            sourceId:  "{$media}:{$entityId}:{$date}"
        );
    }
}
