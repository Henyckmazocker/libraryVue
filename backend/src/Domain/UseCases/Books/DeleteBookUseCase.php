<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

class DeleteBookUseCase
{
    private BookRepositoryInterface $bookRepository;
    private UserRepositoryInterface $userRepository;

    public function __construct(
        BookRepositoryInterface $bookRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->bookRepository = $bookRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @param int $userId ID of the user removing the book from their library
     * @param string $isbn The ISBN of the book to remove from user's library.
     * @return bool True if removal was successful.
     * @throws InvalidArgumentException if user or book not found, or user doesn't have this book.
     */
    public function execute(int $userId, string $isbn): bool
    {
        if (empty($isbn)) {
            throw new InvalidArgumentException('ISBN is required to remove a book.');
        }

        // Validate user exists
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$userId} not found");
        }

        // Check if user has this book in their library
        if (!$this->userRepository->hasUserBook($userId, $isbn)) {
            throw new InvalidArgumentException('Book not found in your library.');
        }

        // Remove the book from user's library (not from the system)
        return $this->bookRepository->removeBookFromUser($userId, $isbn);
    }
} 