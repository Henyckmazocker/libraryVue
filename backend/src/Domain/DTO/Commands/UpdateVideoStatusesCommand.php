<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for updating user statuses for a video
 */
final readonly class UpdateVideoStatusesCommand
{
    public function __construct(
        public int $userId,
        public string $youtubeId,
        public array $statuses
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId:    $userId,
            youtubeId: $data['youtubeId'] ?? $data['youtube_id'] ?? $data['id'] ?? '',
            statuses:  $data['statuses'] ?? []
        );
    }
}
