<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Model\Book;
use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface as NewUserRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\AddBookCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class AddBookUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository,
        private readonly NewUserRepositoryInterface $userRepository,
        private readonly UserBookRepositoryInterface $userBookRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with AddBookCommand
     */
    protected function doExecute($command): Book
    {
        // Validate command is AddBookCommand
        if (!$command instanceof AddBookCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddBookCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Check if user already has this book
        if ($this->userBookRepository->hasBook($command->userId, $command->isbn->toString())) {
            throw new InvalidArgumentException('You already have this book in your library.');
        }

        // Check if book exists in the system
        $existingBook = $this->bookRepository->findById($command->isbn->toString());
        
        if (!$existingBook) {
            // Book doesn't exist, create it first
            // Get allowed statuses from repository for validation
            $bookData = $command->toArray();
            $bookData['allowedStatuses'] = $this->bookRepository->fetchAllowedStatuses();
            
            $book = Book::fromArray($bookData);
            $this->bookRepository->save($book);
        } else {
            // Book exists, use existing book data
            $book = $existingBook;
        }

        // Add the book to user's library with their specific statuses
        $this->userBookRepository->add($command->userId, $command->isbn->toString(), $command->statuses);
        
        // Update user's personal rating if provided
        if ($command->userRating !== null) {
            $this->userBookRepository->updateRating(
                $command->userId, 
                $command->isbn->toString(), 
                $command->userRating->toFloat()
            );
        }
        
        return $book;
    }

    protected function getLogContext(): string
    {
        return 'AddBookUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Book added successfully to user library';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to add book to user library';
    }
}