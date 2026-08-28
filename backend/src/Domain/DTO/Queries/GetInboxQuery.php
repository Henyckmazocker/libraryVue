<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

use App\Domain\Model\Recommendation;

final readonly class GetInboxQuery
{
    public function __construct(
        public int    $userId,
        public string $status = Recommendation::STATUS_PENDING,
        public int    $limit = 20,
        public int    $offset = 0
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $status = trim((string) ($data['status'] ?? Recommendation::STATUS_PENDING));

        return new self(
            userId: $userId,
            // Un estado que no existe devolvería una lista vacía sin decir por
            // qué; se cae a `pending`, que es lo que la bandeja pide siempre.
            status: in_array($status, [
                Recommendation::STATUS_PENDING,
                Recommendation::STATUS_ADDED,
                Recommendation::STATUS_DISMISSED,
            ], true) ? $status : Recommendation::STATUS_PENDING,
            limit:  max(1, min(100, (int) ($data['limit'] ?? 20))),
            offset: max(0, (int) ($data['offset'] ?? 0))
        );
    }
}
