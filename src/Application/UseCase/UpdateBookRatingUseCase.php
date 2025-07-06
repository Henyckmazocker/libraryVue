<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Domain\Repository\BookRepositoryInterface;
use InvalidArgumentException;

class UpdateBookRatingUseCase
{
    private BookRepositoryInterface $bookRepository;

    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    /**
     * @param string $isbn The ISBN of the book to update.
     * @param float|null $rating The new rating (0.5-5, multiple of 0.5, or null to unrate).
     * @return bool True if update was successful.
     * @throws InvalidArgumentException if ISBN is empty, book not found, or rating is invalid.
     */
    public function execute(string $isbn, ?float $rating): bool
    {
        if (empty($isbn)) {
            throw new InvalidArgumentException('ISBN is required to update a rating.');
        }

        $book = $this->bookRepository->findByIsbn($isbn);
        if (!$book) {
            // Or throw a more specific BookNotFoundException
            throw new InvalidArgumentException('Book with ISBN ' . $isbn . ' not found.');
        }

        // The Book::setRating method already contains validation for the rating value itself.
        // The Book constructor and setRating will throw InvalidArgumentException for bad rating values.
        try {
            $book->setRating($rating);
        } catch (\InvalidArgumentException $e) {
             throw new InvalidArgumentException('Invalid rating value: ' . $e->getMessage());
        }

        $this->bookRepository->save($book);
        return true;
    }
} 