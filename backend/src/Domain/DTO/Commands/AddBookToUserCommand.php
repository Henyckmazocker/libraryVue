<?php
declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\ISBN;

/**
 * Command to add a book to a user's library
 */
final readonly class AddBookToUserCommand
{
    public function __construct(
        public int $userId,
        public ISBN $isbn,
        public array $statuses = []
    ) {}

    /**
     * Create from associative array
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['user_id'])) {
            throw new \InvalidArgumentException('user_id is required');
        }
        if (!isset($data['isbn'])) {
            throw new \InvalidArgumentException('isbn is required');
        }

        return new self(
            userId: (int)$data['user_id'],
            isbn: ISBN::fromString($data['isbn']),
            statuses: $data['statuses'] ?? []
        );
    }
}
