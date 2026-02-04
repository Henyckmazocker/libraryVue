<?php

declare(strict_types=1);

namespace App\Domain\Repository\Book;

use App\Domain\Model\Edition;

/**
 * Repository interface for Edition entity operations
 */
interface EditionRepositoryInterface
{
    /**
     * Find an edition by its OpenLibrary edition key
     */
    public function findByOpenLibraryKey(string $editionKey): ?Edition;

    /**
     * Find an edition by ISBN-13
     */
    public function findByIsbn13(string $isbn13): ?Edition;

    /**
     * Find an edition by ISBN-10
     */
    public function findByIsbn10(string $isbn10): ?Edition;

    /**
     * Find an edition by any ISBN (13 or 10)
     */
    public function findByIsbn(string $isbn): ?Edition;

    /**
     * Find an edition by its ID
     */
    public function findById(int $editionId): ?Edition;

    /**
     * Find all editions for a given work
     */
    public function findByWorkId(int $workId): array;

    /**
     * Get the first edition for a work (for legacy compatibility)
     * Orders by publish_year ASC, then edition_id ASC
     */
    public function getFirstEditionForWork(int $workId): ?Edition;

    /**
     * Save a new edition or update existing one
     */
    public function save(Edition $edition): Edition;

    /**
     * Delete an edition
     */
    public function delete(int $editionId): bool;
}
