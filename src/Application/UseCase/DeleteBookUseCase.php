<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Domain\Repository\BookRepositoryInterface;
use InvalidArgumentException;

class DeleteBookUseCase
{
    private BookRepositoryInterface $bookRepository;

    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    /**
     * @param string $isbn The ISBN of the book to delete.
     * @return bool True if deletion was successful, false otherwise.
     * @throws InvalidArgumentException if ISBN is empty.
     */
    public function execute(string $isbn): bool
    {
        if (empty($isbn)) {
            throw new InvalidArgumentException('ISBN is required to delete a book.');
        }

        if (!$this->bookRepository->findByIsbn($isbn)) {
            // Or throw a more specific BookNotFoundException
            throw new InvalidArgumentException('Book with ISBN ' . $isbn . ' not found.');
        }

        return $this->bookRepository->deleteByIsbn($isbn);
    }
} 