<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for updating user's game statuses
 */
final readonly class UpdateGameStatusesCommand
{
    /**
     * @param int $userId User ID
     * @param int $gameId Game ID
     * @param array $statuses New statuses to set
     */
    public function __construct(
        public int $userId,
        public int $gameId,
        public array $statuses
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!isset($data['statuses']) || !is_array($data['statuses'])) {
            throw new \InvalidArgumentException('Statuses array is required');
        }

        if (!isset($data['gameId'])) {
            throw new \InvalidArgumentException('Game ID is required');
        }

        return new self(
            userId: $userId,
            gameId: is_int($data['gameId']) ? $data['gameId'] : (int)$data['gameId'],
            statuses: $data['statuses']
        );
    }
}
