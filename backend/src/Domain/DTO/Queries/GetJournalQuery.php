<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

use App\Domain\Model\JournalEntry;

final readonly class GetJournalQuery
{
    public function __construct(
        public int     $userId,
        public int     $limit  = 30,
        public int     $offset = 0,
        public ?string $media  = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $media = $data['media'] ?? null;

        return new self(
            userId: $userId,
            // El tope se aplica en el servidor, como en `GetFeedQuery`: un
            // `limit` que llega del cliente no se cree.
            limit:  min(100, max(1, (int) ($data['limit'] ?? 30))),
            offset: max(0, (int) ($data['offset'] ?? 0)),
            // Un medio que no existe se ignora en vez de reventar: el filtro es
            // una comodidad del listado, no una condición de la petición.
            media:  is_string($media) && in_array($media, JournalEntry::MEDIA, true) ? $media : null
        );
    }
}
