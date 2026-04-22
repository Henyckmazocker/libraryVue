<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for deleting an album from user's library
 */
final readonly class DeleteAlbumCommand
{
    public function __construct(
        public int $userId,
        public int $albumId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!isset($data['albumId'])) {
            throw new \InvalidArgumentException('Album ID is required.');
        }

        return new self(
            userId: $userId,
            albumId: is_int($data['albumId']) ? $data['albumId'] : (int)$data['albumId']
        );
    }
}
