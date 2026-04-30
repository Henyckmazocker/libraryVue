<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for updating user's album statuses
 */
final readonly class UpdateAlbumStatusesCommand
{
    /**
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @param array $statuses New statuses to set (replaces existing)
     */
    public function __construct(
        public int $userId,
        public int $albumId,
        public array $statuses
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!isset($data['statuses']) || !is_array($data['statuses'])) {
            throw new \InvalidArgumentException('Statuses array is required.');
        }

        if (!isset($data['albumId'])) {
            throw new \InvalidArgumentException('Album ID is required.');
        }

        return new self(
            userId: $userId,
            albumId: is_int($data['albumId']) ? $data['albumId'] : (int)$data['albumId'],
            statuses: $data['statuses']
        );
    }
}
