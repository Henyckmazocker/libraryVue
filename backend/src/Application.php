<?php
declare(strict_types=1);

namespace App;

use App\Infrastructure\Persistence\MySqlBookRepository;
use App\Infrastructure\Persistence\MySqlMovieRepository;
use App\Infrastructure\Persistence\MySqlUserRepository;
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
use App\Domain\UseCases\GetLibraryItemsUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase;
use App\Domain\UseCases\Movies\UpdateMovieRatingUseCase;
use App\Domain\UseCases\Auth\LoginUserUseCase;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Controllers\AuthController;
use App\Controllers\BookController;
use App\Controllers\MovieController;
use App\Controllers\LibraryController;
use App\Controllers\LibraryXController;
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
        $this->userRepository = new MySqlUserRepository($pdo, $databaseLogger);
        $this->authMiddleware = new AuthMiddleware($this->sessionManager, $this->userRepository);
        
        // Initialize repositories
        $this->bookRepository = new MySqlBookRepository($pdo, $databaseLogger);
        $this->movieRepository = new MySqlMovieRepository($pdo, $databaseLogger);
    }
    
    private function setupRouter(): void
    {
        // Auth use cases
        $loginUserUseCase = new LoginUserUseCase($this->userRepository);
        
        // Book use cases
        $addBookUseCase = new AddBookUseCase($this->bookRepository, $this->userRepository);
        $getBooksUseCase = new GetBooksUseCase($this->bookRepository, $this->userRepository);
        $getAllBooksUseCase = new GetAllBooksUseCase($this->bookRepository);
        $deleteBookUseCase = new DeleteBookUseCase($this->bookRepository, $this->userRepository);
        $updateBookRatingUseCase = new UpdateBookRatingUseCase($this->bookRepository, $this->userRepository);
        $updateBookUserStatusesUseCase = new UpdateBookUserStatusesUseCase($this->bookRepository, $this->userRepository);
        
        // Movie use cases
        $addMovieUseCase = new AddMovieUseCase($this->movieRepository, $this->userRepository);
        $getMoviesUseCase = new GetMoviesUseCase($this->movieRepository, $this->userRepository);
        $deleteMovieUseCase = new DeleteMovieUseCase($this->movieRepository, $this->userRepository);
        $updateMovieRatingUseCase = new UpdateMovieRatingUseCase($this->movieRepository, $this->userRepository);
        $updateMovieUserStatusesUseCase = new UpdateMovieUserStatusesUseCase($this->movieRepository, $this->userRepository);
        $getMovieAllowedStatusesUseCase = new GetMovieAllowedStatusesUseCase($this->movieRepository);
        
        // Library use cases
        $getLibraryUseCase = new GetLibraryUseCase($this->bookRepository);
        $getLibraryItemsUseCase = new GetLibraryItemsUseCase($getBooksUseCase, $getMoviesUseCase);
        
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
            $this->bookRepository,
            $this->authMiddleware
        );
        
        $movieController = new MovieController(
            $addMovieUseCase,
            $deleteMovieUseCase,
            $updateMovieRatingUseCase,
            $updateMovieUserStatusesUseCase,
            $getMoviesUseCase,
            $getMovieAllowedStatusesUseCase,
            $this->authMiddleware
        );
        
        $libraryController = new LibraryController(
            $getLibraryUseCase,
            $getBooksUseCase,
            $getMoviesUseCase,
            $addBookUseCase,
            $addMovieUseCase,
            $getMovieAllowedStatusesUseCase,
            $this->bookRepository,
            $this->userRepository,
            $this->authMiddleware
        );
        
        // LibraryX controller
        $libraryXController = new LibraryXController();
        
        // Create and configure router
        $this->router = new ActionRouter(
            $authController,
            $bookController,
            $movieController,
            $libraryController,
            $libraryXController,
            $this->authMiddleware
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
