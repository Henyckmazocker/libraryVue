<?php
namespace App\Domain\UseCases\Books;

use App\Domain\Repository\BookRepositoryInterface;

class GetAllBooksUseCase
{
    private BookRepositoryInterface $bookRepository;

    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    /**
     * Get all books from the catalog
     *
     * @return array
     */
    public function execute(): array
    {
        try {
            return $this->bookRepository->findAll();
        } catch (\Exception $e) {
            throw new \RuntimeException('Unable to retrieve books: ' . $e->getMessage(), 0, $e);
        }
    }
}
