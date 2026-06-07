<?php

declare(strict_types=1);

use App\Infrastructure\Middleware\AuthenticationMiddleware;
use App\Infrastructure\Middleware\CSRFMiddleware;
use App\Infrastructure\Middleware\LoggingMiddleware;
use App\Infrastructure\Middleware\ValidationMiddleware;
use App\Infrastructure\RateLimit\RateLimitMiddleware;

/**
 * Routes Configuration
 * 
 * Defines all application routes with their corresponding controller methods
 * and middleware stacks. This configuration eliminates duplicate auth/CSRF
 * checks across the ActionRouter by declaring middleware declaratively.
 * 
 * Structure:
 * - 'controller': [ControllerClass::class, 'methodName']
 * - 'middleware': Array of middleware class names to apply
 * - 'validation': Optional array of required fields for ValidationMiddleware
 *
 * Rate limiting: every route gets a global default RateLimitMiddleware
 * (env-configured, see RATE_LIMIT_*) applied automatically by ActionRouter.
 * To use a stricter/looser limit, declare RateLimitMiddleware explicitly with
 * config, e.g. [RateLimitMiddleware::class, ['limit' => 5, 'window' => 300,
 * 'by' => 'ip']] — that overrides the global default for the route.
 */
return [
    // ============================================================================
    // AUTH ROUTES - No authentication required (public endpoints)
    // ============================================================================
    'login' => [
        'controller' => ['AuthController', 'login'],
        'middleware' => [
            // Strict brute-force protection: 5 attempts / 5 min per IP.
            [RateLimitMiddleware::class, ['limit' => 5, 'window' => 300, 'by' => 'ip']],
            LoggingMiddleware::class
        ],
        'validation' => []
    ],
    
    'logout' => [
        'controller' => ['AuthController', 'logout'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],
    
    'check_auth' => [
        'controller' => ['AuthController', 'checkAuth'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],
    
    // Frontend log shipping: auth is optional (the controller adds user context
    // from the session when present). Not requiring auth avoids 401 noise from
    // logs flushed without an active session; the global rate limiter still
    // protects these endpoints from abuse.
    'log_frontend' => [
        'controller' => ['AuthController', 'logFrontend'],
        'middleware' => [
            LoggingMiddleware::class
        ],
        'validation' => []
    ],

    'log_frontend_batch' => [
        'controller' => ['AuthController', 'logFrontendBatch'],
        'middleware' => [
            LoggingMiddleware::class
        ],
        'validation' => []
    ],

    'update_user_profile' => [
        'controller' => ['AuthController', 'updateProfile'],
        'middleware' => [
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            LoggingMiddleware::class
        ],
        'validation' => []
    ],

    // ============================================================================
    // BOOKS ROUTES - Write operations require Auth + CSRF
    // ============================================================================
    'add_book' => [
        'controller' => ['BookController', 'addBook'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['book']]]
        ],
        'validation' => ['book']
    ],
    
    'delete_book' => [
        'controller' => ['BookController', 'deleteBook'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn']]]
        ],
        'validation' => ['isbn']
    ],
    
    'update_book_rating' => [
        'controller' => ['BookController', 'updateBookRating'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn', 'rating']]]
        ],
        'validation' => ['isbn', 'rating']
    ],
    
    'update_book_user_statuses' => [
        'controller' => ['BookController', 'updateBookUserStatuses'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn', 'statuses']]]
        ],
        'validation' => ['isbn', 'statuses']
    ],
    
    'edit_user_book' => [
        'controller' => ['BookController', 'editUserBook'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn']]]
        ],
        'validation' => ['isbn']
    ],
    
    // BOOKS - Read operations (Auth only, no CSRF)
    'get_book_allowed_statuses' => [
        'controller' => ['BookController', 'getBookAllowedStatuses'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],
    
    'get_books' => [
        'controller' => ['BookController', 'getAllBooks'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],
    
    'get_library' => [
        'controller' => ['BookController', 'getBooks'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_trending_books' => [
        'controller' => ['BookController', 'getTrendingBooks'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    // ============================================================================
    // WORKS ROUTES - Read operations (public endpoints for search)
    // These endpoints allow searching and viewing book works/editions without authentication
    'search_works' => [
        'controller' => ['BookController', 'searchWorks'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],
    
    'get_work' => [
        'controller' => ['BookController', 'getWork'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],
    
    'get_work_editions' => [
        'controller' => ['BookController', 'getWorkEditions'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],
    
    'validate_isbn' => [
        'controller' => ['BookController', 'validateISBN'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],

    // Tighter limit to protect the third-party Google Books API quota.
    'search_google_books_isbn' => [
        'controller' => ['BookController', 'searchGoogleBooksByISBN'],
        'middleware' => [
            [RateLimitMiddleware::class, ['limit' => 30, 'window' => 60, 'by' => 'ip']],
            LoggingMiddleware::class
        ],
        'validation' => []
    ],

    'get_openlibrary_book_by_isbn' => [
        'controller' => ['BookController', 'getOpenLibraryBookByISBN'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],

    // BOOKS - Edition Notes (Auth + CSRF for write operations)
    'add_edition_note' => [
        'controller' => ['BookController', 'addEditionNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['userEditionId', 'pageNumber']]]
        ],
        'validation' => ['userEditionId', 'pageNumber']
    ],

    'update_edition_note' => [
        'controller' => ['BookController', 'updateEditionNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId']]]
        ],
        'validation' => ['noteId']
    ],

    'delete_edition_note' => [
        'controller' => ['BookController', 'deleteEditionNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId']]]
        ],
        'validation' => ['noteId']
    ],

    'get_edition_notes' => [
        'controller' => ['BookController', 'getEditionNotes'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['userEditionId']]]
        ],
        'validation' => ['userEditionId']
    ],

    'get_edition_note' => [
        'controller' => ['BookController', 'getEditionNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId']]]
        ],
        'validation' => ['noteId']
    ],

    // BOOK TAGS ROUTES
    'get_user_book_tags' => [
        'controller' => ['BookController', 'getUserBookTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'create_user_book_tag' => [
        'controller' => ['BookController', 'createUserBookTag'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['name']]]
        ],
        'validation' => ['name']
    ],

    'get_book_tags' => [
        'controller' => ['BookController', 'getBookTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn']]]
        ],
        'validation' => ['isbn']
    ],

    'update_book_tags' => [
        'controller' => ['BookController', 'updateBookTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn', 'tag_ids']]]
        ],
        'validation' => ['isbn', 'tag_ids']
    ],

    // ============================================================================
    // MOVIES ROUTES - Write operations require Auth + CSRF
    // ============================================================================
    'add_movie' => [
        'controller' => ['MovieController', 'addMovie'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['movie']]]
        ],
        'validation' => ['movie']
    ],
    
    'delete_movie' => [
        'controller' => ['MovieController', 'deleteMovie'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['imdbID', 'id']]] // Either imdbID or id required
        ],
        'validation' => [] // Handled in controller logic
    ],
    
    'update_movie_rating' => [
        'controller' => ['MovieController', 'updateMovieRating'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['rating']]]
        ],
        'validation' => ['rating']
    ],
    
    'update_movie_user_statuses' => [
        'controller' => ['MovieController', 'updateMovieUserStatuses'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['statuses']]]
        ],
        'validation' => ['statuses']
    ],
    
    'edit_user_movie' => [
        'controller' => ['MovieController', 'editUserMovie'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn']]]
        ],
        'validation' => ['isbn']
    ],
    
    // MOVIES - Read operations (Auth only, no CSRF)
    'get_movie_allowed_statuses' => [
        'controller' => ['MovieController', 'getMovieAllowedStatuses'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],
    
    'get_movies' => [
        'controller' => ['MovieController', 'getMovies'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_trending_movies' => [
        'controller' => ['MovieController', 'getTrendingMovies'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    // OMDB PROXY - Public read-only endpoints (no auth, no CSRF)
    // Tighter limit to protect the third-party OMDB API quota.
    'search_movies_omdb' => [
        'controller' => ['MovieController', 'searchMoviesOmdb'],
        'middleware' => [
            [RateLimitMiddleware::class, ['limit' => 30, 'window' => 60, 'by' => 'ip']],
            LoggingMiddleware::class
        ],
        'validation' => []
    ],

    'get_movie_details_omdb' => [
        'controller' => ['MovieController', 'getMovieDetailsOmdb'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],

    'get_season_episodes_omdb' => [
        'controller' => ['MovieController', 'getSeasonEpisodesOmdb'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],

    // MOVIE TAGS ROUTES
    'get_user_movie_tags' => [
        'controller' => ['MovieController', 'getUserMovieTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'create_user_movie_tag' => [
        'controller' => ['MovieController', 'createUserMovieTag'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['name']]]
        ],
        'validation' => ['name']
    ],

    'get_movie_tags' => [
        'controller' => ['MovieController', 'getMovieTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn']]]
        ],
        'validation' => ['isbn']
    ],

    'update_movie_tags' => [
        'controller' => ['MovieController', 'updateMovieTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn', 'tag_ids']]]
        ],
        'validation' => ['isbn', 'tag_ids']
    ],

    // MOVIE NOTES ROUTES
    'get_movie_notes' => [
        'controller' => ['MovieController', 'getMovieNotes'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'add_movie_note' => [
        'controller' => ['MovieController', 'addMovieNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['movieIsbn', 'noteText']]]
        ],
        'validation' => ['movieIsbn', 'noteText']
    ],

    'update_movie_note' => [
        'controller' => ['MovieController', 'updateMovieNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId', 'noteText']]]
        ],
        'validation' => ['noteId', 'noteText']
    ],

    'delete_movie_note' => [
        'controller' => ['MovieController', 'deleteMovieNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId']]]
        ],
        'validation' => ['noteId']
    ],

    // SERIES SEASON TRACKING
    'track_series_season' => [
        'controller' => ['MovieController', 'trackSeriesSeason'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['seriesIsbn', 'seasonNumber']]]
        ],
        'validation' => ['seriesIsbn', 'seasonNumber']
    ],

    'get_series_progress' => [
        'controller' => ['MovieController', 'getSeriesProgress'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    // ============================================================================
    // LIBRARY ROUTES
    // ============================================================================
    'get_library_items' => [
        'controller' => ['LibraryController', 'getLibraryItems'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],
    
    'save_library' => [
        'controller' => ['LibraryController', 'saveLibrary'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class
        ],
        'validation' => []
    ],
    
    'import_data' => [
        'controller' => ['LibraryController', 'importData'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['processedData']]]
        ],
        'validation' => ['processedData']
    ],
    
    'ping' => [
        'controller' => ['LibraryController', 'ping'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],

    'get_ownership_formats' => [
        'controller' => ['LibraryController', 'getOwnershipFormats'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['entityType']]]
        ],
        'validation' => ['entityType']
    ],

    // ============================================================================
    // LIBRARYX ROUTES
    // ============================================================================
    'libraryx_get_urls' => [
        'controller' => ['LibraryXController', 'getUrls'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],
    
    'libraryx_update_urls' => [
        'controller' => ['LibraryXController', 'updateUrls'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class
        ],
        'validation' => []
    ],

    // ============================================================================
    // STATISTICS ROUTES
    // ============================================================================
    'get_book_stats' => [
        'controller' => ['StatsController', 'getBookStats'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],
    
    'get_movie_stats' => [
        'controller' => ['StatsController', 'getMovieStats'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_game_stats' => [
        'controller' => ['StatsController', 'getGameStats'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_album_stats' => [
        'controller' => ['StatsController', 'getAlbumStats'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_video_stats' => [
        'controller' => ['StatsController', 'getVideoStats'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    // ============================================================================
    // READING SESSIONS ROUTES
    // ============================================================================
    'create_reading_session' => [
        'controller' => ['BookController', 'createReadingSession'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn']]]
        ],
        'validation' => ['isbn']
    ],

    'get_active_reading_session' => [
        'controller' => ['BookController', 'getActiveReadingSession'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'complete_reading_session' => [
        'controller' => ['BookController', 'completeReadingSession'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['sessionId', 'endPage']]]
        ],
        'validation' => ['sessionId', 'endPage']
    ],

    'update_reading_progress' => [
        'controller' => ['BookController', 'updateReadingProgressWithSession'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn', 'currentPage']]]
        ],
        'validation' => ['isbn', 'currentPage']
    ],

    'get_reading_session_history' => [
        'controller' => ['BookController', 'getReadingSessionHistory'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_progress_history' => [
        'controller' => ['BookController', 'getProgressHistory'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['isbn']]]
        ],
        'validation' => ['isbn']
    ],

    'get_session_progress' => [
        'controller' => ['BookController', 'getSessionProgress'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['sessionId']]]
        ],
        'validation' => ['sessionId']
    ],

    'get_user_active_reading_sessions' => [
        'controller' => ['BookController', 'getUserActiveReadingSessions'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'pause_reading_session' => [
        'controller' => ['BookController', 'pauseReadingSession'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['sessionId']]]
        ],
        'validation' => ['sessionId']
    ],

    'resume_reading_session' => [
        'controller' => ['BookController', 'resumeReadingSession'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['sessionId']]]
        ],
        'validation' => ['sessionId']
    ],

    'delete_reading_session' => [
        'controller' => ['BookController', 'deleteReadingSession'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['sessionId']]]
        ],
        'validation' => ['sessionId']
    ],

    'get_book_reading_summary' => [
        'controller' => ['BookController', 'getBookReadingSummary'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_detailed_progress_history' => [
        'controller' => ['BookController', 'getDetailedProgressHistory'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_user_reading_stats' => [
        'controller' => ['BookController', 'getUserReadingStats'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_current_reading_sessions' => [
        'controller' => ['BookController', 'getCurrentReadingSessions'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    // ============================================================================
    // GAMES ROUTES - Write operations require Auth + CSRF
    // ============================================================================
    'add_game' => [
        'controller' => ['GameController', 'addGame'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['game']]]
        ],
        'validation' => ['game']
    ],
    
    'delete_game' => [
        'controller' => ['GameController', 'deleteGame'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['gameId']]]
        ],
        'validation' => ['gameId']
    ],
    
    'update_game_rating' => [
        'controller' => ['GameController', 'updateGameRating'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['gameId', 'rating']]]
        ],
        'validation' => ['gameId', 'rating']
    ],
    
    'update_game_user_statuses' => [
        'controller' => ['GameController', 'updateGameUserStatuses'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['gameId', 'statuses']]]
        ],
        'validation' => ['gameId', 'statuses']
    ],
    
    'edit_user_game' => [
        'controller' => ['GameController', 'editUserGame'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['gameId']]]
        ],
        'validation' => ['gameId']
    ],
    
    // GAMES - Read operations (Auth only, no CSRF)
    'get_game_allowed_statuses' => [
        'controller' => ['GameController', 'getGameAllowedStatuses'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],
    
    'get_games' => [
        'controller' => ['GameController', 'getGames'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_trending_games' => [
        'controller' => ['GameController', 'getTrendingGames'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    // GAME TAGS ROUTES
    'get_user_game_tags' => [
        'controller' => ['GameController', 'getUserGameTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'create_user_game_tag' => [
        'controller' => ['GameController', 'createUserGameTag'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['name']]]
        ],
        'validation' => ['name']
    ],

    'delete_user_game_tag' => [
        'controller' => ['GameController', 'deleteUserGameTag'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['tagId']]]
        ],
        'validation' => ['tagId']
    ],

    'get_game_tags' => [
        'controller' => ['GameController', 'getGameTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'assign_tag_to_game' => [
        'controller' => ['GameController', 'assignTagToGame'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['gameId', 'tagId']]]
        ],
        'validation' => ['gameId', 'tagId']
    ],

    'remove_tag_from_game' => [
        'controller' => ['GameController', 'removeTagFromGame'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['gameId', 'tagId']]]
        ],
        'validation' => ['gameId', 'tagId']
    ],

    'update_game_tags' => [
        'controller' => ['GameController', 'updateGameTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['gameId', 'tag_ids']]]
        ],
        'validation' => ['gameId', 'tag_ids']
    ],

    // GAME NOTES ROUTES
    'get_game_notes' => [
        'controller' => ['GameController', 'getGameNotes'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'add_game_note' => [
        'controller' => ['GameController', 'addGameNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['gameId', 'noteText']]]
        ],
        'validation' => ['gameId', 'noteText']
    ],

    'update_game_note' => [
        'controller' => ['GameController', 'updateGameNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId', 'noteText']]]
        ],
        'validation' => ['noteId', 'noteText']
    ],

    'delete_game_note' => [
        'controller' => ['GameController', 'deleteGameNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId']]]
        ],
        'validation' => ['noteId']
    ],

    // IGDB API CONFIGURATION (Public endpoints - no auth required)
    'get_igdb_config' => [
        'controller' => ['GameController', 'getIGDBConfig'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],

    'get_igdb_token' => [
        'controller' => ['GameController', 'getIGDBToken'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],

    // IGDB API PROXY ENDPOINTS (Public - to avoid CORS issues)
    'search_igdb_games' => [
        'controller' => ['GameController', 'searchIGDBGames'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => ['query']
    ],

    'get_igdb_game_by_id' => [
        'controller' => ['GameController', 'getIGDBGameById'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => ['gameId']
    ],

    'get_igdb_game_details' => [
        'controller' => ['GameController', 'getIGDBGameDetails'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => ['gameId']
    ],

    // ============================================================================
    // ALBUMS ROUTES - Write operations require Auth + CSRF
    // ============================================================================
    'add_album' => [
        'controller' => ['AlbumController', 'addAlbum'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['album']]]
        ],
        'validation' => ['album']
    ],

    'delete_album' => [
        'controller' => ['AlbumController', 'deleteAlbum'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['albumId']]]
        ],
        'validation' => ['albumId']
    ],

    'update_album_rating' => [
        'controller' => ['AlbumController', 'updateAlbumRating'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['albumId', 'rating']]]
        ],
        'validation' => ['albumId', 'rating']
    ],

    'update_album_user_statuses' => [
        'controller' => ['AlbumController', 'updateAlbumUserStatuses'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['albumId', 'statuses']]]
        ],
        'validation' => ['albumId', 'statuses']
    ],

    'edit_user_album' => [
        'controller' => ['AlbumController', 'editUserAlbum'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['albumId']]]
        ],
        'validation' => ['albumId']
    ],

    // ALBUMS - Read operations (Auth only, no CSRF)
    'get_album_allowed_statuses' => [
        'controller' => ['AlbumController', 'getAlbumAllowedStatuses'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],

    'get_albums' => [
        'controller' => ['AlbumController', 'getAlbums'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_trending_albums' => [
        'controller' => ['AlbumController', 'getTrendingAlbums'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    // ALBUM TAGS ROUTES
    'get_user_album_tags' => [
        'controller' => ['AlbumController', 'getUserAlbumTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'create_user_album_tag' => [
        'controller' => ['AlbumController', 'createUserAlbumTag'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['name']]]
        ],
        'validation' => ['name']
    ],

    'delete_user_album_tag' => [
        'controller' => ['AlbumController', 'deleteUserAlbumTag'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['tagId']]]
        ],
        'validation' => ['tagId']
    ],

    'get_album_tags' => [
        'controller' => ['AlbumController', 'getAlbumTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'update_album_tags' => [
        'controller' => ['AlbumController', 'updateAlbumTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['albumId', 'tag_ids']]]
        ],
        'validation' => ['albumId', 'tag_ids']
    ],

    // ALBUM NOTES ROUTES
    'get_album_notes' => [
        'controller' => ['AlbumController', 'getAlbumNotes'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'add_album_note' => [
        'controller' => ['AlbumController', 'addAlbumNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['albumId', 'noteText']]]
        ],
        'validation' => ['albumId', 'noteText']
    ],

    'update_album_note' => [
        'controller' => ['AlbumController', 'updateAlbumNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId', 'noteText']]]
        ],
        'validation' => ['noteId', 'noteText']
    ],

    'delete_album_note' => [
        'controller' => ['AlbumController', 'deleteAlbumNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId']]]
        ],
        'validation' => ['noteId']
    ],

    // SPOTIFY API PROXY ENDPOINTS (Public - to avoid CORS issues)
    'search_spotify_albums' => [
        'controller' => ['AlbumController', 'searchSpotifyAlbums'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => ['query']
    ],

    'get_spotify_album' => [
        'controller' => ['AlbumController', 'getSpotifyAlbum'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => ['spotifyId']
    ],

    'get_spotify_artist' => [
        'controller' => ['AlbumController', 'getSpotifyArtist'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => ['artistId']
    ],

    'get_spotify_album_tracks' => [
        'controller' => ['AlbumController', 'getSpotifyAlbumTracks'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => ['spotifyId']
    ],

    'get_spotify_new_releases' => [
        'controller' => ['AlbumController', 'getSpotifyNewReleases'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],

    'get_listening_stats' => [
        'controller' => ['AlbumController', 'getListeningStats'],
        'middleware' => [
            AuthenticationMiddleware::class,
            LoggingMiddleware::class
        ],
        'validation' => []
    ],

    // =========================================================================
    // VIDEOS — YouTube video management
    // =========================================================================

    'add_video' => [
        'controller' => ['VideoController', 'addVideo'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['title']]]
        ],
        'validation' => ['title']
    ],

    'delete_video' => [
        'controller' => ['VideoController', 'deleteVideo'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['youtubeId']]]
        ],
        'validation' => ['youtubeId']
    ],

    'update_video_rating' => [
        'controller' => ['VideoController', 'updateVideoRating'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['youtubeId', 'rating']]]
        ],
        'validation' => ['youtubeId', 'rating']
    ],

    'update_video_user_statuses' => [
        'controller' => ['VideoController', 'updateVideoUserStatuses'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['youtubeId', 'statuses']]]
        ],
        'validation' => ['youtubeId', 'statuses']
    ],

    'edit_user_video' => [
        'controller' => ['VideoController', 'editUserVideo'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['youtubeId']]]
        ],
        'validation' => ['youtubeId']
    ],

    // VIDEO — Read operations
    'get_video_allowed_statuses' => [
        'controller' => ['VideoController', 'getVideoAllowedStatuses'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => []
    ],

    'get_videos' => [
        'controller' => ['VideoController', 'getVideos'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'get_trending_videos' => [
        'controller' => ['VideoController', 'getTrendingVideos'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    // VIDEO TAGS
    'get_user_video_tags' => [
        'controller' => ['VideoController', 'getUserVideoTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'create_user_video_tag' => [
        'controller' => ['VideoController', 'createUserVideoTag'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['name']]]
        ],
        'validation' => ['name']
    ],

    'delete_user_video_tag' => [
        'controller' => ['VideoController', 'deleteUserVideoTag'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['tagId']]]
        ],
        'validation' => ['tagId']
    ],

    'get_video_tags' => [
        'controller' => ['VideoController', 'getVideoTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'update_video_tags' => [
        'controller' => ['VideoController', 'updateVideoTags'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['videoId', 'tag_ids']]]
        ],
        'validation' => ['videoId', 'tag_ids']
    ],

    // VIDEO NOTES
    'get_video_notes' => [
        'controller' => ['VideoController', 'getVideoNotes'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class
        ],
        'validation' => []
    ],

    'add_video_note' => [
        'controller' => ['VideoController', 'addVideoNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['youtubeId', 'noteText']]]
        ],
        'validation' => ['youtubeId', 'noteText']
    ],

    'update_video_note' => [
        'controller' => ['VideoController', 'updateVideoNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId', 'noteText']]]
        ],
        'validation' => ['noteId', 'noteText']
    ],

    'delete_video_note' => [
        'controller' => ['VideoController', 'deleteVideoNote'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['noteId']]]
        ],
        'validation' => ['noteId']
    ],

    // YOUTUBE API PROXY
    'search_youtube_videos' => [
        'controller' => ['VideoController', 'searchVideos'],
        'middleware' => [LoggingMiddleware::class],
        'validation' => ['query']
    ],

    // ============================================================================
    // SOCIAL ROUTES - Friends + Feed
    // ============================================================================

    // Write operations (require Auth + CSRF)
    'send_friend_request' => [
        'controller' => ['SocialController', 'sendFriendRequest'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['addresseeId']]]
        ],
        'validation' => ['addresseeId']
    ],

    'accept_friend_request' => [
        'controller' => ['SocialController', 'acceptFriendRequest'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['friendshipId']]]
        ],
        'validation' => ['friendshipId']
    ],

    'reject_friend_request' => [
        'controller' => ['SocialController', 'rejectFriendRequest'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['friendshipId']]]
        ],
        'validation' => ['friendshipId']
    ],

    'remove_friend' => [
        'controller' => ['SocialController', 'removeFriend'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['friendId']]]
        ],
        'validation' => ['friendId']
    ],

    'update_privacy_settings' => [
        'controller' => ['FeedController', 'updatePrivacySettings'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            CSRFMiddleware::class,
        ],
        'validation' => []
    ],

    // Read operations (require Auth only)
    'get_friends' => [
        'controller' => ['SocialController', 'getFriends'],
        'middleware' => [LoggingMiddleware::class, AuthenticationMiddleware::class],
        'validation' => []
    ],

    'get_friend_requests' => [
        'controller' => ['SocialController', 'getFriendRequests'],
        'middleware' => [LoggingMiddleware::class, AuthenticationMiddleware::class],
        'validation' => []
    ],

    'search_users' => [
        'controller' => ['SocialController', 'searchUsers'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['term']]]
        ],
        'validation' => ['term']
    ],

    'get_public_profile' => [
        'controller' => ['SocialController', 'getPublicProfile'],
        'middleware' => [
            LoggingMiddleware::class,
            AuthenticationMiddleware::class,
            [ValidationMiddleware::class, ['required' => ['username']]]
        ],
        'validation' => ['username']
    ],

    'get_feed' => [
        'controller' => ['FeedController', 'getFeed'],
        'middleware' => [LoggingMiddleware::class, AuthenticationMiddleware::class],
        'validation' => []
    ],

    'get_privacy_settings' => [
        'controller' => ['FeedController', 'getPrivacySettings'],
        'middleware' => [LoggingMiddleware::class, AuthenticationMiddleware::class],
        'validation' => []
    ],
];

