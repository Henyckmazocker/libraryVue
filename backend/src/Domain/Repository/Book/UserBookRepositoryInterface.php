<?php
declare(strict_types=1);

namespace App\Domain\Repository\Book;

/**
 * Repository interface for User-Book relationship management
 * 
 * Single Responsibility: Manages the many-to-many relationship between users and books
 */
interface UserBookRepositoryInterface
{
    /**
     * Find all books for a user with filters
     *
     * @param int $userId User ID
     * @param array $filters Optional filters ['title' => string, 'status' => string, 'genre' => string]
     * @return array Array of book data with user-specific fields
     */
    public function findByUser(int $userId, array $filters = []): array;

    /**
     * Check if user has a book in their library
     *
     * @param int $userId User ID
     * @param string $bookId Book ISBN
     * @return bool
     */
    public function hasBook(int $userId, string $bookId): bool;

    /**
     * Add book to user's library
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param array $statuses Array of status names to assign
     * @return void
     */
    public function add(int $userId, string $isbn, array $statuses = []): void;

    /**
     * Remove book from user's library
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return bool Success
     */
    public function remove(int $userId, string $isbn): bool;

    /**
     * Update user's book data (rating, notes, current page, consumed date)
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param array $data Data to update ['current_page', 'personal_rating', 'personal_notes', 'consumed_at']
     * @return void
     */
    public function edit(int $userId, string $isbn, array $data): void;

    /**
     * Update user's statuses for a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param array $statuses Array of status names
     * @return void
     */
    public function updateStatuses(int $userId, string $isbn, array $statuses): void;

    /**
     * Update user's rating for a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param float|null $rating User's rating (null to remove)
     * @return void
     */
    public function updateRating(int $userId, string $isbn, ?float $rating): void;

    /**
     * Get user's statuses for a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return array Array of status names
     */
    public function getUserStatuses(int $userId, string $isbn): array;

    /**
     * Get current page user is reading
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return int Current page number
     */
    public function getCurrentPage(int $userId, string $isbn): int;

    /**
     * Count total books for user
     *
     * @param int $userId User ID
     * @return int Total books count
     */
    public function count(int $userId): int;

    /**
     * Count books by status for user
     *
     * @param int $userId User ID
     * @param string $statusName Status name
     * @return int Books count for status
     */
    public function countByStatus(int $userId, string $statusName): int;
}
