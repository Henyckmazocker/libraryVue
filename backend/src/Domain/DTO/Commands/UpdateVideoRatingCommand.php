<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for updating a user's personal rating for a video
 */
final readonly class UpdateVideoRatingCommand
{
    public function __construct(
        public int $userId,
        public string $youtubeId,
        public Rating $rating
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $ratingValue = $data['rating'] ?? $data['personalRating'] ?? $data['userRating'] ?? null;
        if ($ratingValue === null) {
            throw new \InvalidArgumentException('Rating is required.');
        }

        return new self(
            userId:    $userId,
            youtubeId: $data['youtubeId'] ?? $data['youtube_id'] ?? $data['id'] ?? '',
            rating:    Rating::fromFloat((float)$ratingValue)
        );
    }
}
