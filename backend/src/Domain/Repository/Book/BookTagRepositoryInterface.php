<?php
declare(strict_types=1);

namespace App\Domain\Repository\Book;

/**
 * Repository interface for Book Tag management
 * 
 * Single Responsibility: Manages user-defined tags for books
 */
interface BookTagRepositoryInterface
{
    /**
     * Get all tags for a user
     *
     * @param int $userId User ID
     * @return array Array of tags ['id' => int, 'name' => string, 'color' => string]
     */
    public function getByUser(int $userId): array;

    /**
     * Get tags assigned to a specific book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return array Array of tags
     */
    public function getByBook(int $userId, string $isbn): array;

    /**
     * Create a new tag for user
     *
     * @param int $userId User ID
     * @param string $name Tag name
     * @param string $color Tag color (hex code)
     * @return int Created tag ID
     */
    public function create(int $userId, string $name, string $color = '#007bff'): int;

    /**
     * Assign tag to a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param int $tagId Tag ID
     * @return void
     */
    public function assign(int $userId, string $isbn, int $tagId): void;

    /**
     * Remove all tags from a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return void
     */
    public function removeAll(int $userId, string $isbn): void;

    /**
     * Get allowed tags for user (alias for getByUser)
     *
     * @param int $userId User ID
     * @return array Array of tags
     */
    public function getAllowedTags(int $userId): array;
}
