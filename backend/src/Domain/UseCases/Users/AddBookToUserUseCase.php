<?php
declare(strict_types=1);

namespace App\Domain\UseCases\Users;

use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface as NewUserRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\AddBookToUserCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use RuntimeException;

class AddBookToUserUseCase extends AbstractUseCase
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
     * Execute with AddBookToUserCommand
     * Add a book to user's library
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof AddBookToUserCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddBookToUserCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new RuntimeException("User with ID {$command->userId} not found");
        }

        // Validate book exists
        $isbnString = $command->isbn->toString();
        $book = $this->bookRepository->findById($isbnString);
        if (!$book) {
            throw new RuntimeException("Book with ISBN {$isbnString} not found");
        }

        // Check if user already has this book
        if ($this->userBookRepository->hasBook($command->userId, $isbnString)) {
            throw new RuntimeException("User already has this book in their library");
        }

        // Add book to user's library
        $this->bookRepository->addBookToUser($command->userId, $isbnString, $command->statuses);

        return [
            'status' => 'success',
            'message' => 'Book added to user library successfully',
            'data' => [
                'user_id' => $command->userId,
                'isbn' => $isbnString,
                'statuses' => $command->statuses
            ]
        ];
    }

    /**
     * Get log context for this use case
     */
    protected function getLogContext(): string
    {
        return 'AddBookToUserUseCase';
    }
}
