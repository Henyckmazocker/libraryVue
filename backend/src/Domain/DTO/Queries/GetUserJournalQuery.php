<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

final readonly class GetUserJournalQuery
{
    public function __construct(
        public string $username,
        public int    $viewerUserId,
        public int    $limit  = 20,
        public int    $offset = 0
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            username:     trim((string) ($data['username'] ?? '')),
            viewerUserId: $userId,
            // Tope más bajo que el del diario propio (`GetJournalQuery`, 100):
            // esto es una sección de un perfil ajeno, no un listado paginado.
            limit:        min(50, max(1, (int) ($data['limit'] ?? 20))),
            offset:       max(0, (int) ($data['offset'] ?? 0))
        );
    }
}
