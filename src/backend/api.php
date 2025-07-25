<?php
declare(strict_types=1);

// ini_set('display_errors', '1'); // Removed for cleanup
ini_set('log_errors', '1');     // Keep errors logged
error_reporting(E_ALL);       // Report all errors to the log

// Simple PSR-4 autoloader (restored)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    // Assumes api.php is in a directory like 'backend' 
    // and 'src' is a sibling to 'backend', containing the 'App' namespace root.
    $base_dir = __DIR__ . '/../src/'; 

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Not an App\ class, move to next autoloader
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});


use App\Infrastructure\Persistence\MySqlBookRepository;
use App\Infrastructure\Persistence\MySqlMovieRepository;
use App\Application\UseCase\Books\AddBookUseCase;
use App\Application\UseCase\GetLibraryUseCase;
use App\Application\UseCase\Books\DeleteBookUseCase;
use App\Application\UseCase\Books\UpdateBookRatingUseCase;
use App\Application\UseCase\Movies\AddMovieUseCase;
use App\Application\UseCase\Movies\GetMovieAllowedStatusesUseCase;
use App\Application\UseCase\Books\UpdateBookUserStatusesUseCase;
use App\Application\Domain\Model\Book;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight request for CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

$response = [
    'status' => 'error',
    'message' => 'An unexpected error occurred.',
    'data' => null
];
$statusCode = 500;

// Configuration
// $libraryFilePath = __DIR__ . '/my_library.json'; // No longer needed

