<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for removing a YouTube video from a user's library
 */
final readonly class DeleteVideoCommand
{
    public function __construct(
        public int $userId,
        public string $youtubeId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId:    $userId,
            youtubeId: $data['youtubeId'] ?? $data['youtube_id'] ?? $data['id'] ?? ''
        );
    }
}
