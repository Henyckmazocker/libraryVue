<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query DTO for getting library items (books and movies combined)
 */
final readonly class GetLibraryQuery
{
    public array $filters;

    public function __construct(
        public int $userId,
        public ?string $itemType = null, // 'book', 'movie', or null for all
        public ?string $status = null,
        public ?string $sortBy = null,
        public ?string $sortOrder = 'desc'
    ) {
        if ($itemType !== null && !in_array($itemType, ['book', 'movie'])) {
            throw new \InvalidArgumentException("Item type must be 'book', 'movie', or null");
        }
        
        $this->filters = $this->buildFilters();
    }

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            itemType: $data['itemType'] ?? null,
            status: $data['status'] ?? null,
            sortBy: $data['sortBy'] ?? null,
            sortOrder: $data['sortOrder'] ?? 'desc'
        );
    }

    private function buildFilters(): array
    {
        $filters = [];
        
        if ($this->itemType !== null) {
            $filters['itemType'] = $this->itemType;
        }
        
        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }
        
        if ($this->sortBy !== null) {
            $filters['sortBy'] = $this->sortBy;
            $filters['sortOrder'] = $this->sortOrder;
        }
        
        return $filters;
    }

    public function toFilters(): array
    {
        return $this->filters;
    }
}
