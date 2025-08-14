<?php

declare(strict_types=1);

namespace App\Application\Domain\Repository;

use App\Application\Domain\Model\Book;

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
}