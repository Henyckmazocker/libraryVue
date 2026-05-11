<?php

declare(strict_types=1);

namespace App\Domain\Repository\Video;

/**
 * Repository interface for User Video Tags operations
 *
 * Manages custom tags for videos per user
 */
interface VideoTagRepositoryInterface
{
    /**
     * Get all tags created by a user
     *
     * @return array Tags with id, name, color
     */
    public function findByUser(int $userId): array;

    /**
     * Create a new tag for a user
     *
     * @return int New tag ID
     */
    public function create(int $userId, string $name, string $color = '#c0392b'): int;

    /**
     * Delete a tag
     */
    public function delete(int $userId, int $tagId): bool;

    /**
     * Assign a tag to a video
     */
    public function assignToVideo(int $userId, int $videoId, int $tagId): void;

    /**
     * Remove a tag from a video
     */
    public function removeFromVideo(int $userId, int $videoId, int $tagId): void;

    /**
     * Remove all tags from a specific video
     */
    public function removeAllFromVideo(int $userId, int $videoId): void;

    /**
     * Get all tag IDs assigned to a specific video
     */
    public function getVideoTags(int $userId, int $videoId): array;

    /**
     * Sync tags for a video — replaces all existing assignments
     */
    public function syncVideoTags(int $userId, int $videoId, array $tagIds): void;
}
