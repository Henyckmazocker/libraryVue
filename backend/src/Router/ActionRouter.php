<?php

declare(strict_types=1);

namespace App\Router;

use App\Controllers\AuthController;
use App\Controllers\BookController;
use App\Controllers\MovieController;
use App\Controllers\LibraryController;
use App\Controllers\LibraryXController;
use App\Controllers\StatsController;
use App\Infrastructure\Middleware\MiddlewarePipeline;
use App\Domain\DTO\Commands\AddBookCommand;
use App\Domain\DTO\Commands\DeleteBookCommand;
use App\Domain\DTO\Commands\UpdateBookRatingCommand;
use App\Domain\DTO\Commands\UpdateBookStatusesCommand;
use App\Domain\DTO\Commands\EditUserBookCommand;
use App\Domain\DTO\Commands\AddMovieCommand;
use App\Domain\DTO\Commands\DeleteMovieCommand;
use App\Domain\DTO\Commands\UpdateMovieRatingCommand;
use App\Domain\DTO\Commands\UpdateMovieStatusesCommand;
use App\Domain\DTO\Commands\EditUserMovieCommand;
use App\Domain\DTO\Commands\CreateReadingSessionCommand;
use App\Domain\DTO\Commands\CompleteReadingSessionCommand;
use App\Domain\DTO\Commands\UpdateReadingProgressCommand;
use App\Domain\DTO\Commands\ManageReadingSessionCommand;
use App\Domain\DTO\Queries\GetBooksByUserQuery;
use App\Domain\DTO\Queries\GetMoviesByUserQuery;
use App\Domain\DTO\Queries\GetAllowedStatusesQuery;
use App\Domain\DTO\Queries\GetAllBooksQuery;
use App\Domain\DTO\Queries\GetReadingSessionQuery;
use App\Domain\DTO\Queries\GetUserReadingStatsQuery;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * ActionRouter - Routes incoming actions to appropriate controllers
 * 
 * This router uses the Middleware Pattern to eliminate code duplication
 * for authentication, CSRF validation, logging, and input validation.
 * Routes are configured declaratively in config/routes.php.
 * 
 * BEFORE refactoring: 225 lines with duplicate auth/CSRF checks
 * AFTER refactoring: ~80 lines with middleware pipeline (-64% reduction)
 */
class ActionRouter
{
    private array $routes;
    private ContainerInterface $container;
    private LoggerInterface $logger;
    
    // Controller instances (lazy-loaded)
    private ?AuthController $authController = null;
    private ?BookController $bookController = null;
    private ?MovieController $movieController = null;
    private ?LibraryController $libraryController = null;
    private ?LibraryXController $libraryXController = null;
    private ?StatsController $statsController = null;

