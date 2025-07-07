<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Domain\Model\Book;
use App\Application\Domain\Repository\BookRepositoryInterface;
use InvalidArgumentException;

class AddBookUseCase
{
    private BookRepositoryInterface $bookRepository;

    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    /**
     * @param array $bookData Raw data for the book, including userStatuses.
     * @return Book The added book.
     * @throws InvalidArgumentException if book data is invalid or book already exists.
     */
    public function execute(array $bookData): Book
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
        // Further validation of statuses (e.g., against Book::ALLOWED_STATUSES) will be done in Book::fromArray or Book constructor

        if ($this->bookRepository->findByIsbn($bookData['isbn'])) {
            throw new InvalidArgumentException('Book with ISBN ' . $bookData['isbn'] . ' already exists.');
        }

        // Let the Book constructor and fromArray handle detailed validation.
        try {
            $book = Book::fromArray([
                'isbn' => $bookData['isbn'],
                'title' => $bookData['title'],
                'author' => $bookData['author'] ?? null,
                'coverUrl' => $bookData['coverUrl'] ?? null,
                'rating' => isset($bookData['rating']) && is_numeric($bookData['rating']) ? (float)$bookData['rating'] : null,
                'userStatuses' => $bookData['userStatuses'], // Pass userStatuses
                'addedTimestamp' => $bookData['addedTimestamp'] ?? time()
            ],
                $bookData['allowedStatuses']
            );
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException('Invalid book data: ' . $e->getMessage());
        }
        
        $this->bookRepository->save($book);
        return $book;
    }
} 