try {
    // Initialization
    $bookRepository = new MySqlBookRepository();
    $movieRepository = new App\Infrastructure\Persistence\MySqlMovieRepository();

    // Use cases libros
    $addBookUseCase = new AddBookUseCase($bookRepository);
    $getLibraryUseCase = new GetLibraryUseCase($bookRepository);
    $deleteBookUseCase = new DeleteBookUseCase($bookRepository);
    $updateBookRatingUseCase = new UpdateBookRatingUseCase($bookRepository);
    $updateBookUserStatusesUseCase = new UpdateBookUserStatusesUseCase($bookRepository);

    // Use cases películas
    $addMovieUseCase = new App\Application\UseCase\Movies\AddMovieUseCase($movieRepository);
    $getMovieAllowedStatusesUseCase = new App\Application\UseCase\Movies\GetMovieAllowedStatusesUseCase($movieRepository);

    // Decode incoming JSON data
    $inputData = json_decode(file_get_contents('php://input'), true) ?? [];

    // Determine action
    $action = $inputData['action'] ?? $_REQUEST['action'] ?? null;

    switch ($action) {
        case 'save_library':
            // Obtiene la biblioteca actual y la guarda en my_library.json
            $library = $getLibraryUseCase->execute();
            $libraryArray = array_map(fn($book) => $book->toArray(), $library);
            $libraryFilePath = __DIR__ . '/my_library.json';
            $json = json_encode($libraryArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (file_put_contents($libraryFilePath, $json) !== false) {
                $response['status'] = 'success';
                $response['message'] = 'Library saved successfully.';
                $statusCode = 200;
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Failed to save library to file.';
                $statusCode = 500;
            }
            break;
        case 'get_book_allowed_statuses':
            $statuses = $bookRepository->fetchAllowedStatuses();
            $response['status'] = 'success';
            $response['message'] = 'Allowed book statuses retrieved.';
            $response['data'] = $statuses;
            $statusCode = 200;
            break;
        case 'add_book':
            if (!isset($inputData['book']) || !is_array($inputData['book'])) {
                throw new InvalidArgumentException('Book data is required for add_book action.');
            }
            $addedBook = $addBookUseCase->execute($inputData['book']);
            $response['status'] = 'success';
            $response['message'] = 'Book added: ' . $addedBook->getTitle();
            $response['data'] = $addedBook->toArray();
            $statusCode = 201; // Created
            break;

        case 'add_movie':
            if (!isset($inputData['movie']) || !is_array($inputData['movie'])) {
                throw new InvalidArgumentException('Movie data is required for add_movie action.');
            }
            $addedMovie = $addMovieUseCase->execute($inputData['movie']);
            $response['status'] = 'success';
            $response['message'] = 'Movie added: ' . $addedMovie->getTitle();
            $response['data'] = $addedMovie->toArray();
            $statusCode = 201;
            break;

        case 'get_library':
            $library = $getLibraryUseCase->execute();
            $response['status'] = 'success';
            $response['message'] = 'Library data retrieved.';
            $response['data'] = array_map(fn(Book $book) => $book->toArray(), $library);
            $statusCode = 200;
            break;

        case 'get_movies':
            $movies = $movieRepository->findAll();
            $response['status'] = 'success';
            $response['message'] = 'Movies data retrieved.';
            $response['data'] = array_map(function($movieArr) use ($movieRepository) {
                // Si ya es Movie, toArray, si es array, lo convertimos
                if ($movieArr instanceof App\Application\Domain\Model\Movie) {
                    return $movieArr->toArray();
                }
                return $movieArr;
            }, $movies);
            $statusCode = 200;
            break;

        case 'get_movie_allowed_statuses':
            $statuses = $getMovieAllowedStatusesUseCase->execute();
            $response['status'] = 'success';
            $response['message'] = 'Allowed movie statuses retrieved.';
            $response['data'] = $statuses;
            $statusCode = 200;
            break;

        case 'delete_book':
            if (!isset($inputData['isbn']) || !is_string($inputData['isbn'])) {
                throw new InvalidArgumentException('ISBN is required for delete_book action.');
            }
            $deleteBookUseCase->execute($inputData['isbn']);
            $response['status'] = 'success';
            $response['message'] = 'Book deleted: ' . $inputData['isbn'];
            $statusCode = 200;
            break;

        case 'delete_movie':
            if (!isset($inputData['id']) || !is_string($inputData['id'])) {
                throw new InvalidArgumentException('ID is required for delete_movie action.');
            }
            $movieRepository->deleteById($inputData['id']);
            $response['status'] = 'success';
            $response['message'] = 'Movie deleted: ' . $inputData['id'];
            $statusCode = 200;
            break;

        case 'update_book_rating':
            if (!isset($inputData['isbn']) || !is_string($inputData['isbn'])) {
                throw new InvalidArgumentException('ISBN is required for update_book_rating.');
            }
            // Rating can be null, float, or 0 (which will be treated as null by use case/entity)
            $rating = null;
            if (isset($inputData['rating'])) {
                if (is_numeric($inputData['rating'])) {
                    $rating = (float)$inputData['rating'];
                    if ($rating == 0) { // Treat explicit 0 as unrate intention
                        $rating = null;
                    }
                } else {
                    // If rating is present but not numeric (and not null), it's an issue.
                    // The UseCase/Book entity will also validate this, but good to be clear.
                    throw new InvalidArgumentException('Rating must be a number or null.');
                }
            }
            
            $updateBookRatingUseCase->execute($inputData['isbn'], $rating);
            $response['status'] = 'success';
            $response['message'] = 'Rating updated for ISBN ' . $inputData['isbn'];
            $statusCode = 200;
            break;
        case 'update_book_user_statuses':
            if (!isset($inputData['isbn']) || !is_string($inputData['isbn'])) {
                throw new InvalidArgumentException('ISBN is required for update_book_rating.');
            }
            // Statuses can't be null, or empty
            $statuses = null;
            if (isset($inputData['statuses'])) {
                if (is_array($inputData['statuses']) && !empty($inputData['statuses'])) {
                    $statuses = $inputData['statuses'];
                } else {
                    throw new InvalidArgumentException('Statuses must be a non-empty array.');
                }
            }

            $updateBookUserStatusesUseCase->execute($inputData['isbn'], $statuses);
            $response['status'] = 'success';
            $response['message'] = 'User statuses updated for ISBN ' . $inputData['isbn'];
            $statusCode = 200;
            break;        
        case 'ping': // Example of a simple non-data action
            $response['status'] = 'success';
            $response['message'] = 'pong';
            $response['data'] = null;
            $statusCode = 200;
            break;

        default:
            if (isset($inputData['message']) && $action === null) { // Keep old message echo behavior if no other action matches
                 $response['status'] = 'success';
                 $response['message'] = 'Original message endpoint: Message received: ' . $inputData['message'];
                 $statusCode = 200;
            } else {
                throw new InvalidArgumentException('No valid action specified or missing required parameters. Action: ' . ($action ?? 'null'));
            }
    }

    // Default response if no action is matched by the switch (if you have one)
    // For this test, we might not even reach a switch if MySqlBookRepository fails
    if (!isset($response['status']) || $response['status'] !== 'success'){
        $response['status'] = 'info';
        $response['message'] = 'API script executed, but no specific action was processed successfully (or MySqlBookRepository loaded correctly, and debug exit was removed).';
        $statusCode = 200;
    }    

} catch (InvalidArgumentException $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
    $statusCode = 400;
} catch (RuntimeException $e) {
    error_log("Runtime Exception in API: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
    $response['status'] = 'error';
    $response['message'] = 'A server runtime error occurred. Please try again later.'; // User-friendly message
    $statusCode = 500;
} catch (Throwable $e) {
    error_log("General Throwable in API: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\nStack Trace:\n" . $e->getTraceAsString());
    $response['status'] = 'error';
    $response['message'] = 'An unexpected server error occurred.'; // Generic message for production/cleanup
    // unset($response['trace']); // Ensure trace is not sent if it was added previously for debug
    $statusCode = 500;
}

http_response_code($statusCode);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>