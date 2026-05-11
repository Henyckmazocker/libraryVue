<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Video\Mappers;

use App\Domain\Model\Video;
use App\Domain\Model\ValueObjects\YouTubeId;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Timestamp;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

/**
 * Maps between database rows and Video domain entities
 */
class VideoDataMapper
{
    use HydrationHelpersTrait;

    /**
     * Convert a database row to a Video domain entity
     */
    public function toDomain(array $row): Video
    {
        $youtubeId = YouTubeId::fromString($this->extractString($row, 'youtube_id'));

        $userRating = Rating::fromNullableFloat(
            $this->extractFloat($row, 'user_rating', null)
        );

        $addedAt = isset($row['user_added_at'])
            ? Timestamp::fromString($this->extractString($row, 'user_added_at'))
            : Timestamp::now();

        // User statuses from GROUP_CONCAT
        $userStatuses = [];
        if (array_key_exists('user_statuses', $row) && $row['user_statuses'] !== null) {
            if (is_array($row['user_statuses'])) {
                $userStatuses = $row['user_statuses'];
            } elseif (is_string($row['user_statuses']) && $row['user_statuses'] !== '') {
                $userStatuses = explode(', ', $row['user_statuses']);
            }
        }

        // Categories stored as JSON
        $categoriesData = $this->extractJson($row, 'categories', []);

        return new Video(
            id:              $this->extractRequiredInt($row, 'id'),
            youtubeId:       $youtubeId,
            title:           $this->extractString($row, 'title'),
            channelName:     $this->extractString($row, 'channel_name', null),
            channelId:       $this->extractString($row, 'channel_id', null),
            coverUrl:        $this->extractString($row, 'cover_url', null),
            duration:        $this->extractString($row, 'duration', null),
            durationSeconds: $this->extractInt($row, 'duration_seconds', null),
            viewCount:       $this->extractInt($row, 'view_count', null),
            likeCount:       $this->extractInt($row, 'like_count', null),
            publishedAt:     $this->extractString($row, 'published_at', null),
            description:     $this->extractString($row, 'description', null),
            categories:      $categoriesData ?: null,
            addedTimestamp:  $addedAt,
            userRating:      $userRating,
            userStatuses:    $userStatuses,
            allowedStatuses: [],
            tags:            null,
            allowedTags:     null,
            personalNotes:   $this->extractString($row, 'personal_notes', null),
            watchCount:      $this->extractInt($row, 'watch_count', null),
            watchedAt:       $this->extractString($row, 'watched_at', null)
        );
    }

    /**
     * Convert a collection of rows to Video entities
     *
     * @return Video[]
     */
    public function toDomainCollection(array $rows): array
    {
        return array_map([$this, 'toDomain'], $rows);
    }

    /**
     * Convert a Video entity to database persistence array (INSERT/UPDATE)
     */
    public function toPersistence(Video $video): array
    {
        return [
            'youtube_id'      => $video->getYoutubeId()->toString(),
            'title'           => $video->getTitle(),
            'channel_name'    => $video->getChannelName(),
            'channel_id'      => $video->getChannelId(),
            'cover_url'       => $video->getCoverUrl(),
            'duration'        => $video->getDuration(),
            'duration_seconds'=> $video->getDurationSeconds(),
            'view_count'      => $video->getViewCount(),
            'like_count'      => $video->getLikeCount(),
            'published_at'    => $video->getPublishedAt() !== null
                                 ? (new \DateTime($video->getPublishedAt()))->format('Y-m-d H:i:s')
                                 : null,
            'description'     => $video->getDescription(),
            'categories'      => $video->getCategories() !== null
                                 ? json_encode($video->getCategories(), JSON_THROW_ON_ERROR)
                                 : null,
        ];
    }
}
