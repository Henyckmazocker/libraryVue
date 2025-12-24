<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use App\Infrastructure\Database\DatabaseConnector;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Infrastructure\Persistence\MySqlUserRepository;
use App\Infrastructure\Persistence\User\MySqlUserRepository as NewMySqlUserRepository;
use App\Infrastructure\Persistence\MySqlBookRepository;
use App\Infrastructure\Persistence\MySqlMovieRepository;
use App\Infrastructure\Persistence\Movie\MySqlMovieRepository as NewMySqlMovieRepository;
use App\Infrastructure\Persistence\Movie\MySqlUserMovieRepository;
use App\Infrastructure\Persistence\Movie\MySqlMovieTagRepository;
use App\Infrastructure\Persistence\Movie\MySqlMovieNoteRepository;
use App\Infrastructure\Persistence\Movie\Mappers\MovieDataMapper;
use App\Infrastructure\Persistence\Book\MySqlBookRepository as NewMySqlBookRepository;
use App\Infrastructure\Persistence\Book\MySqlUserBookRepository as NewMySqlUserBookRepository;
use App\Infrastructure\Persistence\Book\MySqlBookTagRepository;
use App\Infrastructure\Persistence\Book\MySqlBookNoteRepository;
use App\Infrastructure\Persistence\Book\MySqlReadingSessionRepository;
use App\Infrastructure\Persistence\Book\MySqlReadingProgressRepository;
use App\Infrastructure\Persistence\Book\Mappers\BookDataMapper;
use App\Infrastructure\Logging\LoggingService;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface as NewUserRepositoryInterface;
use App\Domain\Services\UserLibraryStatisticsService;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\MovieRepositoryInterface;
use App\Domain\Repository\Movie\MovieRepositoryInterface as NewMovieRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Repository\Movie\MovieTagRepositoryInterface;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\Repository\Book\BookRepositoryInterface as NewBookRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface as NewUserBookRepositoryInterface;
use App\Domain\Repository\Book\BookTagRepositoryInterface;
use App\Domain\Repository\Book\BookNoteRepositoryInterface;
use App\Domain\Repository\Book\ReadingSessionRepositoryInterface;
use App\Domain\Repository\Book\ReadingProgressRepositoryInterface;
use App\Controllers\LibraryXController;

