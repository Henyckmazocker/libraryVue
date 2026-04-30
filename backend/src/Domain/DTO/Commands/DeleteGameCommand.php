<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for deleting a game from user's library
 */
final readonly class DeleteGameCommand
{
    public function __construct(
        public int $userId,
        public int $gameId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!isset($data['gameId'])) {
            throw new \InvalidArgumentException('Game ID is required');
        }

        return new self(
            userId: $userId,
            gameId: is_int($data['gameId']) ? $data['gameId'] : (int)$data['gameId']
        );
    }
}
