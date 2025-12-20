<?php
declare(strict_types=1);

namespace App\Domain\Repository\Movie;

/**
 * Repository interface for Movie Notes management
 * 
 * Single Responsibility: Manages user notes for movies
 */
interface MovieNoteRepositoryInterface
{
    /**
     * Get all notes for a movie by page
     *
     * @param int $userId User ID
     * @param string $movieIsbn Movie identifier
     * @return array Array of notes with id, page_number, note_text, note_type, is_private, created_at
     */
    public function getByPage(int $userId, string $movieIsbn): array;

    /**
     * Add note to movie
     *
     * @param int $userId User ID
     * @param string $movieIsbn Movie identifier
     * @param string $noteText Note content
     * @param string $noteType Type of note (note, highlight, etc.)
     * @param bool $isPrivate Privacy flag
     * @return int Note ID
     */
    public function add(
        int $userId,
        string $movieIsbn,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): int;

    /**
     * Delete note
     *
     * @param int $noteId Note ID
     * @param int $userId User ID (for security check)
     * @return bool Success
     */
    public function delete(int $noteId, int $userId): bool;

    /**
     * Update note
     *
     * @param int $noteId Note ID
     * @param int $userId User ID (for security check)
     * @param string $noteText New note content
     * @param string|null $noteType New note type
     * @param bool|null $isPrivate New privacy flag
     * @return bool Success
     */
    public function update(
        int $noteId,
        int $userId,
        string $noteText,
        ?string $noteType = null,
        ?bool $isPrivate = null
    ): bool;
}
