<?php

declare(strict_types=1);

use App\Infrastructure\Middleware\AuthenticationMiddleware;
use App\Infrastructure\Middleware\CSRFMiddleware;
use App\Infrastructure\Middleware\LoggingMiddleware;
use App\Infrastructure\Middleware\ValidationMiddleware;

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
 */
return [
    // ============================================================================
    // AUTH ROUTES - No authentication required (public endpoints)
    // ============================================================================
    'login' => [
        'controller' => ['AuthController', 'login'],
        'middleware' => [LoggingMiddleware::class],
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
    
    'log_frontend' => [
        'controller' => ['AuthController', 'logFrontend'],
        'middleware' => [LoggingMiddleware::class],
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
        'middleware' => [LoggingMiddleware::class],
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
            AuthenticationMiddleware::class
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

    'update_reading_progress_with_session' => [
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
];
