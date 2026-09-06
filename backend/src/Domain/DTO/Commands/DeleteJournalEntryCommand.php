<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

final readonly class DeleteJournalEntryCommand
{
    public function __construct(
        public int $userId,
        public int $entryId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId:  $userId,
            entryId: (int) ($data['entryId'] ?? $data['entry_id'] ?? 0)
        );
    }
}
