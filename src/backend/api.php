<?php
declare(strict_types=1);

// ini_set('display_errors', '1'); // Removed for cleanup
ini_set('log_errors', '1');     // Keep errors logged
error_reporting(E_ALL);       // Report all errors to the log

// Simple PSR-4 autoloader (restored)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    // Assumes api.php is in a directory like 'backend' 
    // and 'src' is a sibling to 'backend', containing the 'App' namespace root.
    $base_dir = __DIR__ . '/../'; 

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Not an App\ class, move to next autoloader
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Require Composer autoloader for dependencies
require_once __DIR__ . '/../src/vendor/autoload.php';


use App\Infrastructure\Persistence\MySqlBookRepository;
use App\Infrastructure\Persistence\MySqlMovieRepository;
use App\Infrastructure\Persistence\MySqlUserRepository;
use App\Application\UseCase\Books\AddBookUseCase;
use App\Application\UseCase\GetLibraryUseCase;
use App\Application\UseCase\Books\DeleteBookUseCase;
use App\Application\UseCase\Books\UpdateBookRatingUseCase;
use App\Application\UseCase\Movies\AddMovieUseCase;
use App\Application\UseCase\Movies\DeleteMovieUseCase;
use App\Application\UseCase\Movies\GetMovieAllowedStatusesUseCase;
use App\Application\UseCase\Books\UpdateBookUserStatusesUseCase;
use App\Application\UseCase\GetLibraryItemsUseCase;
use App\Application\UseCase\Books\GetBooksUseCase;
use App\Application\UseCase\Movies\GetMoviesUseCase;
use App\Application\Domain\Model\Book;
use App\Application\Domain\Model\Movie;
use App\Application\UseCase\Movies\UpdateMovieRatingUseCase;
use App\Application\UseCase\Auth\LoginUserUseCase;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Middleware\AuthMiddleware;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:8080'); // Specific origin for credentials
header('Access-Control-Allow-Credentials: true'); // Allow cookies
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle OPTIONS preflight request for CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

$response = [
    'status' => 'error',
    'message' => 'An unexpected error occurred.',
    'data' => null
];
$statusCode = 500;

// Configuration
// $libraryFilePath = __DIR__ . '/my_library.json'; // No longer needed

