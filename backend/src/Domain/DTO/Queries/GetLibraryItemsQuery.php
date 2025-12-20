<?php
declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query to get unified library items (books + movies) with filters
 * Returns both books and movies in a single list
 */
final readonly class GetLibraryItemsQuery
{
    public function __construct(
        public ?string $title = null,
        public ?string $status = null,
        public ?string $sortBy = 'title',
        public ?string $sortOrder = 'asc'
    ) {}

    /**
     * Create from associative array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            status: $data['status'] ?? null,
            sortBy: $data['sortBy'] ?? 'title',
            sortOrder: $data['sortOrder'] ?? 'asc'
        );
    }

    /**
     * Build filters array for child Use Cases
     */
    public function toFiltersArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'status' => $this->status,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder
        ], fn($value) => $value !== null);
    }
}
