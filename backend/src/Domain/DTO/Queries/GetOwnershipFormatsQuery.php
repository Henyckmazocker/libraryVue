<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query to retrieve available ownership formats for a given entity type.
 */
final readonly class GetOwnershipFormatsQuery
{
    /**
     * @param string $entityType  One of: 'book', 'movie', 'game', 'album'
     */
    public function __construct(public string $entityType) {}

    public static function fromArray(array $data): self
    {
        return new self(entityType: (string) ($data['entityType'] ?? $data['entity_type'] ?? ''));
    }
}
