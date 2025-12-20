<?php
declare(strict_types=1);

namespace App\Domain\Repository\Movie;

/**
 * Repository interface for Movie Tag management
 * 
 * Single Responsibility: Manages user-created tags for movies
 */
interface MovieTagRepositoryInterface
{
    /**
     * Get all tags created by user
     *
     * @param int $userId User ID
     * @return array Array of tags with id, name, color
     */
    public function getByUser(int $userId): array;

    /**
     * Get tags assigned to a movie by user
     *
     * @param int $userId User ID
     * @param string $movieIsbn Movie identifier
     * @return array Array of tags with id, name, color
     */
    public function getByMovie(int $userId, string $movieIsbn): array;

    /**
     * Create new tag for user
     *
     * @param int $userId User ID
     * @param string $name Tag name
     * @param string $color Tag color (hex)
     * @return int Tag ID
     */
    public function create(int $userId, string $name, string $color): int;

    /**
     * Assign tag to movie
     *
     * @param int $userId User ID
     * @param string $movieIsbn Movie identifier
     * @param int $tagId Tag ID
     * @return void
     */
    public function assign(int $userId, string $movieIsbn, int $tagId): void;

    /**
     * Remove all tags from a movie
     *
     * @param int $userId User ID
     * @param string $movieIsbn Movie identifier
     * @return void
     */
    public function removeAll(int $userId, string $movieIsbn): void;

    /**
     * Get allowed tags for user (alias of getByUser)
     *
     * @param int $userId User ID
     * @param string|null $isbn Optional movie identifier (unused, kept for compatibility)
     * @return array Array of tags
     */
    public function getAllowedTags(int $userId, ?string $isbn = null): array;
}
