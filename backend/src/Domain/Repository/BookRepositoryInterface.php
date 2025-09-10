<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Book;

interface BookRepositoryInterface
{
    public function fetchAllowedStatuses(): array;

    /**
     * @param array $filters Optional filters (e.g., ['userStatus' => 'read'])
     * @return Book[]
     */
    public function findAll(array $filters = []): array;

    public function findById(string $isbn): ?Book;

    /**
     * Finds books by a specific user status.
     * @param string $statusName The user status to filter by.
     * @return Book[]
     */
    public function findByUserStatus(string $statusName): array;

    public function save(Book $book): void;

    public function deleteByIsbn(string $isbn): bool;

    // User-related methods
    public function addBookToUser(int $userId, string $isbn, array $statuses = []): void;
    
    public function removeBookFromUser(int $userId, string $isbn): bool;
    
    public function findBooksByUser(int $userId, array $filters = []): array;
    
    public function updateUserBookStatuses(int $userId, string $isbn, array $statuses, bool $manageTransaction = true): void;
    
    public function updateUserBookRating(int $userId, string $isbn, ?float $rating): void;
    
    public function getUserBookStatuses(int $userId, string $isbn): array;

    public function editUserBook(int $userId, string $isbn, ?int $currentPage = null, ?float $personalRating = null, ?string $personalNotes = null, ?string $consumedAt = null): void;

    public function addUserBookNote(int $userId, string $isbn, int $pageNumber, string $noteText, string $noteType = 'note', bool $isPrivate = true): int;

    public function addUserBookTag(int $userId, string $name, string $color = '#007bff'): int;

    public function assignUserBookTag(int $userId, string $isbn, int $tagId): void;

    public function removeAllUserBookTags(int $userId, string $isbn): void;

    /**
     * Obtiene los tags asignados a un libro específico de un usuario.
     * Devuelve un array de tags (id, name, color).
     */
    public function getBookTags(int $userId, string $isbn): array;

    /**
     * Obtiene todos los tags creados por el usuario.
     * Devuelve un array de tags (id, name, color).
     */
    public function getUserBookTags(int $userId): array;

    /**
     * Obtiene las notas de un libro por página para un usuario.
     * Devuelve un array de notas (id, page_number, note_text, note_type, is_private, created_at).
     */
    public function getBookNotesByPage(int $userId, string $isbn): array;
}