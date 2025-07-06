<?php

declare(strict_types=1);

namespace App\Application\Domain\Repository;

use App\Application\Domain\Model\Book;

interface BookRepositoryInterface
{
    /**
     * @param array $filters Optional filters (e.g., ['userStatus' => 'read'])
     * @return Book[]
     */
    public function findAll(array $filters = []): array;

    public function findByIsbn(string $isbn): ?Book;

    /**
     * Finds books by a specific user status.
     * @param string $status The user status to filter by.
     * @return Book[]
     */
    public function findByUserStatus(string $status): array;

    public function save(Book $book): void;

    public function deleteByIsbn(string $isbn): bool;
} 