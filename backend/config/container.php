<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Database\DatabaseConnector;
use App\Infrastructure\Logging\LoggerFactory;
use App\Infrastructure\Session\SessionManager;

return function (): ContainerInterface {
    $containerBuilder = new ContainerBuilder();
    
    // Enable compilation for production performance
    if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
        $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
    }
    
    $containerBuilder->addDefinitions([
        
        // ===========================
        // INFRASTRUCTURE
        // ===========================
        
        PDO::class => function () {
            $connector = new DatabaseConnector();
            return $connector->getConnection();
        },
        
        // Alias 'db' to PDO for repositories that use $db parameter name
        'db' => DI\get(PDO::class),
        
        LoggerInterface::class => function () {
            return LoggerFactory::createDatabaseLogger();
        },
        
        SessionManager::class => DI\autowire(),

        \App\Infrastructure\Auth\JWTService::class => DI\autowire(),

        // ===========================
        // DATA MAPPERS
        // ===========================
        
        \App\Infrastructure\Persistence\Book\Mappers\BookDataMapper::class => DI\autowire(),
        \App\Infrastructure\Persistence\Movie\Mappers\MovieDataMapper::class => DI\autowire(),
        \App\Infrastructure\Persistence\Game\Mappers\GameDataMapper::class => DI\autowire(),
        \App\Infrastructure\Persistence\User\Mappers\UserDataMapper::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\Mappers\WorkDataMapper::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\Mappers\EditionDataMapper::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\Mappers\UserBookEditionDataMapper::class => DI\autowire(),
        
        // ===========================
        // REPOSITORIES
        // ===========================
        
        // Map interfaces to implementations
        \App\Domain\Repository\User\UserRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\User\MySqlUserRepository::class),
        \App\Domain\Repository\Book\BookRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Book\MySqlBookRepository::class),
        \App\Domain\Repository\Movie\MovieRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Movie\MySqlMovieRepository::class),
        \App\Domain\Repository\Game\GameRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Game\MySqlGameRepository::class),
        \App\Domain\Repository\Game\UserGameRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Game\MySqlUserGameRepository::class),
        \App\Domain\Repository\Game\GameTagRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Game\MySqlGameTagRepository::class),
        \App\Domain\Repository\Game\GameNoteRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Game\MySqlGameNoteRepository::class),
        // Album repositories
        \App\Domain\Repository\Album\AlbumRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Album\MySqlAlbumRepository::class),
        \App\Domain\Repository\Album\UserAlbumRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Album\MySqlUserAlbumRepository::class),
        \App\Domain\Repository\Album\AlbumTagRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Album\MySqlAlbumTagRepository::class),
        \App\Domain\Repository\Album\AlbumNoteRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Album\MySqlAlbumNoteRepository::class),
        // Video repositories
        \App\Domain\Repository\Video\VideoRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Video\MySqlVideoRepository::class),
        \App\Domain\Repository\Video\UserVideoRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Video\MySqlUserVideoRepository::class),
        \App\Domain\Repository\Video\VideoTagRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Video\MySqlVideoTagRepository::class),
        \App\Domain\Repository\Video\VideoNoteRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Video\MySqlVideoNoteRepository::class),
        // Owned-format lookup repository (shared across all entity types)
        \App\Domain\Repository\OwnedFormatRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Common\MySqlOwnedFormatRepository::class),
        \App\Domain\Repository\Book\WorkRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Book\MySqlWorkRepository::class),
        \App\Domain\Repository\Book\EditionRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Book\MySqlEditionRepository::class),
        \App\Domain\Repository\Book\UserBookEditionRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Book\MySqlUserBookEditionRepository::class),
        \App\Domain\Repository\Book\UserBookRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Book\MySqlUserBookRepository::class),
        \App\Domain\Repository\Movie\UserMovieRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Movie\MySqlUserMovieRepository::class),
        \App\Domain\Repository\Book\BookTagRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Book\MySqlBookTagRepository::class),
        \App\Domain\Repository\Book\BookNoteRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Book\MySqlBookNoteRepository::class),
        \App\Domain\Repository\Book\ReadingSessionRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Book\MySqlReadingSessionRepository::class),
        \App\Domain\Repository\Book\ReadingProgressRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Book\MySqlReadingProgressRepository::class),
        \App\Domain\Repository\Movie\MovieTagRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Movie\MySqlMovieTagRepository::class),
        \App\Domain\Repository\Movie\MovieNoteRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Movie\MySqlMovieNoteRepository::class),
        \App\Domain\Repository\Movie\SeriesSeasonRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Movie\MySqlSeriesSeasonRepository::class),
        \App\Domain\Repository\Book\EditionNoteRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Book\MySqlEditionNoteRepository::class),

        // SOCIAL
        \App\Domain\Repository\Social\FriendshipRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Social\MySqlFriendshipRepository::class),
        \App\Domain\Repository\Social\FeedEventRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Social\MySqlFeedEventRepository::class),
        \App\Domain\Repository\Social\PrivacySettingsRepositoryInterface::class => DI\get(\App\Infrastructure\Persistence\Social\MySqlPrivacySettingsRepository::class),
        \App\Infrastructure\Persistence\Social\MySqlFriendshipRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Social\MySqlFeedEventRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Social\MySqlPrivacySettingsRepository::class => DI\autowire(),
        
        // Note: Most repositories use 'db' as the constructor parameter name for PDO
        \App\Infrastructure\Persistence\Game\MySqlGameRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Game\MySqlUserGameRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Game\MySqlGameTagRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Game\MySqlGameNoteRepository::class => DI\autowire(),
        // Album persistence
        \App\Infrastructure\Persistence\Album\MySqlAlbumRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Album\MySqlUserAlbumRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Album\MySqlAlbumTagRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Album\MySqlAlbumNoteRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Album\Mappers\AlbumDataMapper::class => DI\autowire(),
        \App\Infrastructure\Persistence\Common\MySqlOwnedFormatRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\User\MySqlUserRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\MySqlBookRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Movie\MySqlMovieRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\MySqlWorkRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\MySqlEditionRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\MySqlUserBookEditionRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\MySqlUserBookRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Movie\MySqlUserMovieRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\MySqlBookTagRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\MySqlBookNoteRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\MySqlReadingSessionRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\MySqlReadingProgressRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Movie\MySqlMovieTagRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Movie\MySqlMovieNoteRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Movie\MySqlSeriesSeasonRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Book\MySqlEditionNoteRepository::class => DI\autowire(),
        // Video persistence
        \App\Infrastructure\Persistence\Video\MySqlVideoRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Video\MySqlUserVideoRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Video\MySqlVideoTagRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Video\MySqlVideoNoteRepository::class => DI\autowire(),
        \App\Infrastructure\Persistence\Video\Mappers\VideoDataMapper::class => DI\autowire(),
        
        // ===========================
        // DOMAIN SERVICES
        // ===========================
        
        \App\Infrastructure\Cache\CacheService::class => function(ContainerInterface $c) {
            $cacheDir = __DIR__ . '/../storage/cache';
            return new \App\Infrastructure\Cache\CacheService($cacheDir, $c->get(LoggerInterface::class));
        },
        
        \App\Domain\Services\BookImportServiceInterface::class => DI\get(\App\Domain\Services\BookImportService::class),
        \App\Domain\Services\BookImportService::class => DI\autowire(),
        
        \App\Domain\Services\OpenLibraryService::class => DI\autowire()
            ->constructorParameter('cache', DI\get(\App\Infrastructure\Cache\CacheService::class))
            ->constructorParameter('logger', DI\get(LoggerInterface::class)),
            
        \App\Domain\Services\GoogleBooksService::class => DI\autowire()
            ->constructorParameter('cache', DI\get(\App\Infrastructure\Cache\CacheService::class))
            ->constructorParameter('logger', DI\get(LoggerInterface::class)),
            
        \App\Domain\Services\IGDBService::class => DI\autowire()
            ->constructorParameter('cache', DI\get(\App\Infrastructure\Cache\CacheService::class))
            ->constructorParameter('logger', DI\get(LoggerInterface::class)),

        \App\Domain\Services\OmdbService::class => DI\autowire()
            ->constructorParameter('cache', DI\get(\App\Infrastructure\Cache\CacheService::class))
            ->constructorParameter('logger', DI\get(LoggerInterface::class)),

        \App\Domain\Services\SpotifyService::class => DI\autowire()
            ->constructorParameter('cache', DI\get(\App\Infrastructure\Cache\CacheService::class))
            ->constructorParameter('logger', DI\get(LoggerInterface::class)),

        \App\Domain\Services\LastFmService::class => DI\autowire()
            ->constructorParameter('cache', DI\get(\App\Infrastructure\Cache\CacheService::class))
            ->constructorParameter('logger', DI\get(LoggerInterface::class)),

        \App\Domain\Services\YouTubeService::class => DI\autowire()
            ->constructorParameter('cache', DI\get(\App\Infrastructure\Cache\CacheService::class))
            ->constructorParameter('logger', DI\get(LoggerInterface::class)),
            
        \App\Domain\Services\WorkSearchService::class => DI\autowire()
            ->constructorParameter('openLibraryService', DI\get(\App\Domain\Services\OpenLibraryService::class))
            ->constructorParameter('googleBooksService', DI\get(\App\Domain\Services\GoogleBooksService::class))
            ->constructorParameter('editionRepository', DI\get(\App\Domain\Repository\Book\EditionRepositoryInterface::class))
            ->constructorParameter('logger', DI\get(LoggerInterface::class)),

        // ===========================
        // USE CASES - Auth
        // ===========================
        
        \App\Domain\UseCases\Auth\LoginUserUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Auth\UpdateUserProfileUseCase::class => DI\autowire(),
        
        // ===========================
        // USE CASES - Books
        // ===========================
        
        \App\Domain\UseCases\Books\AddBookUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Books\GetBooksUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Books\GetAllBooksUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Books\DeleteBookUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Books\UpdateBookRatingUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Books\UpdateBookUserStatusesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Books\EditUserBookUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Books\GetBookAllowedStatusesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Books\GetTrendingBooksUseCase::class => DI\autowire(),
        
        // ===========================
        // USE CASES - Movies
        // ===========================
        
        \App\Domain\UseCases\Movies\AddMovieUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Movies\GetMoviesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Movies\DeleteMovieUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Movies\UpdateMovieRatingUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Movies\EditUserMovieUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Movies\GetTrendingMoviesUseCase::class => DI\autowire(),
        
        // ===========================
        // USE CASES - Games
        // ===========================
        
        \App\Domain\UseCases\Games\AddGameUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Games\GetGamesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Games\DeleteGameUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Games\UpdateGameRatingUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Games\UpdateGameUserStatusesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Games\GetGameAllowedStatusesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Games\EditUserGameUseCase::class => DI\autowire(),

        // ===========================
        // USE CASES - Albums
        // ===========================

        \App\Domain\UseCases\Albums\AddAlbumUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Albums\DeleteAlbumUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Albums\GetAlbumsUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Albums\UpdateAlbumRatingUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Albums\UpdateAlbumUserStatusesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Albums\GetAlbumAllowedStatusesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Albums\EditUserAlbumUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Albums\GetTrendingAlbumsUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Albums\GetListeningStatsUseCase::class => DI\autowire(),

        // ===========================
        // USE CASES - Videos
        // ===========================

        \App\Domain\UseCases\Videos\AddVideoUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\DeleteVideoUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\EditUserVideoUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\GetVideosUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\GetVideoAllowedStatusesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\GetTrendingVideosUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\UpdateVideoRatingUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\UpdateVideoUserStatusesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\AddVideoNoteUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\GetVideoNotesUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\UpdateVideoNoteUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Videos\DeleteVideoNoteUseCase::class => DI\autowire(),
        
        // ===========================
        // USE CASES - Library
        // ===========================
        
        \App\Domain\UseCases\GetLibraryUseCase::class => DI\autowire(),
        \App\Domain\UseCases\GetLibraryItemsUseCase::class => DI\autowire(),
        \App\Domain\UseCases\GetOwnershipFormatsUseCase::class => DI\autowire(),

        // ===========================
        // USE CASES - Social
        // ===========================

        \App\Domain\UseCases\Social\SendFriendRequestUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\AcceptFriendRequestUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\RejectFriendRequestUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\RemoveFriendUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\GetFriendsUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\GetFriendRequestsUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\SearchUsersUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\GetPublicProfileUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\GetFeedUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\GetPrivacySettingsUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\UpdatePrivacySettingsUseCase::class => DI\autowire(),
        \App\Domain\UseCases\Social\CreateFeedEventUseCase::class => DI\autowire(),
        \App\Domain\Services\FeedEventService::class => DI\autowire(),
        
        // ===========================
        // MIDDLEWARE
        // ===========================
        
        \App\Infrastructure\Middleware\AuthMiddleware::class => DI\autowire(),
        \App\Infrastructure\Middleware\LoggingMiddleware::class => DI\autowire(),
        \App\Infrastructure\Middleware\CsrfMiddleware::class => DI\autowire(),
        
        // ===========================
        // CONTROLLERS
        // ===========================
        
        \App\Controllers\AuthController::class => DI\autowire(),
        \App\Controllers\BookController::class => DI\autowire(),
        \App\Controllers\MovieController::class => DI\autowire(),
        \App\Controllers\GameController::class => DI\autowire(),
        \App\Controllers\AlbumController::class => DI\autowire(),
        \App\Controllers\VideoController::class => DI\autowire(),
        \App\Controllers\LibraryController::class => DI\autowire(),
        \App\Controllers\LibraryXController::class => DI\autowire(),
        \App\Controllers\StatsController::class => DI\autowire(),
        \App\Controllers\SocialController::class => DI\autowire(),
        \App\Controllers\FeedController::class => DI\autowire(),
        
        // ===========================
        // ROUTER
        // ===========================
        
        \App\Router\ActionRouter::class => DI\autowire()
            ->constructorParameter('routes', require __DIR__ . '/routes.php')
            ->constructorParameter('container', DI\get(ContainerInterface::class))
            ->constructorParameter('logger', DI\get(LoggerInterface::class)),
    ]);
    
    return $containerBuilder->build();
};
