<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use App\Infrastructure\Database\DatabaseConnector;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Infrastructure\Auth\GoogleOAuthVerifier;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Persistence\User\MySqlUserRepository;
use App\Infrastructure\Persistence\Movie\MySqlMovieRepository;
use App\Infrastructure\Persistence\Movie\MySqlUserMovieRepository;
use App\Infrastructure\Persistence\Movie\MySqlMovieTagRepository;
use App\Infrastructure\Persistence\Movie\MySqlMovieNoteRepository;
use App\Infrastructure\Persistence\Movie\Mappers\MovieDataMapper;
use App\Infrastructure\Persistence\Book\MySqlBookRepository;
use App\Infrastructure\Persistence\Book\MySqlUserBookRepository;
use App\Infrastructure\Persistence\Book\MySqlBookTagRepository;
use App\Infrastructure\Persistence\Book\MySqlBookNoteRepository;
use App\Infrastructure\Persistence\Book\MySqlReadingSessionRepository;
use App\Infrastructure\Persistence\Book\MySqlReadingProgressRepository;
use App\Infrastructure\Persistence\Book\Mappers\BookDataMapper;
// New Work/Edition architecture
use App\Infrastructure\Persistence\Book\MySqlWorkRepository;
use App\Infrastructure\Persistence\Book\MySqlEditionRepository;
use App\Infrastructure\Persistence\Book\MySqlUserBookEditionRepository;
use App\Infrastructure\Persistence\Book\Mappers\WorkDataMapper;
use App\Infrastructure\Persistence\Book\Mappers\EditionDataMapper;
use App\Infrastructure\Persistence\Book\Mappers\UserBookEditionDataMapper;
use App\Infrastructure\Logging\LoggingService;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\UserLibraryStatisticsService;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Repository\Movie\MovieTagRepositoryInterface;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\Repository\Book\BookTagRepositoryInterface;
use App\Domain\Repository\Book\BookNoteRepositoryInterface;
use App\Domain\Repository\Book\ReadingSessionRepositoryInterface;
use App\Domain\Repository\Book\ReadingProgressRepositoryInterface;
// New Work/Edition architecture
use App\Domain\Repository\Book\WorkRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Service\BookImportService;
use App\Controllers\LibraryXController;

