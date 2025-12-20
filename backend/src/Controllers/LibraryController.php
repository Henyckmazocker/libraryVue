<?php
namespace App\Controllers;

use App\Domain\UseCases\GetLibraryUseCase;
use App\Domain\UseCases\Books\GetBooksUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\Books\AddBookUseCase;
use App\Domain\UseCases\Movies\AddMovieUseCase;
use App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase;
use App\Domain\UseCases\Books\GetBookAllowedStatusesUseCase;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Domain\DTO\Queries\GetBooksByUserQuery;
use App\Domain\DTO\Queries\GetMoviesByUserQuery;

class LibraryController extends BaseController implements Contracts\LibraryControllerInterface
{
    private GetLibraryUseCase $getLibraryUseCase;
    private GetBooksUseCase $getBooksUseCase;
    private GetMoviesUseCase $getMoviesUseCase;
    private AddBookUseCase $addBookUseCase;
    private AddMovieUseCase $addMovieUseCase;
    private GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase;
    private GetBookAllowedStatusesUseCase $getBookAllowedStatusesUseCase;
    private UserBookRepositoryInterface $userBookRepository;
    private UserMovieRepositoryInterface $userMovieRepository;
    private AuthMiddleware $authMiddleware;

    public function __construct(
        GetLibraryUseCase $getLibraryUseCase,
        GetBooksUseCase $getBooksUseCase,
        GetMoviesUseCase $getMoviesUseCase,
        AddBookUseCase $addBookUseCase,
        AddMovieUseCase $addMovieUseCase,
        GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase,
        GetBookAllowedStatusesUseCase $getBookAllowedStatusesUseCase,
        UserBookRepositoryInterface $userBookRepository,
        UserMovieRepositoryInterface $userMovieRepository,
        AuthMiddleware $authMiddleware
    ) {
        $this->getLibraryUseCase = $getLibraryUseCase;
        $this->getBooksUseCase = $getBooksUseCase;
        $this->getMoviesUseCase = $getMoviesUseCase;
        $this->addBookUseCase = $addBookUseCase;
        $this->addMovieUseCase = $addMovieUseCase;
        $this->getMovieAllowedStatusesUseCase = $getMovieAllowedStatusesUseCase;
        $this->getBookAllowedStatusesUseCase = $getBookAllowedStatusesUseCase;
        $this->userBookRepository = $userBookRepository;
        $this->userMovieRepository = $userMovieRepository;
        $this->authMiddleware = $authMiddleware;
    }

    public function getLibraryItems(int $userId): array
    {
        // Get books and movies for this specific user
        $booksQuery = new GetBooksByUserQuery($userId);
        $books = $this->getBooksUseCase->execute($booksQuery);
        
        $moviesQuery = new GetMoviesByUserQuery($userId);
        $movies = $this->getMoviesUseCase->execute($moviesQuery);
        
        return $this->successResponse('Library items (books and movies) retrieved.', [
            'books' => $books,
            'movies' => $movies
        ]);
    }

    public function saveLibrary(int $userId): array
    {
        // Obtiene la biblioteca actual del usuario específico y la guarda en my_library.json
        $booksQuery = new GetBooksByUserQuery($userId);
        $books = $this->getBooksUseCase->execute($booksQuery);
        $libraryArray = array_map(fn($book) => $book->toArray(), $books);
        $libraryFilePath = __DIR__ . '/../../public/my_library.json';
        $json = json_encode($libraryArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($libraryFilePath, $json) !== false) {
            return $this->successResponse('Library saved successfully.');
        } else {
            return $this->errorResponse('Failed to save library to file.', 500);
        }
    }

    public function importData(array $processedData, int $userId): array
    {
        if (empty($processedData)) {
            throw new \InvalidArgumentException('ProcessedData must be an array.');
        }
        
        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];
        
