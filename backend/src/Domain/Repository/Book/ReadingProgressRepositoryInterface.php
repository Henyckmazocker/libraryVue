<?php
declare(strict_types=1);

namespace App\Domain\Repository\Book;

/**
 * Repository interface for Reading Progress management
 * 
 * Single Responsibility: Tracks page progress and statistics
 */
interface ReadingProgressRepositoryInterface
{
    /**
     * Update reading progress with session tracking
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param int $currentPage Current page number
     * @param string $progressType Progress type ('advance', 'rewind', 'jump')
     * @param string|null $notes Optional notes
     * @return void
     */
    public function updateWithSession(
        int $userId,
        string $isbn,
        int $currentPage,
        string $progressType = 'advance',
        ?string $notes = null
    ): void;

    /**
     * Add entry to progress history
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param int $currentPage Current page
     * @param int $previousPage Previous page
     * @param int|null $sessionId Associated session ID
     * @return void
     */
    public function addHistory(
        int $userId,
        string $isbn,
        int $currentPage,
        int $previousPage,
        ?int $sessionId = null
    ): void;

    /**
     * Get reading progress history
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return array Array of progress history entries
     */
    public function getHistory(int $userId, string $isbn): array;

    /**
     * Get monthly pages read statistics
     *
     * @param int $userId User ID
     * @param int $months Number of months to retrieve
     * @return array Monthly statistics
     */
    public function getMonthlyStats(int $userId, int $months = 12): array;

    /**
     * Get last progress page for a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return int Last recorded page
     */
    public function getLastProgressPage(int $userId, string $isbn): int;

    /**
     * Get book reading summary
     *
     * @param int $userId User ID
     * @param int $bookId Book ID (Note: may need to convert from ISBN)
     * @return array Reading summary data
     */
    public function getBookReadingSummary(int $userId, int $bookId): array;

    /**
     * Get detailed progress history
     *
     * @param int $userId User ID
     * @param int $bookId Book ID
     * @return array Detailed progress data
     */
    public function getDetailedProgressHistory(int $userId, int $bookId): array;

    /**
     * Get user reading statistics
     *
     * @param int $userId User ID
     * @return array Overall reading statistics
     */
    public function getUserReadingStats(int $userId): array;

    /**
     * Get current reading sessions with progress
     *
     * @param int $userId User ID
     * @return array Current sessions with progress data
     */
    public function getCurrentReadingSessions(int $userId): array;
}
