<?php
namespace App\Controllers;

use App\Domain\UseCases\GetLibraryUseCase;
use App\Domain\UseCases\Books\GetBooksUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\Books\AddBookUseCase;
use App\Domain\UseCases\Movies\AddMovieUseCase;
use App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Middleware\AuthMiddleware;

class LibraryController extends BaseController implements Contracts\LibraryControllerInterface
{
    private GetLibraryUseCase $getLibraryUseCase;
    private GetBooksUseCase $getBooksUseCase;
    private GetMoviesUseCase $getMoviesUseCase;
    private AddBookUseCase $addBookUseCase;
    private AddMovieUseCase $addMovieUseCase;
    private GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase;
    private BookRepositoryInterface $bookRepository;
    private UserRepositoryInterface $userRepository;
    private AuthMiddleware $authMiddleware;

    public function __construct(
        GetLibraryUseCase $getLibraryUseCase,
        GetBooksUseCase $getBooksUseCase,
        GetMoviesUseCase $getMoviesUseCase,
        AddBookUseCase $addBookUseCase,
        AddMovieUseCase $addMovieUseCase,
        GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase,
        BookRepositoryInterface $bookRepository,
        UserRepositoryInterface $userRepository,
        AuthMiddleware $authMiddleware
    ) {
        $this->getLibraryUseCase = $getLibraryUseCase;
        $this->getBooksUseCase = $getBooksUseCase;
        $this->getMoviesUseCase = $getMoviesUseCase;
        $this->addBookUseCase = $addBookUseCase;
        $this->addMovieUseCase = $addMovieUseCase;
        $this->getMovieAllowedStatusesUseCase = $getMovieAllowedStatusesUseCase;
        $this->bookRepository = $bookRepository;
        $this->userRepository = $userRepository;
        $this->authMiddleware = $authMiddleware;
    }

    public function getLibraryItems(int $userId): array
    {
        $filters = [];
        // Get books and movies for this specific user
        $books = $this->getBooksUseCase->execute($userId, $filters);
        $movies = $this->getMoviesUseCase->execute($userId, $filters);
        
        return $this->successResponse('Library items (books and movies) retrieved.', [
            'books' => $books,
            'movies' => $movies
        ]);
    }

    public function saveLibrary(int $userId): array
    {
        // Obtiene la biblioteca actual del usuario específico y la guarda en my_library.json
        $books = $this->getBooksUseCase->execute($userId);
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
                    if ($this->userRepository->hasUserMovie($userId, $itemData['id'])) {
                        $skippedCount++;
                        continue; // Skip movies user already has
                    }
                    
                    $allowedStatuses = $this->getMovieAllowedStatusesUseCase->execute();
                    $movieDataForUseCase = [
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
                    
                    $this->addMovieUseCase->execute($movieDataForUseCase, $userId);
                    $importedCount++;
                    
                } else if ($isBook) {
                    // Procesar como libro
                    if ($this->userRepository->hasUserBook($userId, $itemData['isbn'])) {
                        $skippedCount++;
                        continue; // Skip books user already has
                    }
                    
                    $allowedStatuses = $this->bookRepository->fetchAllowedStatuses();
                    $bookDataForUseCase = [
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
                    
                    $this->addBookUseCase->execute($bookDataForUseCase, $userId);
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

    /**
     * Handle HTTP request for library endpoints
     */
    public function handleRequest(string $method, string $path): void
    {
        try {
            $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
            $action = $inputData['action'] ?? $_REQUEST['action'] ?? null;
            
            // Handle authentication for actions that require it
            $authResult = null;
            $authRequiredActions = ['get_library_items', 'import_books', 'import_movies'];
            
            if (in_array($action, $authRequiredActions)) {
                $authResult = $this->authMiddleware->requireAuth();
                if ($authResult['status'] === 'error') {
                    http_response_code(401);
                    echo json_encode($authResult);
                    return;
                }
                
                // Check CSRF for modifying actions
                $csrfRequiredActions = ['import_books', 'import_movies'];
                if (in_array($action, $csrfRequiredActions)) {
                    $csrfResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($csrfResult['status'] === 'error') {
                        http_response_code(403);
                        echo json_encode($csrfResult);
                        return;
                    }
                    $authResult = $csrfResult;
                }
            }
            
            $response = match ($action) {
                'get_library_items' => $this->getLibraryItems($authResult['user']['id']),
                'import_books' => $this->importBooks($inputData['books'] ?? [], $authResult['user']['id']),
                'import_movies' => $this->importMovies($inputData['movies'] ?? [], $authResult['user']['id']),
                'ping' => $this->ping(),
                default => $this->errorResponse('Invalid library action: ' . $action)
            };
            
            $statusCode = $response['status'] === 'success' ? 200 : 400;
            http_response_code($statusCode);
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }
    }
}
