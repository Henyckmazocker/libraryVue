<?php

declare(strict_types=1);

namespace App\Domain\Repository\Video;

/**
 * Repository interface for Video Notes management
 *
 * Single Responsibility: Manages user notes for videos
 */
interface VideoNoteRepositoryInterface
{
    /**
     * Get all notes for a video
     *
     * @return array Array of notes with id, note_text, note_type, is_private, created_at
     */
    public function getByVideo(int $userId, int $videoId): array;

    /**
     * Add a note to a video
     *
     * @return int Note ID
     */
    public function add(
        int $userId,
        int $videoId,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): int;

    /**
     * Delete a note (with ownership check)
     */
    public function delete(int $noteId, int $userId): bool;

    /**
     * Update a note (with ownership check)
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
     */
    public function findById(int $noteId, int $userId): ?array;
}