    public function __construct(
        array $routes,
        ContainerInterface $container,
        LoggerInterface $logger
    ) {
        $this->routes = $routes;
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * Dispatch an action to the appropriate controller through middleware pipeline
     * 
     * @param string $action The action to execute
     * @param array $inputData The input data for the action
     * @return array Response array with status, message, http_code
     */
    public function dispatch(string $action, array $inputData): array
    {
        try {
            // Check if route exists
            if (!isset($this->routes[$action])) {
                return $this->handleUnknownAction($action, $inputData);
            }

            $route = $this->routes[$action];
            
            // Build request context
            $request = [
                'action' => $action,
                'data' => $inputData,
                'csrf_token' => $inputData['csrf_token'] ?? null,
            ];

            // Build middleware pipeline
            $pipeline = new MiddlewarePipeline();
            
            foreach ($route['middleware'] as $middlewareConfig) {
                if (is_array($middlewareConfig)) {
                    // Middleware with configuration [MiddlewareClass::class, ['config' => 'value']]
                    [$middlewareClass, $config] = $middlewareConfig;
                    $middleware = $this->container->get($middlewareClass);
                    
                    // Pass configuration to middleware (e.g., ValidationMiddleware needs 'required' fields)
                    if (method_exists($middleware, 'setConfig')) {
                        $middleware->setConfig($config);
                    }
                    
                    $pipeline->add($middleware);
                } else {
                    // Simple middleware class name
                    $pipeline->add($this->container->get($middlewareConfig));
                }
            }

            // Execute pipeline with controller action as final handler
            return $pipeline->execute($request, function (array $request) use ($route) {
                return $this->executeController($route['controller'], $request);
            });

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('ActionRouter Validation Error', [
                'message' => $e->getMessage(),
                'action' => $action,
                'exception_class' => get_class($e)
            ]);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'http_code' => 400
            ];
        } catch (\Exception $e) {
            $this->logger->error('ActionRouter Unexpected Error', [
                'message' => $e->getMessage(),
                'action' => $action,
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'http_code' => 500
            ];
        }
    }

    /**
     * Execute the controller action with Command DTOs
     * 
     * @param array $controllerConfig [ControllerName, methodName]
     * @param array $request The request context (includes user_id from AuthMiddleware)
     * @return array Controller response
     */
    private function executeController(array $controllerConfig, array $request): array
    {
        [$controllerName, $method] = $controllerConfig;
        $controller = $this->getController($controllerName);
        $data = $request['data'];
        
        // Extract user_id if present (added by AuthenticationMiddleware)
        $userId = $request['user_id'] ?? null;

        // Build Command DTOs from request data and route to controller
        return match ($request['action']) {
            // AUTH - No DTOs needed (simple operations)
            'login' => $controller->login($data),
            'logout' => $controller->logout(),
            'check_auth' => $controller->checkAuth(),
            'log_frontend' => $controller->logFrontend($data['log_data'] ?? []),
            
            // BOOKS - Use Command DTOs
            'add_book' => $controller->addBook(
                AddBookCommand::fromArray($data['book'] ?? [], $userId)
            ),
            'delete_book' => $controller->deleteBook(
                new DeleteBookCommand($userId, $data['isbn'] ?? '')
            ),
            'update_book_rating' => $controller->updateBookRating(
                new UpdateBookRatingCommand($userId, $data['isbn'] ?? '', $data['rating'] ?? null)
            ),
            'update_book_user_statuses' => $controller->updateBookUserStatuses(
                new UpdateBookStatusesCommand($userId, $data['isbn'] ?? '', $data['statuses'] ?? [])
            ),
            'edit_user_book' => $controller->editUserBook(
                EditUserBookCommand::fromArray($data, $userId)
            ),
            'get_book_allowed_statuses' => $controller->getBookAllowedStatuses(),
            'get_books' => $controller->getAllBooks(),
            'get_library' => $controller->getBooks(
                new GetBooksByUserQuery($userId)
            ),
            
            // MOVIES - Use Command DTOs
            'add_movie' => $controller->addMovie(
                AddMovieCommand::fromArray($data['movie'] ?? [], $userId)
            ),
            'delete_movie' => $controller->deleteMovie(
                new DeleteMovieCommand($userId, $data['imdbID'] ?? $data['id'] ?? '')
            ),
            'update_movie_rating' => $controller->updateMovieRating(
                UpdateMovieRatingCommand::fromArray($data, $userId)
            ),
            'update_movie_user_statuses' => $controller->updateMovieUserStatuses(
                UpdateMovieStatusesCommand::fromArray($data, $userId)
            ),
            'edit_user_movie' => $controller->editUserMovie(
                EditUserMovieCommand::fromArray($data, $userId)
            ),
            'get_movie_allowed_statuses' => $controller->getMovieAllowedStatuses(),
            'get_movies' => $controller->getMovies(
                new GetMoviesByUserQuery($userId)
            ),
            
            // LIBRARY - No DTOs (complex operations handled internally)
            'get_library_items' => $controller->getLibraryItems($userId),
            'save_library' => $controller->saveLibrary($userId),
            'import_data' => $controller->importData($data['processedData'] ?? [], $userId),
            'ping' => $controller->ping(),
            
            // LIBRARYX - No DTOs (legacy endpoints)
            'libraryx_get_urls' => $controller->getUrls($request['user'] ?? []),
            'libraryx_update_urls' => $controller->updateUrls($data, $request['user'] ?? []),
            
            // STATISTICS - No DTOs (simple read operations)
            'get_book_stats' => $controller->getBookStats($userId),
            'get_movie_stats' => $controller->getMovieStats($userId),
            
            // READING SESSIONS - Use Command/Query DTOs
            'create_reading_session' => $controller->createReadingSession(
                CreateReadingSessionCommand::fromArray($data, $userId)
            ),
            'get_active_reading_session' => $controller->getActiveReadingSession(
                GetReadingSessionQuery::fromArray($data, $userId)
            ),
            'complete_reading_session' => $controller->completeReadingSession(
                CompleteReadingSessionCommand::fromArray($data)
            ),
            'update_reading_progress_with_session' => $controller->updateReadingProgressWithSession(
                UpdateReadingProgressCommand::fromArray($data, $userId)
            ),
            'get_reading_session_history' => $controller->getReadingSessionHistory(
                GetReadingSessionQuery::fromArray($data, $userId)
            ),
            'get_session_progress' => $controller->getSessionProgress(
                ManageReadingSessionCommand::fromArray($data)
            ),
            'get_user_active_reading_sessions' => $controller->getUserActiveReadingSessions(
                GetUserReadingStatsQuery::fromArray($data, $userId)
            ),
            'pause_reading_session' => $controller->pauseReadingSession(
                ManageReadingSessionCommand::fromArray($data)
            ),
            'resume_reading_session' => $controller->resumeReadingSession(
                ManageReadingSessionCommand::fromArray($data)
            ),
            'delete_reading_session' => $controller->deleteReadingSession(
                ManageReadingSessionCommand::fromArray($data)
            ),
            'get_book_reading_summary' => $controller->getBookReadingSummary(
                GetReadingSessionQuery::fromArray($data, $userId)
            ),
            'get_detailed_progress_history' => $controller->getDetailedProgressHistory(
                GetReadingSessionQuery::fromArray($data, $userId)
            ),
            'get_user_reading_stats' => $controller->getUserReadingStats(
                GetUserReadingStatsQuery::fromArray($data, $userId)
            ),
            'get_current_reading_sessions' => $controller->getCurrentReadingSessions(
                GetUserReadingStatsQuery::fromArray($data, $userId)
            ),
            
            default => [
                'status' => 'error',
                'message' => "Controller method not mapped for action: {$request['action']}",
                'http_code' => 500
            ]
        };
    }

    /**
     * Get controller instance (lazy-loaded)
     */
    private function getController(string $controllerName): object
    {
        return match ($controllerName) {
            'AuthController' => $this->authController ??= $this->container->get(AuthController::class),
            'BookController' => $this->bookController ??= $this->container->get(BookController::class),
            'MovieController' => $this->movieController ??= $this->container->get(MovieController::class),
            'LibraryController' => $this->libraryController ??= $this->container->get(LibraryController::class),
            'LibraryXController' => $this->libraryXController ??= $this->container->get(LibraryXController::class),
            'StatsController' => $this->statsController ??= $this->container->get(StatsController::class),
            default => throw new \RuntimeException("Unknown controller: {$controllerName}")
        };
    }

    /**
     * Handle unknown action requests
     */
    private function handleUnknownAction(string $action, array $inputData): array
    {
        // Legacy message endpoint support
        if (isset($inputData['message']) && $action === null) {
            return [
                'status' => 'success',
                'message' => 'Original message endpoint: Message received: ' . $inputData['message'],
                'http_code' => 200
            ];
        }

        $this->logger->warning('Unknown action requested', [
            'action' => $action,
            'available_routes' => array_keys($this->routes)
        ]);

        return [
            'status' => 'error',
            'message' => 'No valid action specified or missing required parameters. Action: ' . ($action ?? 'null'),
            'http_code' => 400
        ];
    }
}
