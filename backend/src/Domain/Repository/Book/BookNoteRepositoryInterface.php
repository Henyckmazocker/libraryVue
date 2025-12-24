<?php
declare(strict_types=1);

namespace App\Domain\Repository\Book;

/**
 * Repository interface for Book Note management
 * 
 * Single Responsibility: Manages user notes on books
 */
interface BookNoteRepositoryInterface
{
    /**
     * Get notes for a book, grouped by page
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @return array Array of notes grouped by page
     */
    public function getByPage(int $userId, string $isbn): array;

    /**
     * Add a note to a book
     *
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param int $pageNumber Page number
     * @param string $noteText Note content
     * @param string $noteType Note type ('note', 'highlight', 'bookmark')
     * @param bool $isPrivate Privacy flag
     * @return int Created note ID
     */
    public function add(
        int $userId,
        string $isbn,
        int $pageNumber,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): int;

    /**
     * Delete a note
     *
     * @param int $userId User ID (for ownership verification)
     * @param int $noteId Note ID
     * @return bool Success
     */
    public function delete(int $userId, int $noteId): bool;

    /**
     * Update a note
     *
     * @param int $userId User ID (for ownership verification)
     * @param int $noteId Note ID
     * @param string $noteText Updated content
     * @param string $noteType Note type
     * @param bool $isPrivate Privacy flag
     * @return bool Success
     */
    public function update(
        int $userId,
        int $noteId,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): bool;
}
