<?php

declare(strict_types=1);

namespace App\Domain\Repository\Album;

use App\Domain\Model\Album;

/**
 * Repository interface for Album entity CRUD operations
 *
 * Single Responsibility: Only manages Album entity persistence
 */
interface AlbumRepositoryInterface
{
    /**
     * Find album by internal ID
     *
     * @param int $id Internal album identifier
     * @return Album|null
     */
    public function findById(int $id): ?Album;

    /**
     * Find album by Spotify ID
     *
     * @param string $spotifyId Spotify base62 identifier
     * @return Album|null
     */
    public function findBySpotifyId(string $spotifyId): ?Album;

    /**
     * Find all albums with optional filters
     *
     * @param array $filters Optional filters ['title' => string, 'artist' => string, 'genre' => string, etc.]
     * @return Album[]
     */
    public function findAll(array $filters = []): array;

    /**
     * Save new album
     *
     * @param Album $album
     * @return Album Album with assigned ID
     */
    public function save(Album $album): Album;

    /**
     * Update existing album
     *
     * @param Album $album
     * @return bool Success
     */
    public function update(Album $album): bool;

    /**
     * Delete album by ID
     *
     * @param int $id Internal album identifier
     * @return bool Success
     */
    public function delete(int $id): bool;

    /**
     * Get allowed status names from database
     *
     * @return string[]
     */
    public function fetchAllowedStatuses(): array;

    /**
     * Update album rating
     *
     * @param int $id Internal album identifier
     * @param float $rating New rating value
     * @return void
     */
    public function updateRating(int $id, float $rating): void;

    /**
     * Check if album exists in database by internal ID
     *
     * @param int $id Internal album identifier
     * @return bool
     */
    public function exists(int $id): bool;

    /**
     * Check if album exists in database by Spotify ID
     *
     * @param string $spotifyId Spotify identifier
     * @return bool
     */
    public function existsBySpotifyId(string $spotifyId): bool;
}
