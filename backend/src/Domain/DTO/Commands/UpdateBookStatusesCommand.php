<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\ISBN;

/**
 * Command DTO for updating book user statuses
 */
final readonly class UpdateBookStatusesCommand
{
    /**
     * @param ISBN $isbn
     * @param int $userId
     * @param array $statuses Array of status names
     */
    public function __construct(
        public ISBN $isbn,
        public int $userId,
        public array $statuses
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            isbn: ISBN::fromString($data['isbn']),
            userId: $userId,
            statuses: $data['statuses'] ?? []
        );
    }
}
