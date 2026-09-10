<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for updating user's game rating
 *
 * `$rating` es nulable: `null` (o un `rating` de 0) borra la valoración. Antes
 * el 0 salía por `InvalidArgumentException`, al revés que libro y película.
 */
final readonly class UpdateGameRatingCommand
{
    public function __construct(
        public int $userId,
        public int $gameId,
        public ?Rating $rating
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!isset($data['gameId'])) {
            throw new \InvalidArgumentException('Game ID is required');
        }

        return new self(
            userId: $userId,
            gameId: is_int($data['gameId']) ? $data['gameId'] : (int)$data['gameId'],
            rating: isset($data['rating']) && (float)$data['rating'] > 0
                ? Rating::fromNullableFloat((float)$data['rating'])
                : null
        );
    }
}
