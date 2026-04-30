<?php
declare(strict_types=1);

namespace App\Domain\Repository\Game;

/**
 * Repository interface for Game Notes management
 * 
 * Single Responsibility: Manages user notes for games
 */
interface GameNoteRepositoryInterface
{
    /**
     * Get all notes for a game
     *
     * @param int $userId User ID
     * @param int $gameId Game identifier
     * @return array Array of notes with id, note_text, note_type, is_private, created_at
     */
    public function getByGame(int $userId, int $gameId): array;

    /**
     * Add note to game
     *
     * @param int $userId User ID
     * @param int $gameId Game identifier
     * @param string $noteText Note content
     * @param string $noteType Type of note (note, highlight, etc.)
     * @param bool $isPrivate Privacy flag
     * @return int Note ID
     */
    public function add(
        int $userId,
        int $gameId,
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
