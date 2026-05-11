<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for editing user's video data (rating, statuses, notes, tags, watch count)
 *
 * Uses ?array $statuses = null to distinguish between:
 * - null  → statuses were not sent; do not touch them
 * - []    → user cleared all statuses; remove them all
 */
final readonly class EditUserVideoCommand
{
    public function __construct(
        public int $userId,
        public string $youtubeId,
        public ?Rating $userRating = null,
        public ?string $personalNotes = null,
        public ?int $watchCount = null,
        public ?string $watchedAt = null,
        public ?array $statuses = null,
        public array $tags = []
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $youtubeId = $data['youtubeId'] ?? $data['youtube_id'] ?? $data['id'] ?? '';
        if (empty($youtubeId)) {
            throw new \InvalidArgumentException('YouTube ID is required.');
        }

        // Support nested payload from frontend EditItemModal pattern
        $videoData = $data['data'] ?? $data;

        $userRating = null;
        $ratingValue = $videoData['personalRating'] ?? $videoData['userRating'] ?? $videoData['user_rating'] ?? null;
        if ($ratingValue !== null && is_numeric($ratingValue)) {
            $userRating = Rating::fromNullableFloat((float)$ratingValue);
        }

        $watchCount = null;
        $watchValue = $videoData['watchCount'] ?? $videoData['watch_count'] ?? null;
        if ($watchValue !== null && is_numeric($watchValue)) {
            $watchCount = (int)$watchValue;
        }

        return new self(
            userId:       $userId,
            youtubeId:    $youtubeId,
            userRating:   $userRating,
            personalNotes: $videoData['personalNotes'] ?? $videoData['personal_notes'] ?? $videoData['notes'] ?? null,
            watchCount:   $watchCount,
            watchedAt:    $videoData['watchedAt'] ?? $videoData['watched_at'] ?? null,
            statuses:     $videoData['statuses'] ?? $data['statuses'] ?? null,
            tags:         $data['tags'] ?? []
        );
    }
}
