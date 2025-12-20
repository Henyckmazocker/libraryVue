<?php
declare(strict_types=1);

namespace App;

use App\Infrastructure\Persistence\Book\MySqlBookRepository;
use App\Infrastructure\Persistence\Movie\MySqlMovieRepository;
use App\Infrastructure\Persistence\User\MySqlUserRepository;
use App\Infrastructure\Persistence\Book\MySqlUserBookRepository;
use App\Infrastructure\Persistence\Movie\MySqlUserMovieRepository;
use App\Infrastructure\Persistence\Book\MySqlBookTagRepository;
use App\Infrastructure\Persistence\Book\MySqlBookNoteRepository;
use App\Infrastructure\Persistence\Book\MySqlReadingSessionRepository;
use App\Infrastructure\Persistence\Book\MySqlReadingProgressRepository;
use App\Infrastructure\Persistence\Movie\MySqlMovieTagRepository;
use App\Infrastructure\Persistence\Movie\MySqlMovieNoteRepository;
use App\Infrastructure\Database\DatabaseConnector;
use App\Infrastructure\Logging\LoggerFactory;
use App\Domain\UseCases\Books\AddBookUseCase;
use App\Domain\UseCases\Books\GetBooksUseCase;
use App\Domain\UseCases\Books\GetAllBooksUseCase;
use App\Domain\UseCases\GetLibraryUseCase;
use App\Domain\UseCases\Books\DeleteBookUseCase;
use App\Domain\UseCases\Books\UpdateBookRatingUseCase;
use App\Domain\UseCases\Movies\AddMovieUseCase;
use App\Domain\UseCases\Movies\DeleteMovieUseCase;
use App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase;
use App\Domain\UseCases\Books\UpdateBookUserStatusesUseCase;
use App\Domain\UseCases\Movies\EditUserMovieUseCase;
use App\Domain\UseCases\GetLibraryItemsUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase;
use App\Domain\UseCases\Movies\UpdateMovieRatingUseCase;
use App\Domain\UseCases\Books\EditUserBookUseCase;
use App\Domain\UseCases\Auth\LoginUserUseCase;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Controllers\AuthController;
use App\Controllers\BookController;
use App\Controllers\MovieController;
use App\Controllers\LibraryController;
use App\Controllers\LibraryXController;
use App\Controllers\StatsController;
use App\Router\ActionRouter;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class Application
{
    private float $startTime;
    private string $requestMethod;
    private string $requestUri;
    private ActionRouter $router;
    
    // Dependencies
    private SessionManager $sessionManager;
    private MySqlUserRepository $userRepository;
    private MySqlBookRepository $bookRepository;
    private MySqlMovieRepository $movieRepository;
    private MySqlUserBookRepository $userBookRepository;
    private MySqlUserMovieRepository $userMovieRepository;
    private MySqlBookTagRepository $bookTagRepository;
    private MySqlBookNoteRepository $bookNoteRepository;
    private MySqlReadingSessionRepository $readingSessionRepository;
    private MySqlReadingProgressRepository $readingProgressRepository;
    private MySqlMovieTagRepository $movieTagRepository;
    private MySqlMovieNoteRepository $movieNoteRepository;
    private AuthMiddleware $authMiddleware;
    
    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $this->requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        
        $this->bootstrap();
        $this->initializeDependencies();
        $this->setupRouter();
    }
    
    private function bootstrap(): void
    {
        // Handle OPTIONS requests immediately for CORS preflight
        if ($this->requestMethod === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        // Start session ONCE at application bootstrap before any middleware
        // This prevents multiple session_start() calls from creating different sessions
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            // Configure session settings for security
            ini_set('session.cookie_httponly', '1');
            // Don't require HTTPS in development
            $isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';
            ini_set('session.cookie_secure', $isProduction ? '1' : '0');
            // Use Lax samesite for development (allows some cross-origin)
            ini_set('session.cookie_samesite', $isProduction ? 'Strict' : 'Lax');
            // Force cookie domain to be empty for localhost
            ini_set('session.cookie_domain', '');
            // Set cookie path to root
            ini_set('session.cookie_path', '/');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', '604800'); // 7 days
            
            session_name('LIBRARY_SESSION');
            session_start();
        }
        
        // Set response headers
        header('Content-Type: application/json');
        
        // Initialize logging for the request
        if (function_exists('logger')) {
            try {
                logger('api')->httpRequest($this->requestMethod, $this->requestUri, [
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null
                ]);
            } catch (Throwable $e) {
                error_log("Logging error on request start: " . $e->getMessage());
            }
        }
    }
    
    private function initializeDependencies(): void
    {
        // Initialize database connection
        $databaseConnector = new DatabaseConnector();
        $pdo = $databaseConnector->getConnection();
        
        // Initialize session and auth components
        $this->sessionManager = new SessionManager();
        $databaseLogger = LoggerFactory::createDatabaseLogger();
        
        // Initialize mappers
        $bookMapper = new \App\Infrastructure\Persistence\Book\Mappers\BookDataMapper();
        $movieMapper = new \App\Infrastructure\Persistence\Movie\Mappers\MovieDataMapper();
        $userMapper = new \App\Infrastructure\Persistence\User\Mappers\UserDataMapper();
        
        // Initialize base repositories
        $this->userRepository = new MySqlUserRepository($pdo, $databaseLogger, $userMapper);
        $this->bookRepository = new MySqlBookRepository($pdo, $bookMapper, $databaseLogger);
        $this->movieRepository = new MySqlMovieRepository($pdo, $movieMapper, $databaseLogger);
        
        // Initialize relationship repositories (need mappers and base repositories)
        $this->userBookRepository = new MySqlUserBookRepository($pdo, $bookMapper, $databaseLogger, $this->bookRepository);
        $this->userMovieRepository = new MySqlUserMovieRepository($pdo, $movieMapper, $databaseLogger);
        
        // Initialize specialized repositories (only need PDO and logger)
        $this->bookTagRepository = new MySqlBookTagRepository($pdo, $databaseLogger);
        $this->bookNoteRepository = new MySqlBookNoteRepository($pdo, $databaseLogger);
        $this->readingSessionRepository = new MySqlReadingSessionRepository($pdo, $databaseLogger);
        $this->readingProgressRepository = new MySqlReadingProgressRepository($pdo, $databaseLogger);
        $this->movieTagRepository = new MySqlMovieTagRepository($pdo, $databaseLogger);
        $this->movieNoteRepository = new MySqlMovieNoteRepository($pdo, $databaseLogger);
        
        // Initialize auth middleware
        $this->authMiddleware = new AuthMiddleware($this->sessionManager, $this->userRepository);
    }
    
    private function setupRouter(): void
    {
        // Get logger for Use Cases
        $logger = LoggerFactory::createDatabaseLogger();
        
        // Auth use cases
        $loginUserUseCase = new LoginUserUseCase($this->userRepository, $logger);
        
        // Book use cases
        $addBookUseCase = new AddBookUseCase($this->bookRepository, $this->userRepository, $this->userBookRepository, $logger);
        $getBooksUseCase = new GetBooksUseCase($this->userRepository, $this->userBookRepository, $logger);
        $getAllBooksUseCase = new GetAllBooksUseCase($this->bookRepository, $logger);
        $deleteBookUseCase = new DeleteBookUseCase($this->userRepository, $this->userBookRepository, $logger);
        $updateBookRatingUseCase = new UpdateBookRatingUseCase($this->userRepository, $this->userBookRepository, $logger);
        $updateBookUserStatusesUseCase = new UpdateBookUserStatusesUseCase($this->userRepository, $this->userBookRepository, $logger);
        $editUserBookUseCase = new EditUserBookUseCase($this->userRepository, $this->userBookRepository, $this->bookTagRepository, $this->bookNoteRepository, $logger);
        $getBookAllowedStatusesUseCase = new \App\Domain\UseCases\Books\GetBookAllowedStatusesUseCase($this->bookRepository, $logger);

        // Movie use cases
        $addMovieUseCase = new AddMovieUseCase($this->movieRepository, $this->userRepository, $this->userMovieRepository, $logger);
        $getMoviesUseCase = new GetMoviesUseCase($this->userRepository, $this->userMovieRepository, $logger);
        $deleteMovieUseCase = new DeleteMovieUseCase($this->userRepository, $this->userMovieRepository, $logger);
        $updateMovieRatingUseCase = new UpdateMovieRatingUseCase($this->userRepository, $this->userMovieRepository, $logger);
        $updateMovieUserStatusesUseCase = new UpdateMovieUserStatusesUseCase($this->userRepository, $this->userMovieRepository, $logger);
        $getMovieAllowedStatusesUseCase = new GetMovieAllowedStatusesUseCase($this->movieRepository, $logger);
        $editUserMovieUseCase = new EditUserMovieUseCase($this->userMovieRepository, $this->movieTagRepository, $this->movieNoteRepository, $logger);

        // Library use cases
        $getLibraryUseCase = new GetLibraryUseCase($this->bookRepository, $this->movieRepository, $logger);
        $getLibraryItemsUseCase = new GetLibraryItemsUseCase($getBooksUseCase, $getMoviesUseCase, $logger);
        
        // Create controllers with dependency injection
        $authController = new AuthController(
            $loginUserUseCase,
            $this->sessionManager,
            $this->authMiddleware
        );
        
        $bookController = new BookController(
            $addBookUseCase,
            $deleteBookUseCase,
            $updateBookRatingUseCase,
            $updateBookUserStatusesUseCase,
            $getBooksUseCase,
            $getAllBooksUseCase,
            $getBookAllowedStatusesUseCase,
            $this->bookRepository,
            $this->bookTagRepository,
            $this->readingSessionRepository,
            $this->readingProgressRepository,
            $this->authMiddleware,
            $editUserBookUseCase
        );
        
        $movieController = new MovieController(
            $addMovieUseCase,
            $deleteMovieUseCase,
            $updateMovieRatingUseCase,
            $updateMovieUserStatusesUseCase,
            $getMoviesUseCase,
            $getMovieAllowedStatusesUseCase,
            $this->authMiddleware,
            $editUserMovieUseCase,
            $this->movieTagRepository,
            $this->movieNoteRepository
        );
        
        $libraryController = new LibraryController(
            $getLibraryUseCase,
            $getBooksUseCase,
            $getMoviesUseCase,
            $addBookUseCase,
            $addMovieUseCase,
            $getMovieAllowedStatusesUseCase,
            $getBookAllowedStatusesUseCase,
            $this->userBookRepository,
            $this->userMovieRepository,
            $this->authMiddleware
        );
        
        // LibraryX controller
        $libraryXController = new LibraryXController($this->authMiddleware);
        
        // Stats controller
        $statsController = new StatsController(
            $this->userBookRepository,
            $this->userMovieRepository,
            $this->readingProgressRepository,
            $this->authMiddleware
        );
        
        // Load routes configuration
        $routes = require __DIR__ . '/../config/routes.php';
        
        // Create middleware instances
        $authenticationMiddleware = new \App\Infrastructure\Middleware\AuthenticationMiddleware($logger);
        $csrfMiddleware = new \App\Infrastructure\Middleware\CSRFMiddleware($logger);
        $loggingMiddleware = new \App\Infrastructure\Middleware\LoggingMiddleware($logger);
        $validationMiddleware = new \App\Infrastructure\Middleware\ValidationMiddleware($logger);
        
        // Create simple container for ActionRouter
        $container = new class(
            $authController, 
            $bookController, 
            $movieController, 
            $libraryController, 
            $libraryXController, 
            $statsController,
            $authenticationMiddleware,
            $csrfMiddleware,
            $loggingMiddleware,
            $validationMiddleware
        ) implements \Psr\Container\ContainerInterface {
            private array $services = [];
            
            public function __construct($auth, $book, $movie, $library, $libraryX, $stats, $authMid, $csrf, $logging, $validation) {
                $this->services = [
                    \App\Controllers\AuthController::class => $auth,
                    \App\Controllers\BookController::class => $book,
                    \App\Controllers\MovieController::class => $movie,
                    \App\Controllers\LibraryController::class => $library,
                    \App\Controllers\LibraryXController::class => $libraryX,
                    \App\Controllers\StatsController::class => $stats,
                    \App\Infrastructure\Middleware\AuthenticationMiddleware::class => $authMid,
                    \App\Infrastructure\Middleware\CSRFMiddleware::class => $csrf,
                    \App\Infrastructure\Middleware\LoggingMiddleware::class => $logging,
                    \App\Infrastructure\Middleware\ValidationMiddleware::class => $validation,
                ];
            }
            
            public function get(string $id) {
                return $this->services[$id] ?? throw new \RuntimeException("Service not found: $id");
            }
            
            public function has(string $id): bool {
                return isset($this->services[$id]);
            }
        };
        
        // Create and configure router
        $this->router = new ActionRouter(
            $routes,
            $container,
            $logger
        );
    }
    
    public function run(): void
    {
        $response = [
            'status' => 'error',
            'message' => 'An unexpected error occurred.',
            'data' => null
        ];
        $statusCode = 500;
        
        try {
            // Decode incoming JSON data
            $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // Determine action
            $action = $inputData['action'] ?? $_REQUEST['action'] ?? null;
            
            $result = $this->router->dispatch($action, $inputData);
            $response = $result;
            $statusCode = $result['http_code'] ?? ($result['status'] === 'success' ? 200 : 400);
            
        } catch (InvalidArgumentException $e) {
            $response['status'] = 'error';
            $response['message'] = $e->getMessage();
            $statusCode = 400;
            
            $this->logError('validation', $e, $action ?? 'unknown');
            
        } catch (RuntimeException $e) {
            $this->logError('runtime', $e, $action ?? 'unknown');
            
            $response['status'] = 'error';
            $response['message'] = 'A server runtime error occurred. Please try again later.';
            $statusCode = 500;
            
        } catch (Throwable $e) {
            $this->logException($e);
            
            $response['status'] = 'error';
            $response['message'] = 'An unexpected server error occurred.';
            $statusCode = 500;
        }
        
        $this->sendResponse($response, $statusCode);
    }
    
    private function logError(string $type, Throwable $e, string $action): void
    {
        if (!function_exists('logger')) {
            // Fallback to error_log if logger is not available
            error_log("[$type] Error in action '$action': " . $e->getMessage());
            return;
        }
        
        try {
            $context = [
                'message' => $e->getMessage(),
                'action' => $action,
                'method' => $this->requestMethod,
                'uri' => $this->requestUri,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'exception_class' => get_class($e)
            ];
            
            if ($type === 'runtime') {
                $context['stack_trace'] = $e->getTraceAsString();
                logger('api')->error('Runtime Error', $context);
            } else {
                logger('api')->warning('Validation Error', $context);
            }
        } catch (Throwable $logError) {
            error_log("Logging error in {$type}: " . $logError->getMessage());
        }
    }
    
    private function logException(Throwable $e): void
    {
        if (function_exists('logger')) {
            try {
                $context = [
                    'exception_class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'stack_trace' => $e->getTraceAsString(),
                    'action' => $_REQUEST['action'] ?? 'unknown',
                    'method' => $this->requestMethod,
                    'uri' => $this->requestUri
                ];
                logger('api')->critical('Unexpected Exception', $context);
            } catch (Throwable $logError) {
                error_log("Logging error in exception: " . $logError->getMessage());
            }
        } else {
            // Fallback to error_log if logger is not available
            error_log("Critical Exception: " . get_class($e) . " in " . $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage());
        }
    }
    
    private function sendResponse(array $response, int $statusCode): void
    {
        // Log response
        $duration = microtime(true) - $this->startTime;
        if (function_exists('logger')) {
            try {
                logger('api')->httpResponse($statusCode, $duration, [
                    'action' => $_REQUEST['action'] ?? 'unknown',
                    'response_size' => strlen(json_encode($response))
                ]);
            } catch (Throwable $logError) {
                error_log("Logging error on response: " . $logError->getMessage());
            }
        }
        
        http_response_code($statusCode);
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    // Getters para acceso desde controllers si es necesario
    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }
    
    public function getUserRepository(): MySqlUserRepository
    {
        return $this->userRepository;
    }
    
    public function getBookRepository(): MySqlBookRepository
    {
        return $this->bookRepository;
    }
    
    public function getMovieRepository(): MySqlMovieRepository
    {
        return $this->movieRepository;
    }
    
    public function getAuthMiddleware(): AuthMiddleware
    {
        return $this->authMiddleware;
    }
}
