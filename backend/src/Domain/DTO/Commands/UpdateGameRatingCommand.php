<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for updating user's game rating
 */
final readonly class UpdateGameRatingCommand
{
    public function __construct(
        public int $userId,
        public int $gameId,
        public Rating $rating
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!isset($data['rating']) || !is_numeric($data['rating'])) {
            throw new \InvalidArgumentException('Valid rating is required');
        }

        if (!isset($data['gameId'])) {
            throw new \InvalidArgumentException('Game ID is required');
        }

        return new self(
            userId: $userId,
            gameId: is_int($data['gameId']) ? $data['gameId'] : (int)$data['gameId'],
            rating: Rating::fromFloat((float)$data['rating'])
        );
    }
}
