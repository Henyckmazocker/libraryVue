<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Domain\Model\Book;
use App\Application\Domain\Repository\BookRepositoryInterface;
use InvalidArgumentException;

class UpdateBookUserStatusesUseCase
{
    private BookRepositoryInterface $bookRepository;

    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    /**
     * @param string $isbn The ISBN of the book to update.
     * @param array $userStatuses The new array of user statuses.
     * @return Book The updated book.
     * @throws InvalidArgumentException if ISBN is empty, book not found, or statuses are invalid.
     */
    public function execute(string $isbn, array $userStatuses): Book
    {
        if (empty($isbn)) {
            throw new InvalidArgumentException('ISBN is required to update book statuses.');
        }

        $book = $this->bookRepository->findByIsbn($isbn);
        if (!$book) {
            throw new InvalidArgumentException('Book with ISBN ' . $isbn . ' not found.');
        }

        // The Book::setUserStatuses method will handle validation of the statuses themselves.
        try {
            $book->setUserStatuses($userStatuses);
        } catch (\InvalidArgumentException $e) {
            // Re-throw or handle more gracefully
            throw new InvalidArgumentException('Invalid user statuses: ' . $e->getMessage());
        }

        $this->bookRepository->save($book);
        return $book;
    }
} 