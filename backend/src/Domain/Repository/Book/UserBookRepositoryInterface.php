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
     * Check if user has a book in their library
     *
     * @param int $userId User ID
     * @param string $bookId Book ISBN
     * @return bool
     */
    public function hasBook(int $userId, string $bookId): bool;

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
     * Get user's statuses for a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return array Array of status names
     */
    public function getUserStatuses(int $userId, string $isbn): array;

    /**
     * Count books by status for user
     *
     * @param int $userId User ID
     * @param string $statusName Status name
     * @return int Books count for status
     */
    public function countByStatus(int $userId, string $statusName): int;

    /**
     * Get trending books based on user activity and ratings
     * 
     * @param int $limit Maximum number of results
     * @param int $daysWindow Time window in days to consider for trending
     * @return array Array of book data with trending scores
     */
    public function getTrendingBooks(int $limit = 20, int $daysWindow = 90, ?int $userId = null): array;
}
