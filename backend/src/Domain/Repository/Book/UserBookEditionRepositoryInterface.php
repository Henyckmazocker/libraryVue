<?php

declare(strict_types=1);

namespace App\Domain\Repository\Book;

use App\Domain\Model\UserBookEdition;

/**
 * Repository interface for UserBookEdition entity operations
 */
interface UserBookEditionRepositoryInterface
{
    /**
     * Find a user's library entry for a specific edition
     */
    public function findByUserAndEdition(int $userId, int $editionId): ?UserBookEdition;

    /**
     * Find a user's library entry by its ID
     */
    public function findById(int $id): ?UserBookEdition;

    /**
     * Find all editions in a user's library
     * 
     * @param int $userId The user ID
     * @param array $filters Optional filters (status, title, genre, etc.)
     * @return UserBookEdition[]
     */
    public function findByUser(int $userId, array $filters = []): array;

    /**
     * Check if a user has a specific edition in their library
     */
    public function hasEdition(int $userId, int $editionId): bool;

    /**
     * Add an edition to a user's library
     */
    public function add(int $userId, int $editionId, array $statuses = [], ?int $ownershipFormatId = null): UserBookEdition;

    /**
     * Save (insert or update) a user book edition
     */
    public function save(UserBookEdition $userBookEdition): UserBookEdition;

    /**
     * Update user's rating for a book
     */
    public function updateRating(int $userId, int $editionId, ?float $workRating, ?float $editionRating = null): void;

    /**
     * Update user's reading progress
     */
    public function updateProgress(int $userId, int $editionId, int $currentPage): void;

    /**
     * Update user's statuses for a book
     */
    public function updateStatuses(int $userId, int $editionId, array $statuses): void;
    
    /**
     * Get user's statuses for a specific edition
     * 
     * @return string[] Array of status names
     */
    public function getStatusesForEdition(int $userId, int $editionId): array;

    /**
     * Remove an edition from a user's library
     */
    public function remove(int $userId, int $editionId): bool;

    /**
     * Delete a user book edition by its ID
     */
    public function delete(int $id): bool;
}
