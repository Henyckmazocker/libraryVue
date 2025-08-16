<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

class UpdateBookRatingUseCase
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
     * @param int $userId ID of the user updating the rating
     * @param string $isbn The ISBN of the book to update.
     * @param float|null $rating The new rating (0.5-5, multiple of 0.5, or null to unrate).
     * @return bool True if update was successful.
     * @throws InvalidArgumentException if user or book not found, user doesn't have book, or rating is invalid.
     */
    public function execute(int $userId, string $isbn, ?float $rating): bool
    {
        if (empty($isbn)) {
            throw new InvalidArgumentException('ISBN is required to update a rating.');
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

        // Validate rating value
        if ($rating !== null && ($rating < 0.5 || $rating > 5 || fmod($rating * 2, 1) !== 0.0)) {
            throw new InvalidArgumentException('Rating must be between 0.5 and 5 in increments of 0.5, or null');
        }

        // Update the user's rating for this book
        $this->bookRepository->updateUserBookRating($userId, $isbn, $rating);
        
        return true;
    }
} 