return [
    // Database Connection (lazy loading)
    PDO::class => DI\factory(function (ContainerInterface $container) {
        return $container->get(DatabaseConnector::class)->getConnection();
    }),

    DatabaseConnector::class => DI\autowire(),

    // Repositories - User Module (New Architecture)
    NewUserRepositoryInterface::class => DI\autowire(NewMySqlUserRepository::class),
    NewMySqlUserRepository::class => DI\autowire()
        ->constructorParameter('database', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get('Logger')),
    
    UserLibraryStatisticsService::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(NewUserBookRepositoryInterface::class))
        ->constructorParameter('movieRepository', DI\get(UserMovieRepositoryInterface::class)),
    
    // Repositories - Movie Module (New Architecture)
    MovieDataMapper::class => DI\autowire(),
    
    NewMovieRepositoryInterface::class => DI\autowire(NewMySqlMovieRepository::class),
    NewMySqlMovieRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(MovieDataMapper::class))
        ->constructorParameter('logger', DI\get('Logger')),
    
    UserMovieRepositoryInterface::class => DI\autowire(MySqlUserMovieRepository::class),
    MySqlUserMovieRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(MovieDataMapper::class))
        ->constructorParameter('logger', DI\get('Logger')),
    
    MovieTagRepositoryInterface::class => DI\autowire(MySqlMovieTagRepository::class),
    MySqlMovieTagRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get('Logger')),
    
    MovieNoteRepositoryInterface::class => DI\autowire(MySqlMovieNoteRepository::class),
    MySqlMovieNoteRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get('Logger')),
    
    // Repositories - Book Module (New Architecture)
    BookDataMapper::class => DI\autowire(),
    
    NewBookRepositoryInterface::class => DI\autowire(NewMySqlBookRepository::class),
    NewMySqlBookRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(BookDataMapper::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class)),
    
    NewUserBookRepositoryInterface::class => DI\autowire(NewMySqlUserBookRepository::class),
    NewMySqlUserBookRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('mapper', DI\get(BookDataMapper::class))
        ->constructorParameter('logger', DI\get(\Psr\Log\LoggerInterface::class))
        ->constructorParameter('bookRepository', DI\get(NewMySqlBookRepository::class)),
    
    BookTagRepositoryInterface::class => DI\autowire(MySqlBookTagRepository::class),
    MySqlBookTagRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get('Logger')),
    
    BookNoteRepositoryInterface::class => DI\autowire(MySqlBookNoteRepository::class),
    MySqlBookNoteRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get('Logger')),
    
    ReadingSessionRepositoryInterface::class => DI\autowire(MySqlReadingSessionRepository::class),
    MySqlReadingSessionRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get('Logger')),
    
    ReadingProgressRepositoryInterface::class => DI\autowire(MySqlReadingProgressRepository::class),
    MySqlReadingProgressRepository::class => DI\autowire()
        ->constructorParameter('db', DI\get(PDO::class))
        ->constructorParameter('logger', DI\get('Logger')),
    
    // Services
    SessionManager::class => DI\autowire(),
    
    AuthMiddleware::class => DI\autowire()
        ->constructorParameter('sessionManager', DI\get(SessionManager::class))
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class)),

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
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class)),
    
    App\Domain\UseCases\Users\AddBookToUserUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(NewBookRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userBookRepository', DI\get(NewUserBookRepositoryInterface::class)),

    // Book Use Cases
    App\Domain\UseCases\Books\AddBookUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(NewBookRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userBookRepository', DI\get(NewUserBookRepositoryInterface::class)),

    App\Domain\UseCases\Books\DeleteBookUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userBookRepository', DI\get(NewUserBookRepositoryInterface::class)),

    App\Domain\UseCases\Books\UpdateBookRatingUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userBookRepository', DI\get(NewUserBookRepositoryInterface::class)),

    App\Domain\UseCases\Books\UpdateBookUserStatusesUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userBookRepository', DI\get(NewUserBookRepositoryInterface::class)),

    App\Domain\UseCases\Books\GetBooksUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userBookRepository', DI\get(NewUserBookRepositoryInterface::class)),

    App\Domain\UseCases\Books\GetAllBooksUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(NewBookRepositoryInterface::class)),

    App\Domain\UseCases\Books\GetBookAllowedStatusesUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(NewBookRepositoryInterface::class)),

    App\Domain\UseCases\Books\EditUserBookUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userBookRepository', DI\get(NewUserBookRepositoryInterface::class))
        ->constructorParameter('bookTagRepository', DI\get(BookTagRepositoryInterface::class))
        ->constructorParameter('bookNoteRepository', DI\get(BookNoteRepositoryInterface::class)),

    // Movie Use Cases
    App\Domain\UseCases\Movies\AddMovieUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(NewMovieRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\DeleteMovieUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\UpdateMovieRatingUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\GetMoviesUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(NewUserRepositoryInterface::class))
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(NewMovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\EditUserMovieUseCase::class => DI\autowire()
        ->constructorParameter('userMovieRepository', DI\get(UserMovieRepositoryInterface::class))
        ->constructorParameter('movieTagRepository', DI\get(MovieTagRepositoryInterface::class))
        ->constructorParameter('movieNoteRepository', DI\get(MovieNoteRepositoryInterface::class)),

    // General Use Cases
    App\Domain\UseCases\GetLibraryUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(NewBookRepositoryInterface::class)),

    // Controllers
    App\Controllers\AuthController::class => DI\autowire()
        ->constructorParameter('loginUserUseCase', DI\get(App\Domain\UseCases\Auth\LoginUserUseCase::class))
        ->constructorParameter('sessionManager', DI\get(SessionManager::class))
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class)),

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
        ->constructorParameter('editUserBookUseCase', DI\get(App\Domain\UseCases\Books\EditUserBookUseCase::class)),

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
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class)),

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
        ->constructorParameter('userBookRepository', DI\get(App\Domain\Repository\Book\UserBookRepositoryInterface::class))
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
