<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for updating book rating
 */
final readonly class UpdateBookRatingCommand
{
    public function __construct(
        public ISBN $isbn,
        public int $userId,
        public Rating $rating
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            isbn: ISBN::fromString($data['isbn']),
            userId: $userId,
            rating: isset($data['rating']) && (float)$data['rating'] > 0
                ? Rating::fromNullableFloat((float)$data['rating'])
                : null
        );
    }
}
