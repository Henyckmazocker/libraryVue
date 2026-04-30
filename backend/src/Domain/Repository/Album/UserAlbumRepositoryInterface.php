<?php

declare(strict_types=1);

namespace App\Domain\Repository\Album;

/**
 * Repository interface for User-Album relationship operations
 *
 * Manages the many-to-many relationship between users and albums
 */
interface UserAlbumRepositoryInterface
{
    /**
     * Add album to user's library
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @param array $statuses Initial statuses
     * @param float|null $personalRating User's rating (0.5-5.0)
     * @param string|null $personalNotes User's notes
     * @param string|null $completedAt Date when album was completed
     * @param int|null $listenCount Number of times listened
     * @param string|null $favoriteTrack User's favourite track name
     * @return void
     */
    public function add(
        int $userId,
        int $albumId,
        array $statuses = [],
        ?float $personalRating = null,
        ?string $personalNotes = null,
        ?string $completedAt = null,
        ?int $listenCount = null,
        ?string $favoriteTrack = null
    ): void;

    /**
     * Remove album from user's library
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @return bool Success
     */
    public function remove(int $userId, int $albumId): bool;

    /**
     * Check if user has a specific album
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @return bool
     */
    public function hasAlbum(int $userId, int $albumId): bool;

    /**
     * Get all albums for a user
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @return array Albums with user-specific data
     */
    public function findByUser(int $userId, array $filters = []): array;

    /**
     * Update user-album relationship data
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @param array $data Fields to update
     * @return bool Success
     */
    public function update(int $userId, int $albumId, array $data): bool;

    /**
     * Update statuses for a user-album relationship
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @param array $statuses New statuses to set (replaces existing)
     * @return void
     */
    public function updateStatuses(int $userId, int $albumId, array $statuses): void;

    /**
     * Update user's personal rating for an album
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @param float $rating New rating value
     * @return void
     */
    public function updateRating(int $userId, int $albumId, float $rating): void;

    /**
     * Get current statuses for a user-album relationship
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @return array Array of status names
     */
    public function getUserStatuses(int $userId, int $albumId): array;

    /**
     * Count albums in user's library
     *
     * @param int $userId User ID
     * @return int
     */
    public function countByUser(int $userId): int;

    /**
     * Get trending albums (most added recently)
     *
     * @param int $limit Maximum number of results
     * @param int $daysWindow Days to look back for trending calculation
     * @param int|null $userId User ID to check ownership (adds isOwned flag)
     * @return array Albums with trending metadata
     */
    public function getTrendingAlbums(int $limit, int $daysWindow, ?int $userId): array;
}
