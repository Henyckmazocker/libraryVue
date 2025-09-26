<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Model\Book;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

class AddBookUseCase
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
     * @param array $bookData Raw data for the book, including userStatuses.
     * @param int $userId ID of the user to associate the book with
     * @return Book The added book.
     * @throws InvalidArgumentException if book data is invalid or user-book relationship already exists.
     */
    public function execute(array $bookData, int $userId): Book
    {
        if (empty($bookData['isbn'])) {
            throw new InvalidArgumentException('ISBN is required to add a book.');
        }
        if (empty($bookData['title'])) {
            throw new InvalidArgumentException('Title is required to add a book.');
        }
        if (empty($bookData['userStatuses']) || !is_array($bookData['userStatuses'])) {
            throw new InvalidArgumentException('User statuses are required and must be an array.');
        }

        // Validate user exists
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$userId} not found");
        }

        // Check if user already has this book - this is the only error case
        if ($this->userRepository->hasUserBook($userId, $bookData['isbn'])) {
            throw new InvalidArgumentException('You already have this book in your library.');
        }

        // Check if book exists in the system
        $existingBook = $this->bookRepository->findById($bookData['isbn']);
        
        if (!$existingBook) {
            // Book doesn't exist, create it first
            try {
                $book = Book::fromArray([
                    'isbn' => $bookData['isbn'],
                    'title' => $bookData['title'],
                    'author' => $bookData['author'] ?? null,
                    'publisher' => $bookData['publisher'] ?? null,
                    'publicationDate' => $bookData['publicationDate'] ?? null,
                    'coverUrl' => $bookData['coverUrl'] ?? null,
                    'rating' => isset($bookData['rating']) && is_numeric($bookData['rating']) ? (float)$bookData['rating'] : null,
                    'pages' => isset($bookData['pages']) && is_numeric($bookData['pages']) ? (int)$bookData['pages'] : null,
                    'description' => $bookData['description'] ?? null,
                    'userStatuses' => $bookData['userStatuses'], // Pass userStatuses
                    'allowedStatuses' => $bookData['allowedStatuses'] ?? [], // Include allowedStatuses in the data
                    'addedTimestamp' => $bookData['addedTimestamp'] ?? time(),
                    'genres' => $bookData['genres'] ?? null // Add genres field
                ]);
            } catch (\InvalidArgumentException $e) {
                throw new InvalidArgumentException('Invalid book data: ' . $e->getMessage());
            }
            
            // Save the book to the system
            $this->bookRepository->save($book);
        } else {
            // Book exists, use existing book data
            $book = $existingBook;
        }

        // Add the book to user's library with their specific statuses
        $this->bookRepository->addBookToUser((int)$userId, $bookData['isbn'], $bookData['userStatuses']);
        
        // Update user's personal rating if provided
        if (isset($bookData['rating']) && is_numeric($bookData['rating'])) {
            $personalRating = (float)$bookData['rating'];
            if ($personalRating >= 0 && $personalRating <= 5) {
                $this->bookRepository->updateUserBookRating((int)$userId, $bookData['isbn'], $personalRating);
            }
        }
        
        return $book;
    }
}