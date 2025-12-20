<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetBooksByUserQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetBooksUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserBookRepositoryInterface $userBookRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with GetBooksByUserQuery
     * Get books for a specific user with optional filters
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof GetBooksByUserQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetBooksByUserQuery');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Get books for this specific user
        $books = $this->userBookRepository->findByUser($command->userId, $command->filters);

        // Convert to array format if needed
        return array_map(function($book) {
            return is_object($book) && method_exists($book, 'toArray') ? $book->toArray() : $book;
        }, $books);
    }

    /**
     * Get log context for this use case
     */
    protected function getLogContext(): string
    {
        return 'GetBooksUseCase';
    }
}
