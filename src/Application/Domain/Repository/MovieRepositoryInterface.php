<?php

declare(strict_types=1);

namespace App\Application\Domain\Repository;

use App\Application\Domain\Model\Movie;

interface MovieRepositoryInterface
{
        /**
     * Actualiza el rating de una película por imdbID o isbn
     * @param string $id Puede ser imdbID o isbn
     * @param float $rating
     * @return void
     */
    public function updateMovieRating(string $id, float $rating): void;
    
    /**
     * Obtiene todas las películas con filtros avanzados (título, estado)
     * @param array $filters ['title' => string|null, 'status' => string|null]
     * @return array
     */
    public function findAllWithFilters(array $filters = []): array;

    public function fetchAllowedStatuses(): array;

    /**
     * @param array $filters Optional filters (e.g., ['userStatus' => 'watched'])
     * @return array
     */
    public function findAll(array $filters = []): array;

    public function save(Movie $movie): void;

    public function deleteByIsbn(string $isbn): bool;

    public function deleteById(int $id): bool;

    public function deleteByName(string $title): bool;

    /**
     * @param string $isbn
     * @return array|null
     */
    public function findById(string $isbn): ?array;

    /**
     * Actualiza los estados de usuario de una película por imdbID
     * @param string $imdbID
     * @param array $statuses
     * @return void
     */
    public function updateUserStatuses(string $imdbID, array $statuses): void;
}
