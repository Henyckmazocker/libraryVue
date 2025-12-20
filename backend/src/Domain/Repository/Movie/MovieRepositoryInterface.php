<?php
declare(strict_types=1);

namespace App\Domain\Repository\Movie;

use App\Domain\Model\Movie;

/**
 * Repository interface for Movie entity CRUD operations
 * 
 * Single Responsibility: Only manages Movie entity persistence
 */
interface MovieRepositoryInterface
{
    /**
     * Find movie by ID (ISBN/IMDB ID)
     *
     * @param string $id Movie identifier
     * @return Movie|null
     */
    public function findById(string $id): ?Movie;

    /**
     * Find all movies with optional filters
     *
     * @param array $filters Optional filters ['title' => string, 'genre' => string, etc.]
     * @return Movie[]
     */
    public function findAll(array $filters = []): array;

    /**
     * Save new movie
     *
     * @param Movie $movie
     * @return Movie Movie with assigned ID
     */
    public function save(Movie $movie): Movie;

    /**
     * Update existing movie
     *
     * @param Movie $movie
     * @return bool Success
     */
    public function update(Movie $movie): bool;

    /**
     * Delete movie by ID
     *
     * @param string $id Movie identifier
     * @return bool Success
     */
    public function delete(string $id): bool;

    /**
     * Get allowed status names from database
     *
     * @return string[]
     */
    public function fetchAllowedStatuses(): array;

    /**
     * Update movie rating
     *
     * @param string $id Movie identifier
     * @param float $rating New rating value
     * @return void
     */
    public function updateRating(string $id, float $rating): void;
}
