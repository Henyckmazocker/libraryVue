<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

final readonly class UpdateJournalEntryCommand
{
    public function __construct(
        public int     $userId,
        public int     $entryId,
        public ?string $entryDate = null,
        public ?float  $rating    = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $fecha  = $data['entryDate'] ?? $data['entry_date'] ?? null;
        $rating = $data['rating'] ?? null;

        return new self(
            userId:    $userId,
            entryId:   (int) ($data['entryId'] ?? $data['entry_id'] ?? 0),
            // Cadena vacía es «no lo toques»; `null` en el rating SÍ es «quítalo»,
            // y por eso los dos casos no se tratan igual.
            entryDate: ($fecha === null || $fecha === '') ? null : (string) $fecha,
            rating:    ($rating === null || $rating === '') ? null : (float) $rating
        );
    }
}
