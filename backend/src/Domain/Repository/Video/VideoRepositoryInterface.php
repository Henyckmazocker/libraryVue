<?php

declare(strict_types=1);

namespace App\Domain\Repository\Video;

use App\Domain\Model\Video;

/**
 * Repository interface for Video entity CRUD operations
 *
 * Single Responsibility: Only manages Video entity persistence
 */
interface VideoRepositoryInterface
{
    /**
     * Find video by internal ID
     */
    public function findById(int $id): ?Video;

    /**
     * Find video by YouTube ID
     */
    public function findByYouTubeId(string $youtubeId): ?Video;

    /**
     * Find all videos with optional filters
     *
     * @param array $filters Optional filters ['title', 'channel_name', etc.]
     * @return Video[]
     */
    public function findAll(array $filters = []): array;

    /**
     * Save new video to catalog
     *
     * @return Video Video with assigned internal ID
     */
    public function save(Video $video): Video;

    /**
     * Update existing video in catalog
     */
    public function update(Video $video): bool;

    /**
     * Delete video by internal ID
     */
    public function delete(int $id): bool;

    /**
     * Fetch all available statuses from video_statuses table
     */
    public function fetchAllowedStatuses(): array;

    /**
     * Update the catalog-level rating for a video
     */
    public function updateRating(int $id, float $rating): void;
}