return [
    // Database Connection (lazy loading)
    PDO::class => DI\factory(function (ContainerInterface $container) {
        return $container->get(DatabaseConnector::class)->getConnection();
    }),

    DatabaseConnector::class => DI\autowire(),

    // Authentication Services
    GoogleOAuthVerifier::class => DI\autowire()
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),

    // Repositories - User Module
    UserRepositoryInterface::class => DI\autowire(MySqlUserRepository::class),
    MySqlUserRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    UserLibraryStatisticsService::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(UserBookRepositoryInterface::class))
        ->constructorParameter('movieRepository', DI\get(UserMovieRepositoryInterface::class)),
    
    // Repositories - Movie Module
    MovieDataMapper::class => DI\autowire(),
    
    MovieRepositoryInterface::class => DI\autowire(MySqlMovieRepository::class),
    MySqlMovieRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(MovieDataMapper::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    UserMovieRepositoryInterface::class => DI\autowire(MySqlUserMovieRepository::class),
    MySqlUserMovieRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(MovieDataMapper::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    MovieTagRepositoryInterface::class => DI\autowire(MySqlMovieTagRepository::class),
    MySqlMovieTagRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    MovieNoteRepositoryInterface::class => DI\autowire(MySqlMovieNoteRepository::class),
    MySqlMovieNoteRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    // Repositories - Book Module (Legacy)
    BookDataMapper::class => DI\autowire(),
    
    BookRepositoryInterface::class => DI\autowire(MySqlBookRepository::class),
    MySqlBookRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(BookDataMapper::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    UserBookRepositoryInterface::class => DI\autowire(MySqlUserBookRepository::class),
    MySqlUserBookRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(BookDataMapper::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class))
        ->constructorParameter('bookRepository', DI\get(MySqlBookRepository::class)),
    
    // Repositories - Book Module (New Work/Edition Architecture)
    WorkDataMapper::class => DI\autowire(),
    EditionDataMapper::class => DI\autowire(),
    UserBookEditionDataMapper::class => DI\autowire(),
    
    WorkRepositoryInterface::class => DI\autowire(MySqlWorkRepository::class),
    MySqlWorkRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(WorkDataMapper::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    EditionRepositoryInterface::class => DI\autowire(MySqlEditionRepository::class),
    MySqlEditionRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(EditionDataMapper::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    UserBookEditionRepositoryInterface::class => DI\autowire(MySqlUserBookEditionRepository::class),
    MySqlUserBookEditionRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(UserBookEditionDataMapper::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    // Infrastructure - Cache
    CacheService::class => DI\autowire()
        ->constructorParameter('cacheDir', __DIR__ . '/../storage/cache')
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    // Services - External APIs
    App\Domain\Services\OpenLibraryService::class => DI\autowire()
        ->constructorParameter('cache', DI\get(CacheService::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    App\Domain\Services\GoogleBooksService::class => DI\autowire()
        ->constructorParameter('cache', DI\get(CacheService::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    App\Domain\Services\WorkSearchService::class => DI\autowire()
        ->constructorParameter('openLibraryService', DI\get(App\Domain\Services\OpenLibraryService::class))
        ->constructorParameter('googleBooksService', DI\get(App\Domain\Services\GoogleBooksService::class))
        ->constructorParameter('editionRepository', DI\get(EditionRepositoryInterface::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    // Services - Domain
    BookImportService::class => DI\autowire()
        ->constructorParameter('workRepository', DI\get(WorkRepositoryInterface::class))
        ->constructorParameter('editionRepository', DI\get(EditionRepositoryInterface::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    BookTagRepositoryInterface::class => DI\autowire(MySqlBookTagRepository::class),
    MySqlBookTagRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    BookNoteRepositoryInterface::class => DI\autowire(MySqlBookNoteRepository::class),
    MySqlBookNoteRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    ReadingSessionRepositoryInterface::class => DI\autowire(MySqlReadingSessionRepository::class),
    MySqlReadingSessionRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    ReadingProgressRepositoryInterface::class => DI\autowire(MySqlReadingProgressRepository::class),
    MySqlReadingProgressRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    // Services
    SessionManager::class => DI\autowire(),
    
    AuthMiddleware::class => DI\autowire()
        ->constructorParameter('sessionManager', DI\get(SessionManager::class))
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class)),

    LoggingService::class => function () {
        return LoggingService::getInstance();
    },
    
    'Logger' => function (ContainerInterface $container) {
        return $container->get(LoggingService::class)->getLogger();
    },
    
    // PSR-3 LoggerInterface - returns actual Monolog Logger instance
    \Psr\Log\LoggerInterface::class => function () {
        $loggingService = LoggingService::getInstance();
        // Initialize LoggerFactory if not already done
        $config = config('logging', []);
        \App\Infrastructure\Logging\LoggerFactory::init($config);
        // Return the actual Monolog Logger instance
        return \App\Infrastructure\Logging\LoggerFactory::create('app');
    },

    // Use Cases
    App\Domain\UseCases\Auth\LoginUserUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class)),
    
    App\Domain\UseCases\Users\AddBookToUserUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userBookRepository', DI\get(UserBookRepositoryInterface::class)),

    // Book Use Cases
    App\Domain\UseCases\Books\AddBookUseCase::class => DI\autowire()
        ->constructorParameter('bookImportService', DI\get(BookImportService::class))
        ->constructorParameter('editionRepository', DI\get(EditionRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userBookEditionRepository', DI\get(UserBookEditionRepositoryInterface::class)),

    App\Domain\UseCases\Books\DeleteBookUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userBookEditionRepository', DI\get(UserBookEditionRepositoryInterface::class))
        ->constructorParameter('editionRepository', DI\get(EditionRepositoryInterface::class)),

    App\Domain\UseCases\Books\UpdateBookRatingUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userBookEditionRepository', DI\get(UserBookEditionRepositoryInterface::class))
        ->constructorParameter('editionRepository', DI\get(EditionRepositoryInterface::class)),

    App\Domain\UseCases\Books\UpdateBookUserStatusesUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userBookRepository', DI\get(UserBookRepositoryInterface::class)),

    App\Domain\UseCases\Books\GetBooksUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userBookEditionRepository', DI\get(UserBookEditionRepositoryInterface::class))
        ->constructorParameter('editionRepository', DI\get(EditionRepositoryInterface::class))
        ->constructorParameter('workRepository', DI\get(WorkRepositoryInterface::class)),

    App\Domain\UseCases\Books\GetAllBooksUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class)),

    App\Domain\UseCases\Books\GetBookAllowedStatusesUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class)),

    App\Domain\UseCases\Books\EditUserBookUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userBookRepository', DI\get(UserBookRepositoryInterface::class))
        ->constructorParameter('bookTagRepository', DI\get(BookTagRepositoryInterface::class))
        ->constructorParameter('bookNoteRepository', DI\get(BookNoteRepositoryInterface::class)),

    App\Domain\UseCases\Books\GetTrendingBooksUseCase::class => DI\autowire()
        ->constructorParameter('userBookRepository', DI\get(UserBookRepositoryInterface::class)),

    // Movie Use Cases
    App\Domain\UseCases\Movies\AddMovieUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(MovieRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\DeleteMovieUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\UpdateMovieRatingUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\GetMoviesUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(MovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\EditUserMovieUseCase::class => DI\autowire()
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class))
        ->constructorParameter('movieTagRepository', DI\get(MovieTagRepositoryInterface::class))
        ->constructorParameter('movieNoteRepository', DI\get(MovieNoteRepositoryInterface::class)),

    App\Domain\UseCases\Movies\GetTrendingMoviesUseCase::class => DI\autowire()
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    // General Use Cases
    App\Domain\UseCases\GetLibraryUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class)),

    // Controllers
    App\Controllers\AuthController::class => DI\autowire()
        ->constructorParameter('loginUserUseCase', DI\get(App\Domain\UseCases\Auth\LoginUserUseCase::class))
        ->constructorParameter('sessionManager', DI\get(SessionManager::class))
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class))
        ->constructorParameter('googleVerifier', DI\get(GoogleOAuthVerifier::class)),

    App\Controllers\BookController::class => DI\autowire()
        ->constructorParameter('addBookUseCase', DI\get(App\Domain\UseCases\Books\AddBookUseCase::class))
        ->constructorParameter('deleteBookUseCase', DI\get(App\Domain\UseCases\Books\DeleteBookUseCase::class))
        ->constructorParameter('updateBookRatingUseCase', DI\get(App\Domain\UseCases\Books\UpdateBookRatingUseCase::class))
        ->constructorParameter('updateBookUserStatusesUseCase', DI\get(App\Domain\UseCases\Books\UpdateBookUserStatusesUseCase::class))
        ->constructorParameter('getBooksUseCase', DI\get(App\Domain\UseCases\Books\GetBooksUseCase::class))
        ->constructorParameter('getAllBooksUseCase', DI\get(App\Domain\UseCases\Books\GetAllBooksUseCase::class))
        ->constructorParameter('getBookAllowedStatusesUseCase', DI\get(App\Domain\UseCases\Books\GetBookAllowedStatusesUseCase::class))
        ->constructorParameter('bookRepository', DI\get(App\Domain\Repository\Book\BookRepositoryInterface::class))
        ->constructorParameter('bookTagRepository', DI\get(App\Domain\Repository\Book\BookTagRepositoryInterface::class))
        ->constructorParameter('readingSessionRepository', DI\get(App\Domain\Repository\Book\ReadingSessionRepositoryInterface::class))
        ->constructorParameter('readingProgressRepository', DI\get(App\Domain\Repository\Book\ReadingProgressRepositoryInterface::class))
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class))
        ->constructorParameter('editUserBookUseCase', DI\get(App\Domain\UseCases\Books\EditUserBookUseCase::class))
        ->constructorParameter('getTrendingBooksUseCase', DI\get(App\Domain\UseCases\Books\GetTrendingBooksUseCase::class))
        ->constructorParameter('workSearchService', DI\get(App\Domain\Services\WorkSearchService::class))
        ->constructorParameter('workRepository', DI\get(App\Domain\Repository\Book\WorkRepositoryInterface::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),

    App\Controllers\MovieController::class => DI\autowire()
        ->constructorParameter('addMovieUseCase', DI\get(App\Domain\UseCases\Movies\AddMovieUseCase::class))
        ->constructorParameter('deleteMovieUseCase', DI\get(App\Domain\UseCases\Movies\DeleteMovieUseCase::class))
        ->constructorParameter('updateMovieRatingUseCase', DI\get(App\Domain\UseCases\Movies\UpdateMovieRatingUseCase::class))
        ->constructorParameter('updateMovieUserStatusesUseCase', DI\get(App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase::class))
        ->constructorParameter('getMoviesUseCase', DI\get(App\Domain\UseCases\Movies\GetMoviesUseCase::class))
        ->constructorParameter('getMovieAllowedStatusesUseCase', DI\get(App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase::class))
        ->constructorParameter('editUserMovieUseCase', DI\get(App\Domain\UseCases\Movies\EditUserMovieUseCase::class))
        ->constructorParameter('movieTagRepository', DI\get(App\Domain\Repository\Movie\MovieTagRepositoryInterface::class))
        ->constructorParameter('movieNoteRepository', DI\get(App\Domain\Repository\Movie\MovieNoteRepositoryInterface::class))
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class))
        ->constructorParameter('getTrendingMoviesUseCase', DI\get(App\Domain\UseCases\Movies\GetTrendingMoviesUseCase::class)),

    App\Controllers\LibraryController::class => DI\autowire()
        ->constructorParameter('getLibraryUseCase', DI\get(App\Domain\UseCases\GetLibraryUseCase::class))
        ->constructorParameter('getBooksUseCase', DI\get(App\Domain\UseCases\Books\GetBooksUseCase::class))
        ->constructorParameter('getMoviesUseCase', DI\get(App\Domain\UseCases\Movies\GetMoviesUseCase::class))
        ->constructorParameter('addBookUseCase', DI\get(App\Domain\UseCases\Books\AddBookUseCase::class))
        ->constructorParameter('addMovieUseCase', DI\get(App\Domain\UseCases\Movies\AddMovieUseCase::class))
        ->constructorParameter('getMovieAllowedStatusesUseCase', DI\get(App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase::class))
        ->constructorParameter('getBookAllowedStatusesUseCase', DI\get(App\Domain\UseCases\Books\GetBookAllowedStatusesUseCase::class))
        ->constructorParameter('userBookRepository', DI\get(App\Domain\Repository\Book\UserBookRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(App\Domain\Repository\Movie\UserMovieRepositoryInterface::class))
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class)),

    App\Controllers\LibraryXController::class => DI\autowire()
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class)),

    App\Controllers\StatsController::class => DI\autowire()
        ->constructorParameter('userBookEditionRepository', DI\get(App\Domain\Repository\Book\UserBookEditionRepositoryInterface::class))
        ->constructorParameter('editionRepository', DI\get(App\Domain\Repository\Book\EditionRepositoryInterface::class))
        ->constructorParameter('workRepository', DI\get(App\Domain\Repository\Book\WorkRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(App\Domain\Repository\Movie\UserMovieRepositoryInterface::class))
        ->constructorParameter('readingProgressRepository', DI\get(App\Domain\Repository\Book\ReadingProgressRepositoryInterface::class))
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class)),

    // ============================================================================
    // ROUTER AND MIDDLEWARE
    // ============================================================================
    
    // Routes configuration (loaded from config/routes.php)
    'routes' => DI\factory(function () {
        return require __DIR__ . '/routes.php';
    }),

    // Middleware instances
    App\Infrastructure\Middleware\AuthenticationMiddleware::class => DI\autowire(),
    App\Infrastructure\Middleware\CSRFMiddleware::class => DI\autowire(),
    App\Infrastructure\Middleware\LoggingMiddleware::class => DI\autowire(),
    App\Infrastructure\Middleware\ValidationMiddleware::class => DI\autowire(),

    // ActionRouter with routes and middleware pipeline
    App\Router\ActionRouter::class => DI\autowire()
        ->constructorParameter('routes', DI\get('routes'))
        ->constructorParameter('container', DI\get(ContainerInterface::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
];
