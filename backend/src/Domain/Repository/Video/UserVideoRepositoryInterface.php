<?php

declare(strict_types=1);

namespace App\Domain\Repository\Video;

/**
 * Repository interface for User-Video relationship operations
 *
 * Manages the many-to-many relationship between users and videos
 */
interface UserVideoRepositoryInterface
{
    /**
     * Add video to user's library
     */
    public function add(
        int $userId,
        int $videoId,
        array $statuses = [],
        ?float $personalRating = null,
        ?string $personalNotes = null,
        ?string $watchedAt = null,
        ?int $watchCount = null
    ): void;

    /**
     * Remove video from user's library
     */
    public function remove(int $userId, int $videoId): bool;

    /**
     * Check if user has a specific video
     */
    public function hasVideo(int $userId, int $videoId): bool;

    /**
     * Get all videos for a user with user-specific data
     *
     * @param array $filters Optional filters ['status', 'sortBy', 'sortOrder']
     */
    public function findByUser(int $userId, array $filters = []): array;

    /**
     * Update user-video relationship data (rating, notes, watch_count, etc.)
     */
    public function update(int $userId, int $videoId, array $data): bool;

    /**
     * Update statuses for a user-video relationship (replaces existing)
     */
    public function updateStatuses(int $userId, int $videoId, array $statuses): void;

    /**
     * Update personal rating for a user-video
     */
    public function updateRating(int $userId, int $videoId, float $rating): void;

    /**
     * Update watch count for a user-video
     */
    public function updateWatchCount(int $userId, int $videoId, int $count): void;

    /**
     * Get trending videos (recently added/rated) for the user
     */
    public function findTrending(int $userId, int $limit = 10): array;
}
