<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\ISBN;

/**
 * Command DTO for deleting a book from user's library
 */
final readonly class DeleteBookCommand
{
    public function __construct(
        public ISBN $isbn,
        public int $userId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            isbn: ISBN::fromString($data['isbn']),
            userId: $userId
        );
    }
}