try {
    // Initialize session and auth components
    $sessionManager = new SessionManager();
    $userRepository = new MySqlUserRepository();
    $authMiddleware = new AuthMiddleware($sessionManager, $userRepository);
    
    // Initialize repositories
    $bookRepository = new MySqlBookRepository();
    $movieRepository = new MySqlMovieRepository();

    // Auth use cases
    $loginUserUseCase = new LoginUserUseCase($userRepository);

    // Use cases libros
    $addBookUseCase = new AddBookUseCase($bookRepository, $userRepository);
    $getLibraryUseCase = new GetLibraryUseCase($bookRepository);
    $deleteBookUseCase = new DeleteBookUseCase($bookRepository, $userRepository);
    $updateBookRatingUseCase = new UpdateBookRatingUseCase($bookRepository, $userRepository);
    $updateBookUserStatusesUseCase = new UpdateBookUserStatusesUseCase($bookRepository, $userRepository);
    $getBooksUseCase = new GetBooksUseCase($bookRepository, $userRepository);

    // Use cases películas
    $addMovieUseCase = new AddMovieUseCase($movieRepository, $userRepository);
    $deleteMovieUseCase = new DeleteMovieUseCase($movieRepository, $userRepository);
    $getMovieAllowedStatusesUseCase = new GetMovieAllowedStatusesUseCase($movieRepository);
    $getMoviesUseCase = new GetMoviesUseCase($movieRepository, $userRepository);
    $updateMovieUserStatusesUseCase = new App\Application\UseCase\Movies\UpdateMovieUserStatusesUseCase($movieRepository, $userRepository);
    $updateMovieRatingUseCase = new UpdateMovieRatingUseCase($movieRepository, $userRepository);

    // Use case combinado para biblioteca unificada
    $getLibraryItemsUseCase = new GetLibraryItemsUseCase($getBooksUseCase, $getMoviesUseCase);

    // Decode incoming JSON data
    $inputData = json_decode(file_get_contents('php://input'), true) ?? [];

    // Determine action
    $action = $inputData['action'] ?? $_REQUEST['action'] ?? null;

    switch ($action) {
        // ==================== AUTH ENDPOINTS ====================
        case 'login':
            if (!isset($inputData['google_token']) || !is_string($inputData['google_token'])) {
                throw new InvalidArgumentException('Google token is required for login.');
            }
            
            // TEMPORAL: Simple verification of Google JWT token header
            // This will be replaced with Google Client library verification later
            $tokenParts = explode('.', $inputData['google_token']);
            if (count($tokenParts) !== 3) {
                throw new InvalidArgumentException('Invalid Google token format.');
            }
            
            $header = json_decode(base64_decode($tokenParts[0]), true);
            $payload = json_decode(base64_decode($tokenParts[1]), true);
            
            if (!$payload || !isset($payload['sub'], $payload['email'], $payload['name'])) {
                throw new InvalidArgumentException('Invalid Google token payload.');
            }
            
            // For now, we'll accept the payload without cryptographic verification
            // In production, you MUST verify the signature with Google's public keys
            
            $user = $loginUserUseCase->execute($payload);
            $sessionManager->login($user);
            
            $response['status'] = 'success';
            $response['message'] = 'Login successful.';
            $response['data'] = [
                'user' => $user->toArray(),
                'csrf_token' => $authMiddleware->getCSRFToken()
            ];
            $statusCode = 200;
            break;

        case 'logout':
            $sessionManager->logout();
            $response['status'] = 'success';
            $response['message'] = 'Logout successful.';
            $statusCode = 200;
            break;

        case 'check_auth':
            $authResult = $authMiddleware->requireAuth();
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
            } else {
                $response['status'] = 'success';
                $response['message'] = 'User is authenticated.';
                $response['data'] = [
                    'user' => $authResult['user'],
                    'csrf_token' => $authMiddleware->getCSRFToken()
                ];
                $statusCode = 200;
            }
            break;

        // ==================== PROTECTED ENDPOINTS ====================
        case 'get_library_items':
            // Check authentication for protected endpoints
            $authResult = $authMiddleware->requireAuth();
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            $filters = [];
            // You can add filter logic from $inputData if needed
            
            // Get books and movies for this specific user
            $books = $getBooksUseCase->execute($userId, $filters);
            $movies = $getMoviesUseCase->execute($userId, $filters);
            
            $response['status'] = 'success';
            $response['message'] = 'Library items (books and movies) retrieved.';
            $response['data'] = [
                'books' => $books,
                'movies' => $movies
            ];
            $statusCode = 200;
            break;
        case 'save_library':
            // Obtiene la biblioteca actual y la guarda en my_library.json
            $library = $getLibraryUseCase->execute();
            $libraryArray = array_map(fn($book) => $book->toArray(), $library);
            $libraryFilePath = __DIR__ . '/my_library.json';
            $json = json_encode($libraryArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (file_put_contents($libraryFilePath, $json) !== false) {
                $response['status'] = 'success';
                $response['message'] = 'Library saved successfully.';
                $statusCode = 200;
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Failed to save library to file.';
                $statusCode = 500;
            }
            break;
        case 'get_book_allowed_statuses':
            $statuses = $bookRepository->fetchAllowedStatuses();
            $response['status'] = 'success';
            $response['message'] = 'Allowed book statuses retrieved.';
            $response['data'] = $statuses;
            $statusCode = 200;
            break;
        case 'add_book':
            // Require authentication and CSRF token for write operations
            $authResult = $authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            
            if (!isset($inputData['book']) || !is_array($inputData['book'])) {
                throw new InvalidArgumentException('Book data is required for add_book action.');
            }
            $addedBook = $addBookUseCase->execute($inputData['book'], $userId);
            $response['status'] = 'success';
            $response['message'] = 'Book added: ' . $addedBook->getTitle();
            $response['data'] = $addedBook->toArray();
            $statusCode = 201; // Created
            break;

        case 'add_movie':
            // Require authentication and CSRF token for write operations
            $authResult = $authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            
            if (!isset($inputData['movie']) || !is_array($inputData['movie'])) {
                throw new InvalidArgumentException('Movie data is required for add_movie action.');
            }
            $addedMovie = $addMovieUseCase->execute($inputData['movie'], $userId);
            $response['status'] = 'success';
            $response['message'] = 'Movie added: ' . $addedMovie->getTitle();
            $response['data'] = $addedMovie->toArray();
            $statusCode = 201;
            break;

        case 'get_library':
            // Require authentication for user's personal library
            $authResult = $authMiddleware->requireAuth();
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            $books = $getBooksUseCase->execute($userId);
            $response['status'] = 'success';
            $response['message'] = 'Library data retrieved.';
            $response['data'] = $books;
            $statusCode = 200;
            break;

        case 'get_movies':
            // Require authentication for user's personal movies
            $authResult = $authMiddleware->requireAuth();
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            $movies = $getMoviesUseCase->execute($userId);
            $response['status'] = 'success';
            $response['message'] = 'Movies data retrieved.';
            $response['data'] = $movies;
            $statusCode = 200;
            break;

        case 'get_movie_allowed_statuses':
            $statuses = $getMovieAllowedStatusesUseCase->execute();
            $response['status'] = 'success';
            $response['message'] = 'Allowed movie statuses retrieved.';
            $response['data'] = $statuses;
            $statusCode = 200;
            break;

        case 'delete_book':
            // Require authentication and CSRF token for delete operations
            $authResult = $authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            
            if (!isset($inputData['isbn']) || !is_string($inputData['isbn'])) {
                throw new InvalidArgumentException('ISBN is required for delete_book action.');
            }
            $deleteBookUseCase->execute($userId, $inputData['isbn']);
            $response['status'] = 'success';
            $response['message'] = 'Book removed from your library: ' . $inputData['isbn'];
            $statusCode = 200;
            break;

        case 'delete_movie':
            // Require authentication and CSRF token for delete operations
            $authResult = $authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            $movieId = $inputData['imdbID'] ?? $inputData['isbn'] ?? $inputData['id'] ?? null;

            if (!isset($movieId) || !is_string($movieId)) {
                throw new InvalidArgumentException('ID is required for delete_movie action.');
            }
            $deleteMovieUseCase->execute($userId, $movieId);
            $response['status'] = 'success';
            $response['message'] = 'Movie removed from your library: ' . $movieId;
            $statusCode = 200;
            break;

        case 'update_book_rating':
            // Require authentication and CSRF token for update operations
            $authResult = $authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            
            if (!isset($inputData['isbn']) || !is_string($inputData['isbn'])) {
                throw new InvalidArgumentException('ISBN is required for update_book_rating.');
            }
            // Rating can be null, float, or 0 (which will be treated as null by use case/entity)
            $rating = null;
            if (isset($inputData['rating'])) {
                if (is_numeric($inputData['rating'])) {
                    $rating = (float)$inputData['rating'];
                    if ($rating == 0) { // Treat explicit 0 as unrate intention
                        $rating = null;
                    }
                } else {
                    // If rating is present but not numeric (and not null), it's an issue.
                    // The UseCase/Book entity will also validate this, but good to be clear.
                    throw new InvalidArgumentException('Rating must be a number or null.');
                }
            }
            
            $updateBookRatingUseCase->execute($userId, $inputData['isbn'], $rating);
            $response['status'] = 'success';
            $response['message'] = 'Rating updated for ISBN ' . $inputData['isbn'];
            $statusCode = 200;
            break;
        case 'update_book_user_statuses':
            // Require authentication and CSRF token for update operations
            $authResult = $authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            
            if (!isset($inputData['isbn']) || !is_string($inputData['isbn'])) {
                throw new InvalidArgumentException('ISBN is required for update_book_user_statuses.');
            }
            // Statuses can't be null, or empty
            $statuses = null;
            if (isset($inputData['statuses'])) {
                if (is_array($inputData['statuses']) && !empty($inputData['statuses'])) {
                    $statuses = $inputData['statuses'];
                } else {
                    throw new InvalidArgumentException('Statuses must be a non-empty array.');
                }
            }

            $updateBookUserStatusesUseCase->execute($userId, $inputData['isbn'], $statuses);
            $response['status'] = 'success';
            $response['message'] = 'User statuses updated for ISBN ' . $inputData['isbn'];
            $statusCode = 200;
            break;
        case 'update_movie_user_statuses':
            // Require authentication and CSRF token for update operations
            $authResult = $authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            $userId = $authResult['user']['id'];
            $movieId = $inputData['imdbID'] ?? $inputData['isbn'] ?? $inputData['id'] ?? null;

            if (!isset($movieId) || !is_string($movieId)) {
                throw new InvalidArgumentException('movieId is required for update_movie_user_statuses.');
            }
            $statuses = null;
            if (isset($inputData['statuses'])) {
                if (is_array($inputData['statuses']) && !empty($inputData['statuses'])) {
                    $statuses = $inputData['statuses'];
                } else {
                    throw new InvalidArgumentException('Statuses must be a non-empty array.');
                }
            }
            $updateMovieUserStatusesUseCase->execute($userId, $movieId, $statuses);
            $response['status'] = 'success';
            $response['message'] = 'User statuses updated for Movie ID ' . $movieId;
            $statusCode = 200;
            break;
        case 'update_movie_rating':
            // Require authentication and CSRF token for update operations
            $authResult = $authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            
            // Temporary debug logs
            error_log("DEBUG - Update movie rating inputData: " . json_encode($inputData));
            
            $movieId = $inputData['imdbID'] ?? $inputData['isbn'] ?? $inputData['id'] ?? null;
            $rating = isset($inputData['rating']) ? (float)$inputData['rating'] : null;
            
            error_log("DEBUG - Extracted movieId: " . ($movieId ?? 'NULL') . ", rating: " . ($rating ?? 'NULL'));
            
            if (!$movieId) {
                $response['status'] = 'error';
                $response['message'] = 'movieId is required to update movie rating.';
                $statusCode = 400;
                break;
            }
            
            // Allow null rating for unrating
            if ($rating === 0) {
                $rating = null;
            }
            
            $updateMovieRatingUseCase->execute($userId, $movieId, $rating);
            $response['status'] = 'success';
            $response['message'] = 'Movie rating updated successfully.';
            $statusCode = 200;
            break;
        case 'import_data':
            // Require authentication and CSRF token for import operations
            $authResult = $authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
            if ($authResult['status'] === 'error') {
                $response = $authResult;
                $statusCode = $authResult['http_code'];
                break;
            }
            
            $userId = $authResult['user']['id'];
            
            if (!isset($inputData['service']) || !isset($inputData['processedData'])) {
                throw new InvalidArgumentException('Service and processedData are required for import_data action.');
            }
            
            $service = $inputData['service'];
            $processedData = $inputData['processedData'];
            
            // Verificar que processedData sea un array
            if (!is_array($processedData)) {
                throw new InvalidArgumentException('ProcessedData must be an array.');
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
                        if ($userRepository->hasUserMovie($userId, $itemData['id'])) {
                            $skippedCount++;
                            continue; // Skip movies user already has
                        }
                        
                        $allowedStatuses = $getMovieAllowedStatusesUseCase->execute();
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
                        
                        $addMovieUseCase->execute($movieDataForUseCase, $userId);
                        $importedCount++;
                        
                    } else if ($isBook) {
                        // Procesar como libro
                        if ($userRepository->hasUserBook($userId, $itemData['isbn'])) {
                            $skippedCount++;
                            continue; // Skip books user already has
                        }
                        
                        $allowedStatuses = $bookRepository->fetchAllowedStatuses();
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
                        
                        $addBookUseCase->execute($bookDataForUseCase, $userId);
                        $importedCount++;
                        
                    } else {
                        $errors[] = "Error en elemento {$index}: No se pudo determinar si es libro o película";
                    }
                    
                } catch (Exception $e) {
                    $itemId = $itemData['id'] ?? $itemData['isbn'] ?? 'unknown';
                    $errors[] = "Error en elemento {$index} (ID: {$itemId}): " . $e->getMessage();
                    error_log("Import error for item {$itemId}: " . $e->getMessage());
                }
            }
            
            $response['status'] = 'success';
            $response['message'] = "Importación completada desde {$service}. Elementos importados: {$importedCount}, Omitidos: {$skippedCount}";
            $response['data'] = [
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'total' => count($processedData),
                'errors' => $errors
            ];
            $statusCode = 200;
            break;
        case 'ping': // Example of a simple non-data action
            $response['status'] = 'success';
            $response['message'] = 'pong';
            $response['data'] = null;
            $statusCode = 200;
            break;

        default:
            if (isset($inputData['message']) && $action === null) { // Keep old message echo behavior if no other action matches
                 $response['status'] = 'success';
                 $response['message'] = 'Original message endpoint: Message received: ' . $inputData['message'];
                 $statusCode = 200;
            } else {
                throw new InvalidArgumentException('No valid action specified or missing required parameters. Action: ' . ($action ?? 'null'));
            }
    }

    // No need for additional response override logic here

} catch (InvalidArgumentException $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
    $statusCode = 400;
} catch (RuntimeException $e) {
    error_log("Runtime Exception in API: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
    $response['status'] = 'error';
    $response['message'] = 'A server runtime error occurred. Please try again later.'; // User-friendly message
    $statusCode = 500;
} catch (Throwable $e) {
    error_log("General Throwable in API: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\nStack Trace:\n" . $e->getTraceAsString());
    $response['status'] = 'error';
    $response['message'] = 'An unexpected server error occurred.'; // Generic message for production/cleanup
    // unset($response['trace']); // Ensure trace is not sent if it was added previously for debug
    $statusCode = 500;
}

http_response_code($statusCode);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>