<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Model\FeedEvent;
use App\Domain\DTO\Commands\CreateFeedEventCommand;
use App\Domain\UseCases\Social\CreateFeedEventUseCase;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * FeedEventService
 *
 * Convenience wrapper around CreateFeedEventUseCase.
 * Use cases that need to emit feed events inject this service
 * and call the typed helper methods.
 *
 * All errors are caught and logged to prevent feed failures
 * from breaking the main library operation.
 */
class FeedEventService
{
    public function __construct(
        private readonly CreateFeedEventUseCase $createFeedEvent,
        private readonly LoggerInterface        $logger
    ) {}

    public function recordItemAdded(
        int     $userId,
        string  $entityType,
        string  $entityId,
        string  $title,
        ?string $cover
    ): void {
        $this->dispatch(new CreateFeedEventCommand(
            userId:      $userId,
            eventType:   FeedEvent::TYPE_ITEM_ADDED,
            entityType:  $entityType,
            entityId:    $entityId,
            entityTitle: $title,
            entityCover: $cover
        ));
    }

    public function recordStatusChanged(
        int     $userId,
        string  $entityType,
        string  $entityId,
        string  $title,
        ?string $cover,
        string  $oldStatus,
        string  $newStatus
    ): void {
        $this->dispatch(new CreateFeedEventCommand(
            userId:      $userId,
            eventType:   FeedEvent::TYPE_STATUS_CHANGED,
            entityType:  $entityType,
            entityId:    $entityId,
            entityTitle: $title,
            entityCover: $cover,
            metadata:    ['old_status' => $oldStatus, 'new_status' => $newStatus]
        ));
    }

    public function recordItemRated(
        int     $userId,
        string  $entityType,
        string  $entityId,
        string  $title,
        ?string $cover,
        float   $rating
    ): void {
        $this->dispatch(new CreateFeedEventCommand(
            userId:      $userId,
            eventType:   FeedEvent::TYPE_ITEM_RATED,
            entityType:  $entityType,
            entityId:    $entityId,
            entityTitle: $title,
            entityCover: $cover,
            metadata:    ['rating' => $rating]
        ));
    }

    /**
     * Una nota nueva sobre algo de la biblioteca.
     *
     * **El texto va entero, sin truncar**, y es una decisión, no un descuido: la
     * tarjeta del feed lo recorta visualmente con un «ver más» y CSS. Truncarlo
     * aquí obligaría a una petición extra para desplegarlo.
     *
     * **Este método NO comprueba si la nota es privada.** La guarda vive en cada
     * `Add*NoteUseCase`, y también es deliberado: esta clase se traga sus
     * propios errores por diseño —un fallo del feed no puede tumbar el guardado
     * de una nota—, así que esconder aquí una regla de privacidad convertiría un
     * fallo silencioso en un **escape de privacidad silencioso**.
     *
     * @param string      $noteText el texto entero de la nota
     * @param string|null $noteType 'quote', 'idea'… tal y como lo mande cada
     *                              entidad; no se normaliza (en libros es un
     *                              ENUM y en las otras cuatro un VARCHAR)
     */
    public function recordNotesUpdated(
        int     $userId,
        string  $entityType,
        string  $entityId,
        string  $title,
        ?string $cover,
        string  $noteText,
        ?string $noteType = null
    ): void {
        $this->dispatch(new CreateFeedEventCommand(
            userId:      $userId,
            eventType:   FeedEvent::TYPE_NOTES_UPDATED,
            entityType:  $entityType,
            entityId:    $entityId,
            entityTitle: $title,
            entityCover: $cover,
            metadata:    [
                'note_text' => $noteText,
                'note_type' => $noteType,
            ]
        ));
    }

    public function recordReadingSession(
        int     $userId,
        string  $entityId,
        string  $title,
        ?string $cover,
        array   $sessionData = []
    ): void {
        $this->dispatch(new CreateFeedEventCommand(
            userId:      $userId,
            eventType:   FeedEvent::TYPE_READING_SESSION,
            entityType:  FeedEvent::ENTITY_BOOK,
            entityId:    $entityId,
            entityTitle: $title,
            entityCover: $cover,
            metadata:    $sessionData ?: null
        ));
    }

    private function dispatch(CreateFeedEventCommand $command): void
    {
        try {
            $this->createFeedEvent->execute($command);
        } catch (Throwable $e) {
            // Feed events are non-critical — log and continue
            $this->logger->warning('FeedEventService: failed to record event', [
                'event_type'  => $command->eventType,
                'entity_type' => $command->entityType,
                'user_id'     => $command->userId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
