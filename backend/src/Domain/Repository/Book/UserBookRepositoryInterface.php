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
     * Update user's book data (rating, current page, ownership format)
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param array $data Data to update ['current_page', 'personal_rating', 'ownership_format_id']
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
     * Get the last known page the user reached in a book
     *
     * La página vive en `user_book_editions.current_page`, y ningún método de
     * esta interfaz la exponía. La necesita el cierre de sesión desde el cambio
     * de estado: `complete()` escribe `$finalPage ?? 0`, así que sin la página
     * el historial pintaría una sesión de cero páginas.
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return int|null Current page, or null if the book is not in the library
     */
    public function getCurrentPage(int $userId, string $isbn): ?int;

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
