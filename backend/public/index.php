<?php
declare(strict_types=1);

// Cargar sistema de configuración
require_once __DIR__ . '/bootstrap.php';

// Handle OPTIONS requests immediately for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Headers are set by .htaccess, just return success
    http_response_code(200);
    exit();
}

// Simple PSR-4 autoloader (updated for new structure)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    // Docker: index.php in /var/www/html/, src in /var/www/html/src/
    $base_dir = __DIR__ . '/src/'; 

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

// Require Composer autoloader for dependencies (updated path for Docker)
require_once __DIR__ . '/../vendor/autoload.php';

// Inicializar sistema de logging ahora que Composer está cargado
if (function_exists('shouldInitializeLogging') && shouldInitializeLogging()) {
    initializeLogging();
}

// Inicializar logging para la request (después de que Composer esté cargado)
$startTime = microtime(true);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Log de inicio de request
if (function_exists('logger')) {
    try {
        logger('api')->httpRequest($requestMethod, $requestUri, [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? null
        ]);
    } catch (\Throwable $e) {
        // Fallback si el logging falla
        error_log("Logging error on request start: " . $e->getMessage());
    }
}


use App\Infrastructure\Persistence\MySqlBookRepository;
use App\Infrastructure\Persistence\MySqlMovieRepository;
use App\Infrastructure\Persistence\MySqlUserRepository;
use App\Domain\UseCases\Books\AddBookUseCase;
use App\Domain\UseCases\GetLibraryUseCase;
use App\Domain\UseCases\Books\DeleteBookUseCase;
use App\Domain\UseCases\Books\UpdateBookRatingUseCase;
use App\Domain\UseCases\Movies\AddMovieUseCase;
use App\Domain\UseCases\Movies\DeleteMovieUseCase;
use App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase;
use App\Domain\UseCases\Books\UpdateBookUserStatusesUseCase;
use App\Domain\UseCases\GetLibraryItemsUseCase;
use App\Domain\UseCases\Books\GetBooksUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase;
use App\Domain\Model\Book;
use App\Domain\Model\Movie;
use App\Domain\UseCases\Movies\UpdateMovieRatingUseCase;
use App\Domain\UseCases\Auth\LoginUserUseCase;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Middleware\AuthMiddleware;

// Configurar headers usando el sistema de configuración
header('Content-Type: application/json');

// CORS está configurado automáticamente por .htaccess
// Las siguientes líneas son redundantes y causan duplicación
// $corsOrigin = $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:8080';
// if ($corsOrigin !== '*') {
//     header("Access-Control-Allow-Origin: {$corsOrigin}");
// }

// OPTIONS requests are handled by .htaccess, this is redundant
// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     http_response_code(204);
//     exit(0);
// }

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
    $updateMovieUserStatusesUseCase = new UpdateMovieUserStatusesUseCase($movieRepository, $userRepository);
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

        case 'log_frontend':
            // Frontend logging endpoint - no authentication required for basic logging
            if (!isset($inputData['log_data']) || !is_array($inputData['log_data'])) {
                $response['status'] = 'error';
                $response['message'] = 'Invalid log data format.';
                $statusCode = 400;
                break;
            }
            
            $logData = $inputData['log_data'];
            
            try {
                // Preserve complete frontend log data
                $context = [
                    'frontend_data' => $logData, // Guardar todo el log original del frontend
                    'source' => $logData['source'] ?? 'frontend',
                    'url' => $logData['url'] ?? 'unknown',
                    'userAgent' => $logData['userAgent'] ?? 'unknown',
                    'timestamp' => $logData['timestamp'] ?? date('c'),
                    'original_message' => $logData['message'] ?? 'Frontend log entry',
                    'frontend_level' => $logData['level'] ?? 'info',
                    'args' => $logData['data']['args'] ?? [],
                    'additional_data' => $logData['data'] ?? []
                ];
                
                // Add user context if authenticated
                if ($sessionManager->isLoggedIn()) {
                    $context['user_id'] = $sessionManager->getCurrentUserId();
                    $context['user_email'] = $sessionManager->getCurrentUser()['email'] ?? 'unknown';
                }
                
                $message = $logData['message'] ?? 'Frontend log entry';
                $level = $logData['level'] ?? 'info';
                
                // Log to appropriate channel based on level and content
                switch ($level) {
                    case 'error':
                        logger('frontend')->error($message, $context);
                        break;
                    case 'warn':
                        logger('frontend')->warning($message, $context);
                        break;
                    case 'auth':
                        logger('auth')->info($message, $context);
                        break;
                    default:
                        logger('frontend')->info($message, $context);
                        break;
                }
                
                $response['status'] = 'success';
                $response['message'] = 'Log entry recorded.';
                $statusCode = 200;
                
            } catch (Exception $e) {
                // Don't let logging failures break the app
                $response['status'] = 'error';
                $response['message'] = 'Failed to record log entry.';
                $statusCode = 500;
                
                // Log this error to backend logs
                error_log('Frontend logging endpoint error: ' . $e->getMessage());
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
    
    // Log error de validación
    if (function_exists('logger')) {
        try {
            logger('api')->warning('Bad Request', [
                'message' => $e->getMessage(),
                'action' => $action ?? 'unknown',
                'method' => $requestMethod,
                'uri' => $requestUri
            ]);
        } catch (\Throwable $logError) {
            error_log("Logging error in validation: " . $logError->getMessage());
        }
    }
    
} catch (RuntimeException $e) {
    error_log("Runtime Exception in API: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
    
    // Log error de runtime
    if (function_exists('logger')) {
        try {
            logger('api')->error('Runtime Error', [
                'message' => $e->getMessage(),
                'action' => $action ?? 'unknown',
                'method' => $requestMethod,
                'uri' => $requestUri,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        } catch (\Throwable $logError) {
            error_log("Logging error in runtime: " . $logError->getMessage());
        }
    }
    
    $response['status'] = 'error';
    $response['message'] = 'A server runtime error occurred. Please try again later.'; // User-friendly message
    $statusCode = 500;
} catch (Throwable $e) {
    error_log("General Throwable in API: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\nStack Trace:\n" . $e->getTraceAsString());
    
    // Log error general
    if (function_exists('logger')) {
        try {
            logger('api')->exception($e, 'Unexpected error in API');
        } catch (\Throwable $logError) {
            error_log("Logging error in exception: " . $logError->getMessage());
        }
    }
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected server error occurred.'; // Generic message for production/cleanup
    // unset($response['trace']); // Ensure trace is not sent if it was added previously for debug
    $statusCode = 500;
}

// Log de respuesta
$duration = microtime(true) - $startTime;
if (function_exists('logger')) {
    try {
        logger('api')->httpResponse($statusCode, $duration, [
            'action' => $action ?? 'unknown',
            'response_size' => strlen(json_encode($response))
        ]);
    } catch (\Throwable $logError) {
        error_log("Logging error on response: " . $logError->getMessage());
    }
}

http_response_code($statusCode);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>