<?php
declare(strict_types=1);

namespace App\Domain\UseCases;

use App\Domain\UseCases\Books\GetBooksUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetLibraryItemsQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetLibraryItemsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly GetBooksUseCase $getBooksUseCase,
        private readonly GetMoviesUseCase $getMoviesUseCase,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with GetLibraryItemsQuery
     * Returns unified list of books + movies with filters applied
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof GetLibraryItemsQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetLibraryItemsQuery');
        }

        // Get filtered data from both use cases
        $filters = $command->toFiltersArray();
        $books = $this->getBooksUseCase->execute($filters);
        $movies = $this->getMoviesUseCase->execute($filters);

        // Add type identifier to each element
        $books = array_map(fn($item) => [...$item, 'itemType' => 'book'], $books);
        $movies = array_map(fn($item) => [...$item, 'itemType' => 'movie'], $movies);

        // Merge and sort
        $all = array_merge($books, $movies);
        
        // Sort by configured field
        $sortBy = $command->sortBy ?? 'title';
        $sortOrder = $command->sortOrder ?? 'asc';
        
        usort($all, function($a, $b) use ($sortBy, $sortOrder) {
            $valueA = strtolower($a[$sortBy] ?? '');
            $valueB = strtolower($b[$sortBy] ?? '');
            
            $comparison = strcmp($valueA, $valueB);
            return $sortOrder === 'desc' ? -$comparison : $comparison;
        });

        return $all;
    }

    /**
     * Get log context for this use case
     */
    protected function getLogContext(): string
    {
        return 'GetLibraryItemsUseCase';
    }
}
