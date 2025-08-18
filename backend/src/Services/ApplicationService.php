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
     * Handle HTTP request and route to appropriate controller
     */
    public function handleRequest(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Parse the request to extract action
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
            // Route based on action prefix to appropriate controller
            if (str_starts_with($action, 'login') || str_starts_with($action, 'logout') || 
                str_starts_with($action, 'check_auth') || str_starts_with($action, 'log_frontend')) {
                $controller = $this->getAuthController();
                $controller->handleRequest($requestMethod, $requestUri);
            } 
            elseif (str_starts_with($action, 'add_book') || str_starts_with($action, 'delete_book') || 
                    str_starts_with($action, 'update_book') || str_starts_with($action, 'get_book') ||
                    str_starts_with($action, 'get_library') && $action === 'get_library') {
                $controller = $this->getBookController();
                $controller->handleRequest($requestMethod, $requestUri);
            } 
            elseif (str_starts_with($action, 'add_movie') || str_starts_with($action, 'delete_movie') || 
                    str_starts_with($action, 'update_movie') || str_starts_with($action, 'get_movie')) {
                $controller = $this->getMovieController();
                $controller->handleRequest($requestMethod, $requestUri);
            } 
            elseif (str_starts_with($action, 'get_library_items') || str_starts_with($action, 'import_') || 
                    $action === 'ping') {
                $controller = $this->getLibraryController();
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
}
