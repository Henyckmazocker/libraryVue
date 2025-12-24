<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query DTO for getting books by user
 */
final readonly class GetBooksByUserQuery
{
    public array $filters;

    public function __construct(
        public int $userId,
        public ?string $status = null,
        public ?string $sortBy = null,
        public ?string $sortOrder = 'asc'
    ) {
        $this->filters = $this->buildFilters();
    }

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            status: $data['status'] ?? null,
            sortBy: $data['sortBy'] ?? null,
            sortOrder: $data['sortOrder'] ?? 'asc'
        );
    }

    private function buildFilters(): array
    {
        $filters = [];
        
        if ($this->status !== null) {
            $filters['userStatus'] = $this->status;
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
