<?php
declare(strict_types=1);

namespace App\Domain\Repository\Book;

use App\Domain\Model\Book;

/**
 * Repository interface for Book entity CRUD operations
 * 
 * Single Responsibility: Manages Book entities only
 */
interface BookRepositoryInterface
{
    /**
     * Find book by ISBN
     *
     * @param string $isbn Book ISBN
     * @return Book|null Book entity or null if not found
     */
    public function findById(string $isbn): ?Book;

    /**
     * Find all books with optional filters
     *
     * @param array $filters Optional filters ['userStatus' => string]
     * @return array Array of Book entities
     */
    public function findAll(array $filters = []): array;

    /**
     * Find books by user status
     *
     * @param string $statusName Status name
     * @return array Array of Book entities
     */
    public function findByUserStatus(string $statusName): array;

    /**
     * Save new book
     *
     * @param Book $book Book entity to save
     * @return void
     */
    public function save(Book $book): void;

    /**
     * Update existing book
     *
     * @param Book $book Book entity with updated data
     * @return void
     */
    public function update(Book $book): void;

    /**
     * Delete book by ISBN
     *
     * @param string $isbn Book ISBN
     * @return bool Success
     */
    public function delete(string $isbn): bool;

    /**
     * Get allowed book statuses
     *
     * @return array Array of status names
     */
    public function fetchAllowedStatuses(): array;

    /**
     * Update book rating
     *
     * @param string $isbn Book ISBN
     * @param float $rating New rating
     * @return void
     */
    public function updateRating(string $isbn, float $rating): void;

    /**
     * Get total pages for a book
     *
     * @param string $isbn Book ISBN
     * @return int Total pages
     */
    public function getTotalPages(string $isbn): int;
}
