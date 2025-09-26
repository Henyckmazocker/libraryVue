<?php
namespace App\Router;

use App\Controllers\AuthController;
use App\Controllers\BookController;
use App\Controllers\MovieController;
use App\Controllers\LibraryController;
use App\Controllers\LibraryXController;
use App\Controllers\StatsController;
use App\Infrastructure\Middleware\AuthMiddleware;

class ActionRouter
{
    private AuthController $authController;
    private BookController $bookController;
    private MovieController $movieController;
    private LibraryController $libraryController;
    private LibraryXController $libraryXController;
    private StatsController $statsController;
    private AuthMiddleware $authMiddleware;

    public function __construct(
        AuthController $authController,
        BookController $bookController,
        MovieController $movieController,
        LibraryController $libraryController,
        LibraryXController $libraryXController,
        StatsController $statsController,
        AuthMiddleware $authMiddleware
    ) {
        $this->authController = $authController;
        $this->bookController = $bookController;
        $this->movieController = $movieController;
        $this->libraryController = $libraryController;
        $this->libraryXController = $libraryXController;
        $this->statsController = $statsController;
        $this->authMiddleware = $authMiddleware;
    }

    public function dispatch(string $action, array $inputData): array
    {
        try {
            switch ($action) {
                // AUTH
                case 'login':
                    return $this->authController->login($inputData);
                case 'logout':
                    return $this->authController->logout();
                case 'check_auth':
                    return $this->authController->checkAuth();
                case 'log_frontend':
                    return $this->authController->logFrontend($inputData['log_data'] ?? []);

                // BOOKS
                case 'add_book':
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->bookController->addBook($inputData['book'] ?? [], $authResult['user']['id']);
                    
                case 'delete_book':
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->bookController->deleteBook($inputData['isbn'] ?? '', $authResult['user']['id']);
                    
                case 'update_book_rating':
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->bookController->updateBookRating($inputData['isbn'] ?? '', $inputData['rating'] ?? null, $authResult['user']['id']);
                    
                case 'update_book_user_statuses':
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->bookController->updateBookUserStatuses($inputData['isbn'] ?? '', $inputData['statuses'] ?? [], $authResult['user']['id']);
                    
                case 'get_book_allowed_statuses':
                    return $this->bookController->getBookAllowedStatuses();
                    
                case 'get_books':
                    return $this->bookController->getAllBooks();
                    
                case 'get_library':
                    $authResult = $this->authMiddleware->requireAuth();
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->bookController->getBooks($authResult['user']['id']);

                case 'edit_user_book': 
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->bookController->editUserBook($inputData['isbn'] ?? '', $authResult['user']['id'], $inputData['data'] ?? [], $inputData['tags'] ?? [], $inputData['notes'] ?? []);

                // MOVIES
                case 'add_movie':
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->movieController->addMovie($inputData['movie'] ?? [], $authResult['user']['id']);
                    
                case 'delete_movie':
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    $movieId = $inputData['imdbID'] ?? $inputData['id'] ?? '';
                    return $this->movieController->deleteMovie($movieId, $authResult['user']['id']);
                    
                case 'update_movie_rating':
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    $movieId = $inputData['imdbID'] ?? $inputData['id'] ?? '';
                    return $this->movieController->updateMovieRating($movieId, $inputData['rating'] ?? null, $authResult['user']['id']);
                    
                case 'update_movie_user_statuses':
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    $movieId = $inputData['imdbID'] ?? $inputData['id'] ?? '';
                    return $this->movieController->updateMovieUserStatuses($movieId, $inputData['statuses'] ?? [], $authResult['user']['id']);
                    
                case 'get_movie_allowed_statuses':
                    return $this->movieController->getMovieAllowedStatuses();
                    
                case 'get_movies':
                    $authResult = $this->authMiddleware->requireAuth();
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->movieController->getMovies($authResult['user']['id']);

                // LIBRARY
                case 'get_library_items':
                    $authResult = $this->authMiddleware->requireAuth();
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->libraryController->getLibraryItems($authResult['user']['id']);
                    
                case 'save_library':
                    $authResult = $this->authMiddleware->requireAuth();
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->libraryController->saveLibrary($authResult['user']['id']);
                    
                case 'import_data':
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->libraryController->importData($inputData['processedData'] ?? [], $authResult['user']['id']);
                    
                case 'ping':
                    return $this->libraryController->ping();

                // LIBRARYX
                case 'libraryx_get_urls':
                    $authResult = $this->authMiddleware->requireAuth();
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->libraryXController->getUrls($authResult['user']);
                    
                case 'libraryx_update_urls':
                    $authResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->libraryXController->updateUrls($inputData, $authResult['user']);

                // STATISTICS
                case 'get_book_stats':
                    $authResult = $this->authMiddleware->requireAuth();
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->statsController->getBookStats($authResult['user']['id']);

                case 'get_movie_stats':
                    $authResult = $this->authMiddleware->requireAuth();
                    if ($authResult['status'] === 'error') return $authResult;
                    return $this->statsController->getMovieStats($authResult['user']['id']);

                default:
                    if (isset($inputData['message']) && $action === null) {
                        return [
                            'status' => 'success',
                            'message' => 'Original message endpoint: Message received: ' . $inputData['message'],
                            'http_code' => 200
                        ];
                    } else {
                        return [
                            'status' => 'error',
                            'message' => 'No valid action specified or missing required parameters. Action: ' . ($action ?? 'null'),
                            'http_code' => 400
                        ];
                    }
            }
        } catch (\InvalidArgumentException $e) {
            // Log validation errors
            if (function_exists('logger')) {
                try {
                    logger('api')->warning('ActionRouter Validation Error', [
                        'message' => $e->getMessage(),
                        'action' => $action ?? 'unknown',
                        'exception_class' => get_class($e)
                    ]);
                } catch (\Throwable $logError) {
                    error_log("ActionRouter logging error: " . $logError->getMessage());
                }
            }
            
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'http_code' => 400
            ];
        } catch (\Exception $e) {
            // Log unexpected errors
            if (function_exists('logger')) {
                try {
                    logger('api')->error('ActionRouter Unexpected Error', [
                        'message' => $e->getMessage(),
                        'action' => $action ?? 'unknown',
                        'exception_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                } catch (\Throwable $logError) {
                    error_log("ActionRouter logging error: " . $logError->getMessage());
                }
            } else {
                // Fallback to error_log
                error_log("ActionRouter error in action '{$action}': " . $e->getMessage());
            }
            
            return [
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'http_code' => 500
            ];
        }
    }
}
