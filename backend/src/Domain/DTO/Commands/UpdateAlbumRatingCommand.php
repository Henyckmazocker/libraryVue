<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for updating user's album rating
 *
 * `$rating` es nulable: `null` (o un `rating` de 0) borra la valoración.
 */
final readonly class UpdateAlbumRatingCommand
{
    public function __construct(
        public int $userId,
        public int $albumId,
        public ?Rating $rating
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!isset($data['albumId'])) {
            throw new \InvalidArgumentException('Album ID is required.');
        }

        return new self(
            userId: $userId,
            albumId: is_int($data['albumId']) ? $data['albumId'] : (int)$data['albumId'],
            rating: isset($data['rating']) && (float)$data['rating'] > 0
                ? Rating::fromNullableFloat((float)$data['rating'])
                : null
        );
    }
}
