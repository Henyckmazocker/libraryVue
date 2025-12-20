<?php
declare(strict_types=1);

namespace App\Domain\DTO\Queries;

/**
 * Query to get all books from catalog
 * No filters - returns complete catalog
 */
final readonly class GetAllBooksQuery
{
    public function __construct() {}

    /**
     * Create from associative array
     */
    public static function fromArray(array $data): self
    {
        return new self();
    }

    /**
     * Static factory for clean instantiation
     */
    public static function create(): self
    {
        return new self();
    }
}
