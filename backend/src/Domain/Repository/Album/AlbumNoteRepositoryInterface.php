<?php

declare(strict_types=1);

namespace App\Domain\Repository\Album;

/**
 * Repository interface for Album Notes management
 *
 * Single Responsibility: Manages user notes for albums
 */
interface AlbumNoteRepositoryInterface
{
    /**
     * Get all notes for an album
     *
     * @param int $userId User ID
     * @param int $albumId Album identifier
     * @return array Array of notes with id, note_text, note_type, is_private, created_at
     */
    public function getByAlbum(int $userId, int $albumId): array;

    /**
     * Add note to album
     *
     * @param int $userId User ID
     * @param int $albumId Album identifier
     * @param string $noteText Note content
     * @param string $noteType Type of note (note, highlight, etc.)
     * @param bool $isPrivate Privacy flag
     * @return int Note ID
     */
    public function add(
        int $userId,
        int $albumId,
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
     * @param string $noteType New note type
     * @param bool $isPrivate New privacy flag
     * @return bool Success
     */
    public function update(
        int $noteId,
        int $userId,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): bool;

    /**
     * Get a single note by ID
     *
     * @param int $noteId Note ID
     * @param int $userId User ID (for security check)
     * @return array|null Note data or null if not found
     */
    public function getById(int $noteId, int $userId): ?array;
}
