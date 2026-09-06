<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\JournalEntry;

/**
 * Alta manual en el diario: «el 12 de julio vi Dune».
 *
 * El título y la portada los resuelve el caso de uso contra la biblioteca del
 * usuario; aquí solo viaja lo que el formulario pregunta.
 */
final readonly class AddJournalEntryCommand
{
    public function __construct(
        public int     $userId,
        public string  $media,
        public string  $entityId,
        public string  $entryDate,
        public ?float  $rating = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $rating = $data['rating'] ?? null;

        return new self(
            userId:    $userId,
            media:     (string) ($data['media'] ?? ''),
            entityId:  (string) ($data['entityId'] ?? $data['entity_id'] ?? ''),
            // Sin fecha, hoy: es lo que espera quien acaba de terminar algo.
            // Las fechas futuras se admiten a propósito (decidido el 2026-09-04).
            entryDate: (string) ($data['entryDate'] ?? $data['entry_date'] ?? date('Y-m-d')),
            rating:    ($rating === null || $rating === '') ? null : (float) $rating
        );
    }

    public function toEntry(string $title, ?string $cover): JournalEntry
    {
        return new JournalEntry(
            id:          null,
            userId:      $this->userId,
            media:       $this->media,
            entityId:    $this->entityId,
            entityTitle: $title,
            entityCover: $cover,
            entryDate:   $this->entryDate,
            rating:      $this->rating,
            source:      JournalEntry::SOURCE_MANUAL,
            sourceId:    null
        );
    }
}
