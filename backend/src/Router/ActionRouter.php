<?php

declare(strict_types=1);

namespace App\Router;

use App\Controllers\AlbumController;
use App\Controllers\AuthController;
use App\Controllers\FeedController;
use App\Controllers\SocialController;
use App\Controllers\BookController;
use App\Controllers\MovieController;
use App\Controllers\GameController;
use App\Controllers\LibraryController;
use App\Controllers\LibraryXController;
use App\Controllers\ClubController;
use App\Controllers\ListController;
use App\Controllers\StatsController;
use App\Controllers\VideoController;
use App\Infrastructure\Middleware\MiddlewarePipeline;
use App\Infrastructure\RateLimit\RateLimitMiddleware;
use App\Domain\DTO\Commands\AddBookCommand;
use App\Domain\DTO\Commands\CreateListCommand;
use App\Domain\DTO\Commands\UpdateListCommand;
use App\Domain\DTO\Commands\DeleteListCommand;
use App\Domain\DTO\Commands\AddListItemCommand;
use App\Domain\DTO\Commands\InviteCollaboratorCommand;
use App\Domain\DTO\Commands\AcceptClubInvitationCommand;
use App\Domain\DTO\Commands\AcceptCollaborationCommand;
use App\Domain\DTO\Commands\CreateClubCommand;
use App\Domain\DTO\Commands\FinishClubPickCommand;
use App\Domain\DTO\Commands\InviteToClubCommand;
use App\Domain\DTO\Commands\LeaveClubCommand;
use App\Domain\DTO\Commands\SetClubPickCommand;
use App\Domain\DTO\Queries\GetClubNotesQuery;
use App\Domain\DTO\Queries\GetClubProgressQuery;
use App\Domain\DTO\Queries\GetClubQuery;
use App\Domain\DTO\Queries\GetMyClubsQuery;
use App\Domain\DTO\Commands\RemoveCollaboratorCommand;
use App\Domain\DTO\Commands\RemoveListItemCommand;
use App\Domain\DTO\Queries\GetMyListsQuery;
use App\Domain\DTO\Queries\GetListQuery;
use App\Domain\DTO\Queries\GetUserListsQuery;
use App\Domain\DTO\Commands\DeleteBookCommand;
use App\Domain\DTO\Commands\UpdateBookRatingCommand;
use App\Domain\DTO\Commands\UpdateBookStatusesCommand;
use App\Domain\DTO\Commands\EditUserBookCommand;
use App\Domain\DTO\Commands\AddMovieCommand;
use App\Domain\DTO\Commands\DeleteMovieCommand;
use App\Domain\DTO\Commands\UpdateMovieRatingCommand;
use App\Domain\DTO\Commands\UpdateMovieStatusesCommand;
use App\Domain\DTO\Commands\EditUserMovieCommand;
use App\Domain\DTO\Commands\AddMovieNoteCommand;
use App\Domain\DTO\Commands\UpdateMovieNoteCommand;
use App\Domain\DTO\Commands\DeleteMovieNoteCommand;
use App\Domain\DTO\Commands\TrackSeriesSeasonCommand;
use App\Domain\DTO\Queries\GetMovieNotesQuery;
use App\Domain\DTO\Queries\GetSeriesProgressQuery;
use App\Domain\DTO\Commands\AddGameCommand;
use App\Domain\DTO\Commands\AddGameNoteCommand;
use App\Domain\DTO\Commands\DeleteGameCommand;
use App\Domain\DTO\Commands\UpdateGameRatingCommand;
use App\Domain\DTO\Commands\UpdateGameStatusesCommand;
use App\Domain\DTO\Commands\EditUserGameCommand;
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
use App\Domain\DTO\Queries\GetTrendingBooksQuery;
use App\Domain\DTO\Queries\GetTrendingMoviesQuery;
use App\Domain\DTO\Queries\GetTrendingGamesQuery;
use App\Domain\DTO\Commands\AddAlbumCommand;
use App\Domain\DTO\Commands\AddAlbumNoteCommand;
use App\Domain\DTO\Commands\DeleteAlbumCommand;
use App\Domain\DTO\Commands\UpdateAlbumRatingCommand;
use App\Domain\DTO\Commands\UpdateAlbumStatusesCommand;
use App\Domain\DTO\Commands\EditUserAlbumCommand;
use App\Domain\DTO\Queries\GetTrendingAlbumsQuery;
use App\Domain\DTO\Queries\GetLastFmStatsQuery;
use App\Domain\DTO\Commands\AcceptFriendRequestCommand;
use App\Domain\DTO\Commands\RejectFriendRequestCommand;
use App\Domain\DTO\Commands\RemoveFriendCommand;
use App\Domain\DTO\Commands\ResolveRecommendationCommand;
use App\Domain\DTO\Commands\SendFriendRequestCommand;
use App\Domain\DTO\Commands\SendRecommendationCommand;
use App\Domain\DTO\Commands\UpdatePrivacySettingsCommand;
use App\Domain\DTO\Queries\GetFeedQuery;
use App\Domain\DTO\Queries\GetFriendRequestsQuery;
use App\Domain\DTO\Queries\GetFriendsQuery;
use App\Domain\DTO\Queries\GetInboxCountQuery;
use App\Domain\DTO\Queries\GetInboxQuery;
use App\Domain\DTO\Queries\GetPublicProfileQuery;
use App\Domain\DTO\Queries\SearchUsersQuery;
use App\Domain\DTO\Commands\AddVideoCommand;
use App\Domain\DTO\Commands\AddVideoNoteCommand;
use App\Domain\DTO\Commands\DeleteVideoCommand;
use App\Domain\DTO\Commands\UpdateVideoRatingCommand;
use App\Domain\DTO\Commands\UpdateVideoStatusesCommand;
use App\Domain\DTO\Commands\EditUserVideoCommand;
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
    private ?AlbumController $albumController = null;
    private ?AuthController $authController = null;
    private ?BookController $bookController = null;
    private ?MovieController $movieController = null;
    private ?GameController $gameController = null;
    private ?LibraryController $libraryController = null;
    private ?LibraryXController $libraryXController = null;
    private ?StatsController $statsController = null;
    private ?VideoController $videoController = null;
    private ?SocialController $socialController = null;
    private ?FeedController $feedController = null;
    private ?ListController $listController = null;
    private ?ClubController $clubController = null;

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

            // Apply a global default rate limiter (env-configured) to every route
            // that does not declare its own RateLimitMiddleware. Routes needing a
            // stricter/looser limit override it by listing RateLimitMiddleware
            // explicitly with config in routes.php.
            if (!$this->hasRateLimitMiddleware($route['middleware'])) {
                $pipeline->add($this->container->get(RateLimitMiddleware::class));
            }

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
     * Check whether a route's middleware stack already declares a RateLimitMiddleware
     * (either as a bare class name or as a [class, config] pair).
     */
    private function hasRateLimitMiddleware(array $middlewares): bool
    {
        foreach ($middlewares as $middlewareConfig) {
            $class = is_array($middlewareConfig) ? ($middlewareConfig[0] ?? null) : $middlewareConfig;
            if ($class === RateLimitMiddleware::class) {
                return true;
            }
        }

        return false;
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
            'check_auth' => $controller->checkAuth($userId, $request['auth_method'] ?? null),
            'update_user_profile' => $controller->updateProfile($data),
            'log_frontend' => $controller->logFrontend($data['log_data'] ?? []),
            'log_frontend_batch' => $controller->logFrontendBatch($data['logs'] ?? []),
            
            // BOOKS - Use Command DTOs
            'add_book' => $controller->addBook(
                AddBookCommand::fromArray($data['book'] ?? [], $userId)
            ),
            'delete_book' => $controller->deleteBook(
                DeleteBookCommand::fromArray($data, $userId)
            ),
            'update_book_rating' => $controller->updateBookRating(
                new UpdateBookRatingCommand($userId, $data['isbn'] ?? '', $data['rating'] ?? null)
            ),
            'update_book_user_statuses' => $controller->updateBookUserStatuses(
                // Por `fromArray`, como sus vecinas: el constructor es
                // (ISBN, int, array) y aqui se le pasaba (int, string, array),
                // asi que la accion respondia 500 a todo. No lo vio nadie
                // durante meses porque el frontend metia los estados dentro de
                // `edit_user_book` y esta accion no la llamaba nada.
                UpdateBookStatusesCommand::fromArray($data, $userId)
            ),
            'edit_user_book' => $controller->editUserBook(
                EditUserBookCommand::fromArray($data, $userId)
            ),
            'get_book_allowed_statuses' => $controller->getBookAllowedStatuses(),
            'get_books' => $controller->getAllBooks(),
            'get_library' => $controller->getBooks(
                new GetBooksByUserQuery($userId)
            ),
            'get_trending_books' => $controller->getTrendingBooks(
                GetTrendingBooksQuery::fromArray($data)
            ),
            
            // WORKS - Work search endpoints (no DTOs needed)
            'search_works' => $controller->searchWorks($data),
            'get_work' => $controller->getWork($data),
            'get_work_editions' => $controller->getWorkEditions($data),
            'validate_isbn' => $controller->validateISBN($data),
            'search_google_books_isbn' => $controller->searchGoogleBooksByISBN($data),
            'get_openlibrary_book_by_isbn' => $controller->getOpenLibraryBookByISBN($data),
            
            // BOOK TAGS - Tag management endpoints
            'get_user_book_tags' => $controller->getUserBookTags($userId),
            'create_user_book_tag' => $controller->createUserBookTag(
                $userId,
                $data['name'] ?? '',
                $data['color'] ?? '#1976d2'
            ),
            'get_book_tags' => $controller->getBookTags($userId, $data['isbn'] ?? ''),
            'update_book_tags' => $controller->updateBookTags($userId, $data['isbn'] ?? '', $data['tag_ids'] ?? []),
            
            // EDITION NOTES - Note management endpoints
            'add_edition_note' => $controller->addEditionNote($data, $userId),
            'update_edition_note' => $controller->updateEditionNote($data, $userId),
            'delete_edition_note' => $controller->deleteEditionNote($data, $userId),
            'get_edition_notes' => $controller->getEditionNotes($data, $userId),
            'get_edition_note' => $controller->getEditionNote($data, $userId),
            
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
            'get_trending_movies' => $controller->getTrendingMovies(
                GetTrendingMoviesQuery::fromArray($data)
            ),
            
            // MOVIE TAGS - Tag management endpoints
            'get_user_movie_tags' => $controller->getUserMovieTags($userId),
            'create_user_movie_tag' => $controller->createUserMovieTag(
                $userId,
                $data['name'] ?? '',
                $data['color'] ?? '#1976d2'
            ),
            'get_movie_tags' => $controller->getMovieTags($userId, $data['isbn'] ?? ''),
            'update_movie_tags' => $controller->updateMovieTags($userId, $data['isbn'] ?? '', $data['tag_ids'] ?? []),
            
            // MOVIE NOTES - Use Command/Query DTOs
            'get_movie_notes' => $controller->getMovieNotes(
                GetMovieNotesQuery::fromArray($data, $userId)
            ),
            'add_movie_note' => $controller->addMovieNote(
                AddMovieNoteCommand::fromArray($data, $userId)
            ),
            'update_movie_note' => $controller->updateMovieNote(
                UpdateMovieNoteCommand::fromArray($data, $userId)
            ),
            'delete_movie_note' => $controller->deleteMovieNote(
                DeleteMovieNoteCommand::fromArray($data, $userId)
            ),

            // SERIES SEASON TRACKING
            'track_series_season' => $controller->trackSeriesSeason(
                TrackSeriesSeasonCommand::fromArray($data, $userId)
            ),
            'get_series_progress' => $controller->getSeriesProgress(
                GetSeriesProgressQuery::fromArray($data, $userId)
            ),

            // OMDB PROXY
            'search_movies_omdb'      => $controller->searchMoviesOmdb($data),
            'get_movie_details_omdb'  => $controller->getMovieDetailsOmdb($data),
            'get_season_episodes_omdb'=> $controller->getSeasonEpisodesOmdb($data),

            // GAMES - Use Command DTOs
            'add_game' => $controller->addGame(
                AddGameCommand::fromArray($data['game'] ?? [], $userId)
            ),
            'delete_game' => $controller->deleteGame(
                DeleteGameCommand::fromArray($data, $userId)
            ),
            'update_game_rating' => $controller->updateGameRating(
                UpdateGameRatingCommand::fromArray($data, $userId)
            ),
            'update_game_user_statuses' => $controller->updateGameUserStatuses(
                UpdateGameStatusesCommand::fromArray($data, $userId)
            ),
            'edit_user_game' => $controller->editUserGame(
                EditUserGameCommand::fromArray($data, $userId)
            ),
            'get_game_allowed_statuses' => $controller->getGameAllowedStatuses(),
            'get_games' => $controller->getGames([
                'userId' => $userId,
                'filters' => $data['filters'] ?? []
            ]),
            'get_trending_games' => $controller->getTrendingGames(
                GetTrendingGamesQuery::fromArray($data)
            ),
            'get_user_game_tags' => $controller->getUserGameTags($userId),
            'create_user_game_tag' => $controller->createUserGameTag(
                $userId,
                $data['name'] ?? '',
                $data['color'] ?? '#1976d2'
            ),
            'delete_user_game_tag' => $controller->deleteUserGameTag($userId, $data['tagId'] ?? 0),
            'get_game_tags' => $controller->getGameTags($userId, $data['gameId'] ?? 0),
            'assign_tag_to_game' => $controller->assignTagToGame(
                $userId,
                $data['gameId'] ?? 0,
                $data['tagId'] ?? 0
            ),
            'remove_tag_from_game' => $controller->removeTagFromGame(
                $userId,
                $data['gameId'] ?? 0,
                $data['tagId'] ?? 0
            ),
            'update_game_tags' => $controller->updateGameTags(
                $userId,
                $data['gameId'] ?? 0,
                $data['tag_ids'] ?? []
            ),
            'get_game_notes' => $controller->getGameNotes($userId, $data['gameId'] ?? 0),
            'add_game_note' => $controller->addGameNote(
                AddGameNoteCommand::fromArray($data, $userId)
            ),
            'update_game_note' => $controller->updateGameNote(
                $data['noteId'] ?? $data['note_id'] ?? 0,
                $userId,
                $data['noteText'] ?? $data['note_text'] ?? '',
                $data['noteType'] ?? $data['note_type'] ?? 'note',
                (bool)($data['isPrivate'] ?? $data['is_private'] ?? true)
            ),
            'delete_game_note' => $controller->deleteGameNote(
                $data['noteId'] ?? $data['note_id'] ?? 0,
                $userId
            ),
            'get_igdb_config' => $controller->getIGDBConfig(),
            'get_igdb_token' => $controller->getIGDBToken(),
            'search_igdb_games' => $controller->searchIGDBGames($data),
            'get_igdb_game_by_id' => $controller->getIGDBGameById($data),
            'get_igdb_game_details' => $controller->getIGDBGameDetails($data),

            // ALBUMS - Use Command DTOs
            'add_album' => $controller->addAlbum(
                AddAlbumCommand::fromArray($data['album'] ?? [], $userId)
            ),
            'delete_album' => $controller->deleteAlbum(
                DeleteAlbumCommand::fromArray($data, $userId)
            ),
            'update_album_rating' => $controller->updateAlbumRating(
                UpdateAlbumRatingCommand::fromArray($data, $userId)
            ),
            'update_album_user_statuses' => $controller->updateAlbumUserStatuses(
                UpdateAlbumStatusesCommand::fromArray($data, $userId)
            ),
            'edit_user_album' => $controller->editUserAlbum(
                EditUserAlbumCommand::fromArray($data, $userId)
            ),
            'get_album_allowed_statuses' => $controller->getAlbumAllowedStatuses(),
            'get_albums' => $controller->getAlbums([
                'userId'  => $userId,
                'filters' => $data['filters'] ?? []
            ]),
            'get_trending_albums' => $controller->getTrendingAlbums(
                GetTrendingAlbumsQuery::fromArray($data)
            ),
            'get_user_album_tags' => $controller->getUserAlbumTags($userId),
            'create_user_album_tag' => $controller->createUserAlbumTag(
                $userId,
                $data['name'] ?? '',
                $data['color'] ?? '#1976d2'
            ),
            'delete_user_album_tag' => $controller->deleteUserAlbumTag($userId, $data['tagId'] ?? 0),
            'get_album_tags' => $controller->getAlbumTags($userId, $data['albumId'] ?? 0),
            'update_album_tags' => $controller->updateAlbumTags(
                $userId,
                $data['albumId'] ?? 0,
                $data['tag_ids'] ?? []
            ),
            'get_album_notes' => $controller->getAlbumNotes($userId, $data['albumId'] ?? 0),
            'add_album_note' => $controller->addAlbumNote(
                AddAlbumNoteCommand::fromArray($data, $userId)
            ),
            'update_album_note' => $controller->updateAlbumNote(
                $data['noteId'] ?? $data['note_id'] ?? 0,
                $userId,
                $data['noteText'] ?? $data['note_text'] ?? '',
                $data['noteType'] ?? $data['note_type'] ?? 'note',
                (bool)($data['isPrivate'] ?? $data['is_private'] ?? true)
            ),
            'delete_album_note' => $controller->deleteAlbumNote(
                $data['noteId'] ?? $data['note_id'] ?? 0,
                $userId
            ),
            'search_spotify_albums'    => $controller->searchSpotifyAlbums($data),
            'get_spotify_album'        => $controller->getSpotifyAlbum($data),
            'get_spotify_artist'       => $controller->getSpotifyArtist($data),
            'get_spotify_album_tracks' => $controller->getSpotifyAlbumTracks($data),
            'get_spotify_new_releases' => $controller->getSpotifyNewReleases($data),
            'get_listening_stats'      => $controller->getListeningStats(
                GetLastFmStatsQuery::fromArray($data, $userId)
            ),

            // LIBRARY - No DTOs (complex operations handled internally)
            'get_library_items'      => $controller->getLibraryItems($userId),
            'save_library'           => $controller->saveLibrary($userId),
            'import_data'            => $controller->importData($data['processedData'] ?? [], $userId),
            'ping'                   => $controller->ping(),
            'get_ownership_formats'  => $controller->getOwnershipFormats($data, $userId),

            // LIBRARYX - No DTOs (legacy endpoints)
            'libraryx_get_urls' => $controller->getUrls($request['user'] ?? []),
            'libraryx_update_urls' => $controller->updateUrls($data, $request['user'] ?? []),
            
            // STATISTICS - No DTOs (simple read operations)
            'get_book_stats'  => $controller->getBookStats($userId),
            'get_movie_stats' => $controller->getMovieStats($userId),
            'get_game_stats'  => $controller->getGameStats($userId),
            'get_album_stats' => $controller->getAlbumStats($userId),
            'get_video_stats' => $controller->getVideoStats($userId),
            
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
            'update_reading_progress' => $controller->updateReadingProgressWithSession(
                UpdateReadingProgressCommand::fromArray($data, $userId)
            ),
            'update_reading_progress_with_session' => $controller->updateReadingProgressWithSession(
                UpdateReadingProgressCommand::fromArray($data, $userId)
            ),
            'get_reading_session_history' => $controller->getReadingSessionHistory(
                GetReadingSessionQuery::fromArray($data, $userId)
            ),
            'get_progress_history' => $controller->getProgressHistory($userId, $data['isbn'] ?? ''),
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

            // VIDEOS - YouTube video management
            'add_video' => $controller->addVideo(
                AddVideoCommand::fromArray($data, $userId)
            ),
            'delete_video' => $controller->deleteVideo(
                DeleteVideoCommand::fromArray($data, $userId)
            ),
            'update_video_rating' => $controller->updateVideoRating(
                UpdateVideoRatingCommand::fromArray($data, $userId)
            ),
            'update_video_user_statuses' => $controller->updateVideoUserStatuses(
                UpdateVideoStatusesCommand::fromArray($data, $userId)
            ),
            'edit_user_video' => $controller->editUserVideo(
                EditUserVideoCommand::fromArray($data, $userId)
            ),
            'get_video_allowed_statuses' => $controller->getVideoAllowedStatuses(),
            'get_videos' => $controller->getVideos([
                'userId'  => $userId,
                'filters' => $data['filters'] ?? []
            ]),
            'get_trending_videos' => $controller->getTrendingVideos([
                'userId' => $userId,
                'limit'  => $data['limit'] ?? 10
            ]),
            'get_user_video_tags'    => $controller->getUserVideoTags($userId),
            'create_user_video_tag'  => $controller->createUserVideoTag($userId, $data['name'] ?? '', $data['color'] ?? '#c0392b'),
            'delete_user_video_tag'  => $controller->deleteUserVideoTag($userId, $data['tagId'] ?? 0),
            'get_video_tags'         => $controller->getVideoTags($userId, $data['videoId'] ?? 0),
            'update_video_tags'      => $controller->updateVideoTags($userId, $data['videoId'] ?? 0, $data['tag_ids'] ?? []),
            'get_video_notes'        => $controller->getVideoNotes($userId, $data['youtubeId'] ?? ''),
            'add_video_note'         => $controller->addVideoNote(AddVideoNoteCommand::fromArray($data, $userId)),
            'update_video_note'      => $controller->updateVideoNote($data['noteId'] ?? 0, $userId, $data['noteText'] ?? '', $data['noteType'] ?? 'note'),
            'delete_video_note'      => $controller->deleteVideoNote($data['noteId'] ?? 0, $userId),
            'search_youtube_videos'  => $controller->searchVideos($data),
            'get_youtube_video_details' => $controller->getVideoDetails($data),

            // SOCIAL - Friends
            'send_friend_request' => $controller->sendFriendRequest(
                SendFriendRequestCommand::fromArray($data, $userId)
            ),
            'accept_friend_request' => $controller->acceptFriendRequest(
                AcceptFriendRequestCommand::fromArray($data, $userId)
            ),
            'reject_friend_request' => $controller->rejectFriendRequest(
                RejectFriendRequestCommand::fromArray($data, $userId)
            ),
            'remove_friend' => $controller->removeFriend(
                RemoveFriendCommand::fromArray($data, $userId)
            ),
            'get_friends' => $controller->getFriends(
                new GetFriendsQuery($userId)
            ),
            'get_friend_requests' => $controller->getFriendRequests(
                new GetFriendRequestsQuery($userId)
            ),
            'search_users' => $controller->searchUsers(
                SearchUsersQuery::fromArray($data, $userId)
            ),
            'get_public_profile' => $controller->getPublicProfile(
                GetPublicProfileQuery::fromArray($data, $userId)
            ),

            // SOCIAL - Feed
            'get_feed' => $controller->getFeed(
                GetFeedQuery::fromArray($data, $userId)
            ),
            'get_privacy_settings' => $controller->getPrivacySettings($userId),
            'update_privacy_settings' => $controller->updatePrivacySettings(
                UpdatePrivacySettingsCommand::fromArray($data, $userId)
            ),

            // MEDIA LISTS
            'create_list' => $controller->createList(
                CreateListCommand::fromArray($data, $userId)
            ),
            'update_list' => $controller->updateList(
                UpdateListCommand::fromArray($data, $userId)
            ),
            'delete_list' => $controller->deleteList(
                DeleteListCommand::fromArray($data, $userId)
            ),
            'get_my_lists' => $controller->getMyLists(
                new GetMyListsQuery($userId)
            ),
            'get_list' => $controller->getList(
                GetListQuery::fromArray($data, $userId)
            ),
            'get_user_lists' => $controller->getUserLists(
                GetUserListsQuery::fromArray($data, $userId)
            ),
            'add_list_item' => $controller->addListItem(
                AddListItemCommand::fromArray($data, $userId)
            ),
            'invite_collaborator' => $controller->inviteCollaborator(
                InviteCollaboratorCommand::fromArray($data, $userId)
            ),
            'accept_collaboration' => $controller->acceptCollaboration(
                AcceptCollaborationCommand::fromArray($data, $userId)
            ),
            'remove_collaborator' => $controller->removeCollaborator(
                RemoveCollaboratorCommand::fromArray($data, $userId)
            ),
            'remove_list_item' => $controller->removeListItem(
                RemoveListItemCommand::fromArray($data, $userId)
            ),

            // CLUBS
            'create_club' => $controller->createClub(
                CreateClubCommand::fromArray($data, $userId)
            ),
            'get_my_clubs' => $controller->getMyClubs(
                new GetMyClubsQuery($userId)
            ),
            'get_club' => $controller->getClub(
                GetClubQuery::fromArray($data, $userId)
            ),
            'invite_to_club' => $controller->inviteToClub(
                InviteToClubCommand::fromArray($data, $userId)
            ),
            'accept_club_invitation' => $controller->acceptClubInvitation(
                AcceptClubInvitationCommand::fromArray($data, $userId)
            ),
            'leave_club' => $controller->leaveClub(
                LeaveClubCommand::fromArray($data, $userId)
            ),
            'set_club_pick' => $controller->setClubPick(
                SetClubPickCommand::fromArray($data, $userId)
            ),
            'finish_club_pick' => $controller->finishClubPick(
                FinishClubPickCommand::fromArray($data, $userId)
            ),
            'get_club_progress' => $controller->getClubProgress(
                GetClubProgressQuery::fromArray($data, $userId)
            ),
            'get_club_notes' => $controller->getClubNotes(
                GetClubNotesQuery::fromArray($data, $userId)
            ),

            // SOCIAL - Recommendations
            'send_recommendation' => $controller->sendRecommendation(
                SendRecommendationCommand::fromArray($data, $userId)
            ),
            'get_inbox' => $controller->getInbox(
                GetInboxQuery::fromArray($data, $userId)
            ),
            'get_inbox_count' => $controller->getInboxCount(
                new GetInboxCountQuery($userId)
            ),
            'resolve_recommendation' => $controller->resolveRecommendation(
                ResolveRecommendationCommand::fromArray($data, $userId)
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
            'AlbumController' => $this->albumController ??= $this->container->get(AlbumController::class),
            'AuthController' => $this->authController ??= $this->container->get(AuthController::class),
            'BookController' => $this->bookController ??= $this->container->get(BookController::class),
            'MovieController' => $this->movieController ??= $this->container->get(MovieController::class),
            'GameController' => $this->gameController ??= $this->container->get(GameController::class),
            'LibraryController' => $this->libraryController ??= $this->container->get(LibraryController::class),
            'LibraryXController' => $this->libraryXController ??= $this->container->get(LibraryXController::class),
            'StatsController' => $this->statsController ??= $this->container->get(StatsController::class),
            'VideoController' => $this->videoController ??= $this->container->get(VideoController::class),
            'SocialController' => $this->socialController ??= $this->container->get(SocialController::class),
            'FeedController' => $this->feedController ??= $this->container->get(FeedController::class),
            'ListController' => $this->listController ??= $this->container->get(ListController::class),
            'ClubController' => $this->clubController ??= $this->container->get(ClubController::class),
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
