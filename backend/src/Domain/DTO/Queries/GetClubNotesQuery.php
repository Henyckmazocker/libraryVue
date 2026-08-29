<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

use InvalidArgumentException;

final readonly class GetClubNotesQuery
{
    public function __construct(
        public int $userId,
        public int $clubId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $clubId = (int) ($data['clubId'] ?? $data['club_id'] ?? 0);
        if ($clubId <= 0) {
            throw new InvalidArgumentException('clubId is required');
        }

        return new self(userId: $userId, clubId: $clubId);
    }
}
