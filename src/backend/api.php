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
use App\Application\UseCase\AddBookUseCase;
use App\Application\UseCase\GetLibraryUseCase;
use App\Application\UseCase\DeleteBookUseCase;
use App\Application\UseCase\UpdateBookRatingUseCase;
use App\Application\Domain\Model\Book; // For type hinting and potentially Book::toArray

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

    // Use cases (could be managed by a simple DI container in a larger app)
    $addBookUseCase = new AddBookUseCase($bookRepository);
    $getLibraryUseCase = new GetLibraryUseCase($bookRepository);
    $deleteBookUseCase = new DeleteBookUseCase($bookRepository);
    $updateBookRatingUseCase = new UpdateBookRatingUseCase($bookRepository);

    // Decode incoming JSON data
    $inputData = json_decode(file_get_contents('php://input'), true) ?? [];

    // Determine action
    $action = $inputData['action'] ?? $_REQUEST['action'] ?? null;
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($action) && isset($_GET['action']) && $_GET['action'] == 'get_library') {
        // Compatibility for existing GET ?action=get_library
        $action = 'get_library';
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($action)) {
        // Default GET action if no specific action is provided in query params (e.g. simple health check or root access)
        // For now, let's assume if it's GET and no action, it might be an attempt to get library, but we should be explicit.
        // Let's make get_library explicit for GET requests.
    }

    switch ($action) {
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

        case 'get_library':
            $library = $getLibraryUseCase->execute();
            $response['status'] = 'success';
            $response['message'] = 'Library data retrieved.';
            $response['data'] = array_map(fn(Book $book) => $book->toArray(), $library);
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