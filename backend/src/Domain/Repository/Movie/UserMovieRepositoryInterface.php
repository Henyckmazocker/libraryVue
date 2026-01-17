<?php
declare(strict_types=1);

namespace App\Domain\Repository\Movie;

/**
 * Repository interface for User-Movie relationship management
 * 
 * Single Responsibility: Manages the many-to-many relationship between users and movies
 */
interface UserMovieRepositoryInterface
{
    /**
     * Find all movies for a user with filters
     *
     * @param int $userId User ID
     * @param array $filters Optional filters ['title' => string, 'status' => string, 'genre' => string]
     * @return array Array of movie data with user-specific fields
     */
    public function findByUser(int $userId, array $filters = []): array;

    /**
     * Check if user has a movie in their library
     *
     * @param int $userId User ID
     * @param string $movieId Movie identifier
     * @return bool
     */
    public function hasMovie(int $userId, string $movieId): bool;

    /**
     * Add movie to user's library
     *
     * @param int $userId User ID
     * @param string $movieIsbn Movie identifier
     * @param array $statuses User's statuses for the movie
     * @param float|null $personalRating User's rating
     * @param string|null $personalNotes User's notes
     * @param string|null $consumedAt Date when consumed
     * @return void
     */
    public function add(
        int $userId,
        string $movieIsbn,
        array $statuses = [],
        ?float $personalRating = null,
        ?string $personalNotes = null,
        ?string $consumedAt = null
    ): void;

    /**
     * Remove movie from user's library
     *
     * @param int $userId User ID
     * @param string $movieId Movie identifier
     * @return bool Success
     */
    public function remove(int $userId, string $movieId): bool;

    /**
     * Update user's movie data (rating, notes, consumed date)
     *
     * @param int $userId User ID
     * @param string $movieIsbn Movie identifier
     * @param array $data Array containing optional keys: personal_rating, personal_notes, consumed_at
     * @return void
     */
    public function edit(int $userId, string $movieIsbn, array $data): void;

    /**
     * Update user's statuses for a movie
     *
     * @param int $userId User ID
     * @param string $movieId Movie identifier
     * @param array $statuses Array of status names
     * @return void
     */
    public function updateStatuses(int $userId, string $movieId, array $statuses): void;

    /**
     * Update user's rating for a movie
     *
     * @param int $userId User ID
     * @param string $movieId Movie identifier
     * @param float|null $rating New rating value
     * @return void
     */
    public function updateRating(int $userId, string $movieId, ?float $rating): void;

    /**
     * Get user's statuses for a movie
     *
     * @param int $userId User ID
     * @param string $movieId Movie identifier
     * @return array Array of status names
     */
    public function getUserStatuses(int $userId, string $movieId): array;

    /**
     * Count total movies for user
     *
     * @param int $userId User ID
     * @return int
     */
    public function count(int $userId): int;

    /**
     * Count movies by status for user
     *
     * @param int $userId User ID
     * @return array Array with status => count pairs
     */
    public function countByStatus(int $userId): array;
}
