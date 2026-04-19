<?php

declare(strict_types=1);

namespace App\Domain\Repository\Book;

use App\Domain\Model\EditionNote;

/**
 * Repository interface for Edition Note management
 * 
 * Single Responsibility: Manages user notes on book editions
 */
interface EditionNoteRepositoryInterface
{
    /**
     * Get all notes for a user edition
     *
     * @param int $userId User ID
     * @param int $userEditionId User edition ID
     * @param string|null $noteType Optional filter by note type
     * @param int|null $pageNumber Optional filter by page number
     * @return EditionNote[] Array of edition notes
     */
    public function findByUserEdition(
        int $userId,
        int $userEditionId,
        ?string $noteType = null,
        ?int $pageNumber = null
    ): array;

    /**
     * Get a single note by ID
     *
     * @param int $noteId Note ID
     * @param int $userId User ID (for ownership verification)
     * @return EditionNote|null
     */
    public function findById(int $noteId, int $userId): ?EditionNote;

    /**
     * Add a note to a book edition
     *
     * @param EditionNote $note Edition note entity
     * @return EditionNote The created note with ID
     */
    public function add(EditionNote $note): EditionNote;

    /**
     * Update an existing note
     *
     * @param EditionNote $note Edition note entity with updated data
     * @return EditionNote The updated note
     */
    public function update(EditionNote $note): EditionNote;

    /**
     * Delete a note
     *
     * @param int $noteId Note ID
     * @param int $userId User ID (for ownership verification)
     * @return bool Success
     */
    public function delete(int $noteId, int $userId): bool;

    /**
     * Delete all notes for a user edition
     *
     * @param int $userEditionId User edition ID
     * @return bool Success
     */
    public function deleteAllByUserEdition(int $userEditionId): bool;

    /**
     * Count notes for a user edition
     *
     * @param int $userEditionId User edition ID
     * @return int Number of notes
     */
    public function countByUserEdition(int $userEditionId): int;
}
