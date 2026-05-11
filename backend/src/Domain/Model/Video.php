<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Model\ValueObjects\YouTubeId;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Timestamp;
use InvalidArgumentException;

class Video
{
    private int $id;
    private YouTubeId $youtubeId;
    private string $title;
    private ?string $channelName;
    private ?string $channelId;
    private ?string $coverUrl;
    private ?string $duration;        // ISO 8601, e.g. "PT4M13S"
    private ?int $durationSeconds;
    private ?int $viewCount;
    private ?int $likeCount;
    private ?string $publishedAt;
    private ?string $description;
    private ?array $categories;
    private Timestamp $addedTimestamp;
    private ?Rating $userRating;
    private array $userStatuses;
    private array $allowedStatuses;
    private ?array $tags;
    private ?array $allowedTags;
    private ?string $personalNotes;
    private ?int $watchCount;
    private ?string $watchedAt;

    public function __construct(
        int $id,
        YouTubeId $youtubeId,
        string $title,
        ?string $channelName,
        ?string $channelId,
        ?string $coverUrl,
        ?string $duration,
        ?int $durationSeconds,
        ?int $viewCount,
        ?int $likeCount,
        ?string $publishedAt,
        ?string $description,
        ?array $categories,
        Timestamp $addedTimestamp,
        ?Rating $userRating,
        array $userStatuses,
        array $allowedStatuses = [],
        ?array $tags = null,
        ?array $allowedTags = null,
        ?string $personalNotes = null,
        ?int $watchCount = null,
        ?string $watchedAt = null
    ) {
        if (empty($title)) {
            throw new InvalidArgumentException('Title cannot be empty.');
        }
        if ($watchCount !== null && $watchCount < 0) {
            throw new InvalidArgumentException('Watch count must be non-negative.');
        }

        $this->id              = $id;
        $this->youtubeId       = $youtubeId;
        $this->title           = $title;
        $this->channelName     = $channelName;
        $this->channelId       = $channelId;
        $this->coverUrl        = $coverUrl;
        $this->duration        = $duration;
        $this->durationSeconds = $durationSeconds;
        $this->viewCount       = $viewCount;
        $this->likeCount       = $likeCount;
        $this->publishedAt     = $publishedAt;
        $this->description     = $description;
        $this->categories      = $categories;
        $this->addedTimestamp  = $addedTimestamp;
        $this->userRating      = $userRating;
        $this->userStatuses    = $userStatuses;
        $this->allowedStatuses = $allowedStatuses;
        $this->tags            = $tags;
        $this->allowedTags     = $allowedTags;
        $this->personalNotes   = $personalNotes;
        $this->watchCount      = $watchCount ?? 0;
        $this->watchedAt       = $watchedAt;
    }

    public static function fromArray(array $data): self
    {
        $youtubeIdStr = $data['youtube_id'] ?? $data['youtubeId'] ?? $data['id'] ?? '';
        if (empty($youtubeIdStr)) {
            throw new InvalidArgumentException('YouTube ID is required for a video.');
        }
        if (empty($data['title'])) {
            throw new InvalidArgumentException('Title is required for a video.');
        }
        if (!isset($data['userStatuses']) || !is_array($data['userStatuses'])) {
            throw new InvalidArgumentException('User statuses are required and must be an array.');
        }

        $youtubeId = YouTubeId::fromString($youtubeIdStr);

        $userRating = null;
        $ratingValue = $data['user_rating'] ?? $data['userRating'] ?? null;
        if ($ratingValue !== null && is_numeric($ratingValue)) {
            $userRating = Rating::fromNullableFloat((float)$ratingValue);
        }

        $addedTimestamp = isset($data['addedTimestamp'])
            ? Timestamp::fromUnixTimestamp((int)$data['addedTimestamp'])
            : Timestamp::now();

        $categories = null;
        $categoriesRaw = $data['categories'] ?? null;
        if ($categoriesRaw !== null) {
            if (is_string($categoriesRaw)) {
                $categoriesRaw = json_decode($categoriesRaw, true) ?? [];
            }
            if (is_array($categoriesRaw)) {
                $categories = $categoriesRaw;
            }
        }

        return new self(
            id:              (int)($data['id'] ?? 0),
            youtubeId:       $youtubeId,
            title:           $data['title'],
            channelName:     $data['channel_name'] ?? $data['channelName'] ?? null,
            channelId:       $data['channel_id'] ?? $data['channelId'] ?? null,
            coverUrl:        $data['cover_url'] ?? $data['coverUrl'] ?? $data['thumbnail'] ?? null,
            duration:        $data['duration'] ?? null,
            durationSeconds: isset($data['duration_seconds']) ? (int)$data['duration_seconds']
                             : (isset($data['durationSeconds']) ? (int)$data['durationSeconds'] : null),
            viewCount:       isset($data['view_count']) ? (int)$data['view_count']
                             : (isset($data['viewCount']) ? (int)$data['viewCount'] : null),
            likeCount:       isset($data['like_count']) ? (int)$data['like_count']
                             : (isset($data['likeCount']) ? (int)$data['likeCount'] : null),
            publishedAt:     $data['published_at'] ?? $data['publishedAt'] ?? null,
            description:     $data['description'] ?? null,
            categories:      $categories,
            addedTimestamp:  $addedTimestamp,
            userRating:      $userRating,
            userStatuses:    $data['userStatuses'],
            allowedStatuses: $data['allowedStatuses'] ?? [],
            tags:            $data['tags'] ?? null,
            allowedTags:     $data['allowedTags'] ?? null,
            personalNotes:   $data['personal_notes'] ?? $data['personalNotes'] ?? null,
            watchCount:      isset($data['watch_count']) ? (int)$data['watch_count']
                             : (isset($data['watchCount']) ? (int)$data['watchCount'] : null),
            watchedAt:       $data['watched_at'] ?? $data['watchedAt'] ?? null,
        );
    }

