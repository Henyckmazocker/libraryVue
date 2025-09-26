<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use App\Infrastructure\Database\DatabaseConnector;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Infrastructure\Persistence\MySqlUserRepository;
use App\Infrastructure\Persistence\MySqlBookRepository;
use App\Infrastructure\Persistence\MySqlMovieRepository;
use App\Infrastructure\Logging\LoggingService;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\MovieRepositoryInterface;
use App\Controllers\LibraryXController;

return [
    // Database Connection (lazy loading)
    PDO::class => DI\factory(function (ContainerInterface $container) {
        return $container->get(DatabaseConnector::class)->getConnection();
    }),

    DatabaseConnector::class => DI\autowire(),

    // Repositories
    UserRepositoryInterface::class => DI\autowire(MySqlUserRepository::class),
    MySqlUserRepository::class => DI\autowire()
        ->constructorParameter('pdo', DI\get(PDO::class)),

    BookRepositoryInterface::class => DI\autowire(MySqlBookRepository::class),
    MySqlBookRepository::class => DI\autowire()
        ->constructorParameter('pdo', DI\get(PDO::class)),

    MovieRepositoryInterface::class => DI\autowire(MySqlMovieRepository::class),
    MySqlMovieRepository::class => DI\autowire()
        ->constructorParameter('pdo', DI\get(PDO::class)),

    // Services
    SessionManager::class => DI\autowire(),
    
    AuthMiddleware::class => DI\autowire()
        ->constructorParameter('sessionManager', DI\get(SessionManager::class)),

    LoggingService::class => function () {
        return LoggingService::getInstance();
    },

    // Use Cases
    App\Domain\UseCases\Auth\LoginUserUseCase::class => DI\autowire()
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class)),

    // Book Use Cases
    App\Domain\UseCases\Books\AddBookUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class)),

    App\Domain\UseCases\Books\DeleteBookUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class)),

    App\Domain\UseCases\Books\UpdateBookRatingUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class)),

    App\Domain\UseCases\Books\UpdateBookUserStatusesUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class)),

    App\Domain\UseCases\Books\GetBooksUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class)),

    App\Domain\UseCases\Books\GetAllBooksUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class)),

    App\Domain\UseCases\Books\EditUserBookUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class)),

    App\Domain\UseCases\Books\EditUserBookUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class)),

    // Movie Use Cases
    App\Domain\UseCases\Movies\AddMovieUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(MovieRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class)),

    App\Domain\UseCases\Movies\DeleteMovieUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(MovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\UpdateMovieRatingUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(MovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(MovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\GetMoviesUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(MovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(MovieRepositoryInterface::class)),

    App\Domain\UseCases\Movies\EditUserMovieUseCase::class => DI\autowire()
        ->constructorParameter('movieRepository', DI\get(MovieRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class)),

    // General Use Cases
    App\Domain\UseCases\GetLibraryUseCase::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class)),

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
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class))
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
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class)),

    App\Controllers\LibraryController::class => DI\autowire()
        ->constructorParameter('getLibraryUseCase', DI\get(App\Domain\UseCases\GetLibraryUseCase::class))
        ->constructorParameter('getBooksUseCase', DI\get(App\Domain\UseCases\Books\GetBooksUseCase::class))
        ->constructorParameter('getMoviesUseCase', DI\get(App\Domain\UseCases\Movies\GetMoviesUseCase::class))
        ->constructorParameter('addBookUseCase', DI\get(App\Domain\UseCases\Books\AddBookUseCase::class))
        ->constructorParameter('addMovieUseCase', DI\get(App\Domain\UseCases\Movies\AddMovieUseCase::class))
        ->constructorParameter('getMovieAllowedStatusesUseCase', DI\get(App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase::class))
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class))
        ->constructorParameter('userRepository', DI\get(UserRepositoryInterface::class))
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class)),

    App\Controllers\LibraryXController::class => DI\autowire()
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class)),

    App\Controllers\StatsController::class => DI\autowire()
        ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class))
        ->constructorParameter('movieRepository', DI\get(MovieRepositoryInterface::class))
        ->constructorParameter('authMiddleware', DI\get(AuthMiddleware::class)),
];
