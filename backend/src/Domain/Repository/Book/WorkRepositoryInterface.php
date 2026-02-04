<?php

declare(strict_types=1);

namespace App\Domain\Repository\Book;

use App\Domain\Model\Work;

/**
 * Repository interface for Work entity operations
 */
interface WorkRepositoryInterface
{
    /**
     * Find a work by its OpenLibrary work key
     */
    public function findByOpenLibraryKey(string $workKey): ?Work;

    /**
     * Find a work by its synthetic key (for non-OpenLibrary books)
     */
    public function findBySyntheticKey(string $syntheticKey): ?Work;

    /**
     * Find a work by title and authors (for duplicate detection)
     */
    public function findByTitleAndAuthors(string $title, array $authors): ?Work;

    /**
     * Find a work by its ID
     */
    public function findById(int $workId): ?Work;

    /**
     * Save a new work or update existing one
     */
    public function save(Work $work): Work;

    /**
     * Delete a work (only if no editions exist)
     */
    public function delete(int $workId): bool;

    /**
     * Search works by title or author
     */
    public function search(string $query, int $limit = 20): array;
}
