<?php
declare(strict_types=1);

namespace App\Services;

use DI\Container;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use App\Controllers\AuthController;
use App\Controllers\BookController;
use App\Controllers\MovieController;
use App\Controllers\LibraryController;
use App\Controllers\LibraryXController;
use App\Controllers\StatsController;

/**
 * Application Service - Main entry point for dependency injection
 */
class ApplicationService
{
    private ContainerInterface $container;

    public function __construct()
    {
        $this->initializeContainer();
    }

    /**
     * Initialize the DI container
     */
    private function initializeContainer(): void
    {
        $containerBuilder = new ContainerBuilder();
        
        // Load configuration
        $dependencies = require __DIR__ . '/../../config/dependencies.php';
        $containerBuilder->addDefinitions($dependencies);
        
        // Enable compilation for production (optional)
        if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
            $containerBuilder->enableCompilation(__DIR__ . '/../../storage/cache');
        }
        
        $this->container = $containerBuilder->build();
    }

    /**
     * Get the DI container
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get a service from the container
     */
    public function get(string $id)
    {
        return $this->container->get($id);
    }

    /**
     * Check if a service exists in the container
     */
    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    /**
     * Get Auth Controller
     */
    public function getAuthController(): AuthController
    {
        return $this->container->get(AuthController::class);
    }

    /**
     * Get Book Controller
     */
    public function getBookController(): BookController
    {
        return $this->container->get(BookController::class);
    }

    /**
     * Get Movie Controller
     */
    public function getMovieController(): MovieController
    {
        return $this->container->get(MovieController::class);
    }

    /**
     * Get Library Controller
     */
    public function getLibraryController(): LibraryController
    {
        return $this->container->get(LibraryController::class);
    }

    /**
     * Get LibraryX Controller
     */
    public function getLibraryXController(): LibraryXController
    {
        return $this->container->get(LibraryXController::class);
    }

    /**
     * Get Stats Controller
     */
    public function getStatsController(): StatsController
    {
        return $this->container->get(StatsController::class);
    }

    /**
     * Handle HTTP request and route to appropriate controller
     */
    public function handleRequest(): void
    {
        // Handle OPTIONS requests immediately for CORS preflight
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Handle RESTful API routes first
        if (preg_match('#^/api/users/(\d+)/book-tags/?(\w+)?/?$#', $requestUri, $matches)) {
            $this->handleBookTagsAPI($requestMethod, (int)$matches[1], $matches[2] ?? null);
            return;
        }
        
        if (preg_match('#^/api/users/(\d+)/books/([^/]+)/tags/?$#', $requestUri, $matches)) {
            $this->handleBookSpecificTagsAPI($requestMethod, (int)$matches[1], $matches[2]);
            return;
        }

        if (preg_match('#^/api/users/(\d+)/movie-tags/?(\w+)?/?$#', $requestUri, $matches)) {
            $this->handleMovieTagsAPI($requestMethod, (int)$matches[1], $matches[2] ?? null);
            return;
        }
        
        if (preg_match('#^/api/users/(\d+)/movies/([^/]+)/tags/?$#', $requestUri, $matches)) {
            $this->handleMovieSpecificTagsAPI($requestMethod, (int)$matches[1], $matches[2]);
            return;
        }
        
        // Parse the request to extract action (legacy support)
        $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $inputData['action'] ?? $_REQUEST['action'] ?? null;
        
        if (!$action) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'No action specified'
            ]);
            return;
        }
        
        try {
            // Route statistics actions to StatsController
            if ($action === 'get_book_stats' || $action === 'get_movie_stats') {
                $controller = $this->getStatsController();
                $controller->handleRequest($requestMethod, $requestUri);
            }
            // Route based on action prefix to appropriate controller
            elseif (str_starts_with($action, 'login') || str_starts_with($action, 'logout') || 
                str_starts_with($action, 'check_auth') || str_starts_with($action, 'log_frontend')) {
                $controller = $this->getAuthController();
                $controller->handleRequest($requestMethod, $requestUri);
            } 
            elseif (str_starts_with($action, 'add_book') || str_starts_with($action, 'delete_book') || 
                    str_starts_with($action, 'update_book') || str_starts_with($action, 'get_book') ||
                    str_starts_with($action, 'edit_user_book') ||
                    str_starts_with($action, 'get_user_book_tags') || str_starts_with($action, 'create_user_book_tag') ||
                    str_starts_with($action, 'get_library') && $action === 'get_library' ||
                    // Reading sessions actions
                    str_starts_with($action, 'create_reading_session') || str_starts_with($action, 'get_active_reading') ||
                    str_starts_with($action, 'complete_reading_session') || str_starts_with($action, 'update_reading_progress') ||
                    str_starts_with($action, 'get_reading_session') || str_starts_with($action, 'get_session') ||
                    str_starts_with($action, 'get_user_active_reading') || str_starts_with($action, 'pause_reading') ||
                    str_starts_with($action, 'resume_reading') || str_starts_with($action, 'delete_reading') ||
                    str_starts_with($action, 'get_book_reading') || str_starts_with($action, 'get_detailed_progress') ||
                    str_starts_with($action, 'get_user_reading') || str_starts_with($action, 'get_current_reading')) {
                $controller = $this->getBookController();
                $controller->handleRequest($requestMethod, $requestUri);
            } 
            elseif (str_starts_with($action, 'add_movie') || str_starts_with($action, 'delete_movie') || 
                    str_starts_with($action, 'update_movie') || str_starts_with($action, 'get_movie') ||
                    str_starts_with($action, 'edit_user_movie') ||
                    str_starts_with($action, 'get_user_movie_tags') || str_starts_with($action, 'create_user_movie_tag')) {
                $controller = $this->getMovieController();
                $controller->handleRequest($requestMethod, $requestUri);
            } 
            elseif (str_starts_with($action, 'get_library_items') || str_starts_with($action, 'import_') || 
                    $action === 'ping') {
                $controller = $this->getLibraryController();
                $controller->handleRequest($requestMethod, $requestUri);
            } 
            elseif (str_starts_with($action, 'libraryx_')) {
                $controller = $this->getLibraryXController();
                $controller->handleRequest($requestMethod, $requestUri);
            } 
            else {
                // Default routing for backward compatibility
                $controller = $this->getLibraryController();
                $controller->handleRequest($requestMethod, $requestUri);
            }
        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Handle application errors
     */
    private function handleError(\Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        
        $response = [
            'error' => true,
            'message' => 'Internal server error'
        ];
        
        // Add debug info in development
        if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
            $response['debug'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }
        
        // Log the error
        error_log("Application Error: " . $e->getMessage());
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    /**
     * Handle book tags API endpoints
     */
    private function handleBookTagsAPI(string $method, int $userId, ?string $tagId): void
    {
        try {
            $controller = $this->getBookController();
            $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
            
            switch ($method) {
                case 'GET':
                    // GET /api/users/{userId}/book-tags
                    $inputData['action'] = 'get_user_book_tags';
                    $response = $controller->getUserBookTags($userId);
                    break;
                    
                case 'POST':
                    // POST /api/users/{userId}/book-tags
                    $inputData['action'] = 'create_user_book_tag';
                    $response = $controller->createUserBookTag(
                        $userId, 
                        $inputData['name'] ?? '', 
                        $inputData['color'] ?? '#1976d2'
                    );
                    break;
                    
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                    return;
            }
            
            $statusCode = $response['status'] === 'success' ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Handle book-specific tags API endpoints
     */
    private function handleBookSpecificTagsAPI(string $method, int $userId, string $isbn): void
    {
        try {
            $controller = $this->getBookController();
            
            switch ($method) {
                case 'GET':
                    // GET /api/users/{userId}/books/{isbn}/tags
                    $response = $controller->getBookTags($userId, $isbn);
                    break;
                    
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                    return;
            }
            
            $statusCode = $response['status'] === 'success' ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Handle movie tags API endpoints
     */
    private function handleMovieTagsAPI(string $method, int $userId, ?string $tagId): void
    {
        try {
            $controller = $this->getMovieController();
            $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
            
            switch ($method) {
                case 'GET':
                    // GET /api/users/{userId}/movie-tags
                    $response = $controller->getUserMovieTags($userId);
                    break;
                    
                case 'POST':
                    // POST /api/users/{userId}/movie-tags
                    $response = $controller->createUserMovieTag(
                        $userId, 
                        $inputData['name'] ?? '', 
                        $inputData['color'] ?? '#1976d2'
                    );
                    break;
                    
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                    return;
            }
            
            $statusCode = $response['status'] === 'success' ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Handle movie-specific tags API endpoints
     */
    private function handleMovieSpecificTagsAPI(string $method, int $userId, string $movieIsbn): void
    {
        try {
            $controller = $this->getMovieController();
            
            switch ($method) {
                case 'GET':
                    // GET /api/users/{userId}/movies/{movieIsbn}/tags
                    $response = $controller->getMovieTags($userId, $movieIsbn);
                    break;
                    
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                    return;
            }
            
            $statusCode = $response['status'] === 'success' ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }
}
