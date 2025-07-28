<?php
namespace App\Application\UseCase;

use App\Application\UseCase\Books\GetBooksUseCase;
use App\Application\UseCase\Movies\GetMoviesUseCase;

class GetLibraryItemsUseCase
{
    private GetBooksUseCase $getBooksUseCase;
    private GetMoviesUseCase $getMoviesUseCase;

    public function __construct(GetBooksUseCase $getBooksUseCase, GetMoviesUseCase $getMoviesUseCase)
    {
        $this->getBooksUseCase = $getBooksUseCase;
        $this->getMoviesUseCase = $getMoviesUseCase;
    }

    /**
     * @param array $filters ['title' => string|null, 'status' => string|null, ...]
     * @return array
     */
    public function execute(array $filters = []): array
    {
        $books = $this->getBooksUseCase->execute($filters);
        $movies = $this->getMoviesUseCase->execute($filters);

        // Añadir tipo identificador a cada elemento
        $books = array_map(function($item) {
            $item['itemType'] = 'book';
            return $item;
        }, $books);
        $movies = array_map(function($item) {
            $item['itemType'] = 'movie';
            return $item;
        }, $movies);

        // Unificar y ordenar (el frontend puede ordenar, pero aquí puedes hacerlo si lo deseas)
        $all = array_merge($books, $movies);
        // Ejemplo: ordenar por título ascendente
        usort($all, function($a, $b) {
            return strcmp(strtolower($a['title'] ?? ''), strtolower($b['title'] ?? ''));
        });
        return $all;
    }
}
