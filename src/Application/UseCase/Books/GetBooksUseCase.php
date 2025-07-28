<?php
namespace App\Application\UseCase\Books;

use App\Application\Domain\Repository\BookRepositoryInterface;

class GetBooksUseCase
{
    private BookRepositoryInterface $bookRepository;

    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    /**
     * @param array $filters Opcional: ['userStatus' => 'read', ...]
     * @return array
     */
    public function execute(array $filters = []): array
    {
        // Devuelve un array de libros como arrays asociativos
        $books = $this->bookRepository->findAll($filters);
        // Si los objetos son entidades Book, conviértelos a array
        return array_map(function($book) {
            return is_object($book) && method_exists($book, 'toArray') ? $book->toArray() : $book;
        }, $books);
    }
}
