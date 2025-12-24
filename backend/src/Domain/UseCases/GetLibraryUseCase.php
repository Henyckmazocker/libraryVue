<?php

declare(strict_types=1);

namespace App\Domain\UseCases;

use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\DTO\Queries\GetLibraryQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetLibraryUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository,
        private readonly MovieRepositoryInterface $movieRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with GetLibraryQuery
     * Returns combined books and movies or filtered by type
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof GetLibraryQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetLibraryQuery');
        }

        $result = [];
        $filters = $command->toFilters();

        // Get books if no itemType filter or itemType is 'book'
        if ($command->itemType === null || $command->itemType === 'book') {
            $books = $this->bookRepository->findAll($filters);
            foreach ($books as $book) {
                $bookArray = $book->toArray();
                $bookArray['itemType'] = 'book';
                $result[] = $bookArray;
            }
        }

        // Get movies if no itemType filter or itemType is 'movie'
        if ($command->itemType === null || $command->itemType === 'movie') {
            $movies = $this->movieRepository->findAll($filters);
            foreach ($movies as $movie) {
                $movieArray = $movie->toArray();
                $movieArray['itemType'] = 'movie';
                $result[] = $movieArray;
            }
        }

        // Sort if needed
        if ($command->sortBy) {
            usort($result, function($a, $b) use ($command) {
                $aValue = $a[$command->sortBy] ?? null;
                $bValue = $b[$command->sortBy] ?? null;
                
                if ($command->sortOrder === 'desc') {
                    return $bValue <=> $aValue;
                }
                return $aValue <=> $bValue;
            });
        }

        return $result;
    }

    protected function getLogContext(): string
    {
        return 'GetLibraryUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Library items retrieved successfully';
    }
} 