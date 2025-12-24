<?php
declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetAllBooksQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetAllBooksUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with GetAllBooksQuery
     * Get all books from the catalog
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof GetAllBooksQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetAllBooksQuery');
        }

        return $this->bookRepository->findAll();
    }

    /**
     * Get log context for this use case
     */
    protected function getLogContext(): string
    {
        return 'GetAllBooksUseCase';
    }
}
