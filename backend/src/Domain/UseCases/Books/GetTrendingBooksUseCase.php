<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetTrendingBooksQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

/**
 * Use case for getting trending books across all users
 */
class GetTrendingBooksUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserBookRepositoryInterface $userBookRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with GetTrendingBooksQuery
     * Get trending books based on user activity and ratings
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof GetTrendingBooksQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetTrendingBooksQuery');
        }

        $this->logger->info('Getting trending books', [
            'limit' => $command->limit,
            'daysWindow' => $command->daysWindow
        ]);

        // Get trending books from repository
        $trendingBooks = $this->userBookRepository->getTrendingBooks(
            $command->limit,
            $command->daysWindow,
            $command->userId
        );

        $this->logger->info('Trending books retrieved', [
            'count' => count($trendingBooks)
        ]);

        return $trendingBooks;
    }

    /**
     * Get log context for this use case
     */
    protected function getLogContext(): string
    {
        return 'GetTrendingBooksUseCase';
    }
}
