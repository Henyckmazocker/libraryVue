<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\YouTubeId;
use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for adding a YouTube video to the user's library
 */
final readonly class AddVideoCommand
{
    public function __construct(
        public YouTubeId $youtubeId,
        public string $title,
        public int $userId,
        public array $statuses = [],
        public ?string $channelName = null,
        public ?string $channelId = null,
        public ?string $coverUrl = null,
        public ?Rating $userRating = null,
        public ?string $duration = null,
        public ?int $durationSeconds = null,
        public ?int $viewCount = null,
        public ?int $likeCount = null,
        public ?string $publishedAt = null,
        public ?string $description = null,
        public ?array $categories = null,
        public ?string $personalNotes = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $youtubeIdStr = $data['youtubeId'] ?? $data['youtube_id'] ?? $data['id'] ?? '';
        $youtubeId = YouTubeId::fromString($youtubeIdStr);

        $userRating = null;
        $ratingValue = $data['userRating'] ?? $data['user_rating'] ?? null;
        if ($ratingValue !== null && is_numeric($ratingValue)) {
            $userRating = Rating::fromNullableFloat((float)$ratingValue);
        }

        return new self(
            youtubeId:      $youtubeId,
            title:          $data['title'] ?? '',
            userId:         $userId,
            statuses:       $data['statuses'] ?? [],
            channelName:    $data['channelName'] ?? $data['channel_name'] ?? null,
            channelId:      $data['channelId'] ?? $data['channel_id'] ?? null,
            coverUrl:       $data['coverUrl'] ?? $data['cover_url'] ?? $data['thumbnail'] ?? null,
            userRating:     $userRating,
            duration:       $data['duration'] ?? null,
            durationSeconds: isset($data['durationSeconds']) ? (int)$data['durationSeconds']
                            : (isset($data['duration_seconds']) ? (int)$data['duration_seconds'] : null),
            viewCount:      isset($data['viewCount']) ? (int)$data['viewCount']
                            : (isset($data['view_count']) ? (int)$data['view_count'] : null),
            likeCount:      isset($data['likeCount']) ? (int)$data['likeCount']
                            : (isset($data['like_count']) ? (int)$data['like_count'] : null),
            publishedAt:    $data['publishedAt'] ?? $data['published_at'] ?? null,
            description:    $data['description'] ?? null,
            categories:     $data['categories'] ?? null,
            personalNotes:  $data['personalNotes'] ?? $data['personal_notes'] ?? null,
        );
    }
}
