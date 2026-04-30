<?php

declare(strict_types=1);

namespace App\Domain\Repository\Album;

/**
 * Repository interface for User Album Tags operations
 *
 * Manages custom tags for albums per user
 */
interface AlbumTagRepositoryInterface
{
    /**
     * Get all tags for a user
     *
     * @param int $userId User ID
     * @return array Tags with id, name, color
     */
    public function findByUser(int $userId): array;

    /**
     * Create a new tag for user
     *
     * @param int $userId User ID
     * @param string $name Tag name
     * @param string $color Tag color (hex)
     * @return int New tag ID
     */
    public function create(int $userId, string $name, string $color = '#007bff'): int;

    /**
     * Delete a tag
     *
     * @param int $userId User ID
     * @param int $tagId Tag ID
     * @return bool Success
     */
    public function delete(int $userId, int $tagId): bool;

    /**
     * Assign tag to album
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @param int $tagId Tag ID
     * @return void
     */
    public function assignToAlbum(int $userId, int $albumId, int $tagId): void;

    /**
     * Remove tag from album
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @param int $tagId Tag ID
     * @return void
     */
    public function removeFromAlbum(int $userId, int $albumId, int $tagId): void;

    /**
     * Remove all tags from a specific album
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @return void
     */
    public function removeAllFromAlbum(int $userId, int $albumId): void;

    /**
     * Get all tags for a specific album
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @return array Tag IDs
     */
    public function getAlbumTags(int $userId, int $albumId): array;

    /**
     * Get albums with a specific tag
     *
     * @param int $userId User ID
     * @param int $tagId Tag ID
     * @return array Album IDs
     */
    public function getAlbumsByTag(int $userId, int $tagId): array;

    /**
     * Sync tags for an album (replace all existing assignments)
     *
     * @param int $userId User ID
     * @param int $albumId Album ID
     * @param array $tagIds New tag IDs to assign
     * @return void
     */
    public function syncAlbumTags(int $userId, int $albumId, array $tagIds): void;
}
