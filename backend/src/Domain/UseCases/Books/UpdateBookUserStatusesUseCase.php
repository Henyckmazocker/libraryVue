<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

class UpdateBookUserStatusesUseCase
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
     * @param int $userId ID of the user updating the book
     * @param string $isbn The ISBN of the book to update.
     * @param array $userStatuses The new array of user statuses.
     * @return bool True if update was successful.
     * @throws InvalidArgumentException if user or book not found, or if user doesn't have this book
     */
    public function execute(int $userId, string $isbn, array $userStatuses): bool
    {
        if (empty($isbn)) {
            throw new InvalidArgumentException('ISBN is required to update book statuses.');
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

        // Update the user's statuses for this book
        $this->bookRepository->updateUserBookStatuses((int)$userId, $isbn, $userStatuses);
        
        return true;
    }
} 