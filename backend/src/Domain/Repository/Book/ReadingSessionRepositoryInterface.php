<?php
declare(strict_types=1);

namespace App\Domain\Repository\Book;

/**
 * Repository interface for Reading Session management
 * 
 * Single Responsibility: Manages reading sessions tracking
 * Reading sessions track when user starts/stops reading with timestamps
 */
interface ReadingSessionRepositoryInterface
{
    /**
     * Create a new reading session
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param int|null $sessionNumber Session number (auto-generated if null)
     * @param int|null $startPage Starting page
     * @return int Created session ID
     */
    public function create(
        int $userId,
        string $isbn,
        ?int $sessionNumber = null,
        ?int $startPage = null
    ): int;

    /**
     * Get active reading session for a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return array|null Session data or null if no active session
     */
    public function getActive(int $userId, string $isbn): ?array;

    /**
     * Complete a reading session
     *
     * @param int $sessionId Session ID
     * @param int|null $finalPage Final page reached
     * @return void
     */
    public function complete(int $sessionId, ?int $finalPage = null): void;

    /**
     * Pause a reading session
     *
     * @param int $sessionId Session ID
     * @param string|null $reason Reason for pausing
     * @return void
     */
    public function pause(int $sessionId, ?string $reason = null): void;

    /**
     * Resume a paused reading session
     *
     * @param int $sessionId Session ID
     * @return void
     */
    public function resume(int $sessionId): void;

    /**
     * Abandon a reading session
     *
     * @param int $sessionId Session ID
     * @param string|null $reason Reason for abandoning
     * @return void
     */
    public function abandon(int $sessionId, ?string $reason = null): void;

    /**
     * Delete a reading session
     *
     * @param int $sessionId Session ID
     * @param bool $keepHistory Keep progress history entries
     * @return void
     */
    public function delete(int $sessionId, bool $keepHistory = true): void;

    /**
     * Get reading session history for a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return array Array of session data
     */
    public function getHistory(int $userId, string $isbn): array;

    /**
     * Get progress for a specific session
     *
     * @param int $sessionId Session ID
     * @return array Progress data
     */
    public function getProgress(int $sessionId): array;

    /**
     * Get all active reading sessions for user
     *
     * @param int $userId User ID
     * @return array Array of active sessions
     */
    public function getUserActiveSessions(int $userId): array;

    /**
     * Get user reading sessions with optional status filter
     *
     * @param int $userId User ID
     * @param string|null $status Optional status filter ('active', 'completed', 'paused', 'abandoned')
     * @return array Array of sessions
     */
    public function getUserSessions(int $userId, ?string $status = null): array;

    /**
     * Get next session number for a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return int Next session number
     */
    public function getNextSessionNumber(int $userId, string $isbn): int;

    /**
     * Get reading session statistics for user
     *
     * @param int $userId User ID
     * @return array Statistics data
     */
    public function getSessionStats(int $userId): array;

    /**
     * Check if user has completed a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return bool
     */
    public function hasCompletedBook(int $userId, string $isbn): bool;

    /**
     * Get book completion count for user
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return int Number of times book was completed
     */
    public function getBookCompletionCount(int $userId, string $isbn): int;

    /**
     * Update book statuses based on sessions
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return void
     */
    public function updateBookStatusesBasedOnSessions(int $userId, string $isbn): void;
}