    // --- Getters ---

    public function getId(): int { return $this->id; }
    public function getYouTubeId(): YouTubeId { return $this->youtubeId; }
    public function getTitle(): string { return $this->title; }
    public function getChannelName(): ?string { return $this->channelName; }
    public function getChannelId(): ?string { return $this->channelId; }
    public function getCoverUrl(): ?string { return $this->coverUrl; }
    public function getDuration(): ?string { return $this->duration; }
    public function getDurationSeconds(): ?int { return $this->durationSeconds; }
    public function getViewCount(): ?int { return $this->viewCount; }
    public function getLikeCount(): ?int { return $this->likeCount; }
    public function getPublishedAt(): ?string { return $this->publishedAt; }
    public function getDescription(): ?string { return $this->description; }
    public function getCategories(): ?array { return $this->categories; }
    public function getAddedTimestamp(): Timestamp { return $this->addedTimestamp; }
    public function getUserRating(): ?Rating { return $this->userRating; }
    public function getUserStatuses(): array { return $this->userStatuses; }
    public function getAllowedStatuses(): array { return $this->allowedStatuses; }
    public function getTags(): ?array { return $this->tags; }
    public function getAllowedTags(): ?array { return $this->allowedTags; }
    public function getPersonalNotes(): ?string { return $this->personalNotes; }
    public function getWatchCount(): ?int { return $this->watchCount; }
    public function getWatchedAt(): ?string { return $this->watchedAt; }

    // --- Setters for post-construction population ---

    public function setTags(?array $tags): void { $this->tags = $tags; }
    public function setAllowedTags(?array $allowedTags): void { $this->allowedTags = $allowedTags; }
    public function setAllowedStatuses(array $allowedStatuses): void { $this->allowedStatuses = $allowedStatuses; }

    // --- Utility ---

    public function hasStatus(string $status): bool
    {
        return in_array($status, $this->userStatuses, true);
    }

    public function isWatched(): bool
    {
        return $this->hasStatus('watched') || $this->hasStatus('re-watching');
    }

    public function isWatching(): bool
    {
        return $this->hasStatus('watching');
    }

    public function isInWatchlist(): bool
    {
        return $this->hasStatus('in-watchlist') || $this->hasStatus('want-to-watch');
    }

    public function getFormattedDuration(): string
    {
        if ($this->durationSeconds === null || $this->durationSeconds <= 0) {
            return $this->duration ?? '0:00';
        }

        $hours   = intdiv($this->durationSeconds, 3600);
        $minutes = intdiv($this->durationSeconds % 3600, 60);
        $seconds = $this->durationSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'youtube_id'       => $this->youtubeId->toString(),
            'youtubeId'        => $this->youtubeId->toString(),
            'title'            => $this->title,
            'channel_name'     => $this->channelName,
            'channelName'      => $this->channelName,
            'channel_id'       => $this->channelId,
            'channelId'        => $this->channelId,
            'cover_url'        => $this->coverUrl,
            'coverUrl'         => $this->coverUrl,
            'thumbnail'        => $this->coverUrl, // alias for frontend compatibility
            'duration'         => $this->duration,
            'duration_seconds' => $this->durationSeconds,
            'durationSeconds'  => $this->durationSeconds,
            'durationFormatted'=> $this->getFormattedDuration(),
            'view_count'       => $this->viewCount,
            'viewCount'        => $this->viewCount,
            'like_count'       => $this->likeCount,
            'likeCount'        => $this->likeCount,
            'published_at'     => $this->publishedAt,
            'publishedAt'      => $this->publishedAt,
            'description'      => $this->description,
            'categories'       => $this->categories,
            'addedTimestamp'   => $this->addedTimestamp->toUnixTimestamp(),
            'user_rating'      => $this->userRating?->toFloat(),
            'userRating'       => $this->userRating?->toFloat(),
            'userStatuses'     => $this->userStatuses,
            'allowedStatuses'  => $this->allowedStatuses,
            'tags'             => $this->tags,
            'allowedTags'      => $this->allowedTags,
            'personal_notes'   => $this->personalNotes,
            'personalNotes'    => $this->personalNotes,
            'notes'            => $this->personalNotes, // alias
            'watch_count'      => $this->watchCount,
            'watchCount'       => $this->watchCount,
            'watched_at'       => $this->watchedAt,
            'watchedAt'        => $this->watchedAt,
            'itemType'         => 'video',
        ];
    }
}
