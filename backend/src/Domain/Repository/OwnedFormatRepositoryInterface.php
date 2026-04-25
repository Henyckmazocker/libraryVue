<?php

declare(strict_types=1);

namespace App\Domain\Repository;

/**
 * Repository for looking up item_owned_formats lookup table
 */
interface OwnedFormatRepositoryInterface
{
    /**
     * Find all active formats for a given entity type.
     *
     * @param string $entityType  One of: 'book', 'movie', 'game', 'album'
     * @return array<int, array{id: int, value: string, label: string, sort_order: int}>
     */
    public function findByEntityType(string $entityType): array;

    /**
     * Find a format by entity type and value string.
     *
     * @param string $entityType  One of: 'book', 'movie', 'game', 'album'
     * @param string $value       Format value (e.g. 'physical_book')
     * @return array{id: int, value: string, label: string}|null
     */
    public function findByEntityTypeAndValue(string $entityType, string $value): ?array;

    /**
     * Find a format by its ID.
     *
     * @param int $id
     * @return array{id: int, entity_type: string, value: string, label: string}|null
     */
    public function findById(int $id): ?array;
}