        foreach ($processedData as $index => $itemData) {
            try {
                // Determinar el tipo de elemento basado en los campos presentes
                $isMovie = isset($itemData['id']) && !isset($itemData['isbn']);
                $isBook = isset($itemData['isbn']);
                
                if ($isMovie) {
                    // Procesar como película
                    $existingMovie = $this->userMovieRepository->findByUserAndMovie($userId, $itemData['id']);
                    if ($existingMovie !== null) {
                        $skippedCount++;
                        continue; // Skip movies user already has
                    }
                    
                    $query = \App\Domain\DTO\Queries\GetAllowedStatusesQuery::forMovies();
                    $allowedStatuses = $this->getMovieAllowedStatusesUseCase->execute($query);
                    $movieDataForUseCase = [
                        'user_id' => $userId,
                        'id' => $itemData['id'],
                        'title' => $itemData['title'],
                        'originalTitle' => $itemData['originalTitle'] ?? $itemData['title'],
                        'director' => $itemData['director'] ?? null,
                        'coverUrl' => $itemData['coverUrl'] ?? null,
                        'rating' => $itemData['rating'] ?? null,
                        'userStatuses' => $itemData['userStatuses'] ?? ['in watchlist'],
                        'addedTimestamp' => $itemData['addedTimestamp'] ?? time(),
                        'allowedStatuses' => $allowedStatuses
                    ];
                    
                    $movieCommand = \App\Domain\DTO\Commands\AddMovieCommand::fromArray($movieDataForUseCase);
                    $this->addMovieUseCase->execute($movieCommand);
                    $importedCount++;
                    
                } else if ($isBook) {
                    // Procesar como libro
                    $existingBook = $this->userBookRepository->findByUserAndBook($userId, $itemData['isbn']);
                    if ($existingBook !== null) {
                        $skippedCount++;
                        continue; // Skip books user already has
                    }
                    
                    $query = \App\Domain\DTO\Queries\GetAllowedStatusesQuery::forBooks();
                    $allowedStatuses = $this->getBookAllowedStatusesUseCase->execute($query);
                    $bookDataForUseCase = [
                        'user_id' => $userId,
                        'isbn' => $itemData['isbn'],
                        'title' => $itemData['title'],
                        'author' => $itemData['author'] ?? null,
                        'publisher' => $itemData['publisher'] ?? null,
                        'publicationDate' => $itemData['publicationDate'] ?? null,
                        'coverUrl' => $itemData['coverUrl'] ?? null,
                        'rating' => $itemData['rating'] ?? null,
                        'pages' => $itemData['pages'] ?? null,
                        'description' => $itemData['description'] ?? null,
                        'userStatuses' => $itemData['userStatuses'] ?? ['owned'],
                        'addedTimestamp' => $itemData['addedTimestamp'] ?? time(),
                        'allowedStatuses' => $allowedStatuses
                    ];
                    
                    $bookCommand = \App\Domain\DTO\Commands\AddBookCommand::fromArray($bookDataForUseCase);
                    $this->addBookUseCase->execute($bookCommand);
                    $importedCount++;
                    
                } else {
                    $errors[] = "Error en elemento {$index}: No se pudo determinar si es libro o película";
                }
                
            } catch (\Exception $e) {
                $itemId = $itemData['id'] ?? $itemData['isbn'] ?? 'unknown';
                $errors[] = "Error en elemento {$index} (ID: {$itemId}): " . $e->getMessage();
                $this->application->logException($e, 'import_item_error', [
                    'item_id' => $itemId,
                    'item_index' => $index,
                    'item_type' => $itemData['type'] ?? 'unknown',
                    'user_id' => $userId
                ]);
            }
        }
        
        return $this->successResponse(
            "Importación completada. Elementos importados: {$importedCount}, Omitidos: {$skippedCount}",
            [
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'total' => count($processedData),
                'errors' => $errors
            ]
        );
    }

    public function ping(): array
    {
        return $this->successResponse('pong', null);
    }

}
