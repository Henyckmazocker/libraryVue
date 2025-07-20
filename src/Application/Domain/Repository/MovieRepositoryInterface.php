<?php

declare(strict_types=1);

namespace App\Application\Domain\Repository;

use App\Application\Domain\Model\Movie;

interface MovieRepositoryInterface
{
    /**
     * @param array $filters Optional filters (e.g., ['userStatus' => 'viewed'])
     * @return array
     */
    public function findAll(array $filters = []): array;

    /**
     * @param string $isbn
     * @return array|null
     */
    public function findById(string $isbn): ?array;

    /**
     * @param array $movie
     * @return void
     */
    public function save(Movie $movie): void;

    /**
     * @param int $id
     * @return bool
     */
    public function deleteById(int $id): bool;

    /**
     * @param string $title
     * @return bool
     */
    public function deleteByName(string $title): bool;

    /**
     * Fetches all allowed movie statuses.
     * @return array
     * This method should be implemented to return an array of status names
     * 
     */
    public function fetchAllowedStatuses(): array;
}
