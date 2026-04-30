<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for updating user's album rating
 */
final readonly class UpdateAlbumRatingCommand
{
    public function __construct(
        public int $userId,
        public int $albumId,
        public Rating $rating
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!isset($data['rating']) || !is_numeric($data['rating'])) {
            throw new \InvalidArgumentException('Valid rating is required.');
        }

        if (!isset($data['albumId'])) {
            throw new \InvalidArgumentException('Album ID is required.');
        }

        return new self(
            userId: $userId,
            albumId: is_int($data['albumId']) ? $data['albumId'] : (int)$data['albumId'],
            rating: Rating::fromFloat((float)$data['rating'])
        );
    }
}
