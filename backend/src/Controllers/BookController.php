<?php
namespace App\Controllers;

use App\Domain\UseCases\Books\AddBookUseCase;
use App\Domain\UseCases\Books\DeleteBookUseCase;
use App\Domain\UseCases\Books\UpdateBookRatingUseCase;
use App\Domain\UseCases\Books\UpdateBookUserStatusesUseCase;
use App\Domain\UseCases\Books\GetBooksUseCase;
use App\Domain\UseCases\Books\GetAllBooksUseCase;
use App\Domain\Repository\BookRepositoryInterface;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Domain\UseCases\Books\EditUserBookUseCase;

class BookController extends BaseController implements Contracts\BookControllerInterface
{

    private AddBookUseCase $addBookUseCase;
    private DeleteBookUseCase $deleteBookUseCase;
    private UpdateBookRatingUseCase $updateBookRatingUseCase;
    private UpdateBookUserStatusesUseCase $updateBookUserStatusesUseCase;
    private GetBooksUseCase $getBooksUseCase;
    private GetAllBooksUseCase $getAllBooksUseCase;
    private BookRepositoryInterface $bookRepository;
    private AuthMiddleware $authMiddleware;
    private EditUserBookUseCase $editUserBookUseCase;

    public function __construct(
        AddBookUseCase $addBookUseCase,
        DeleteBookUseCase $deleteBookUseCase,
        UpdateBookRatingUseCase $updateBookRatingUseCase,
        UpdateBookUserStatusesUseCase $updateBookUserStatusesUseCase,
        GetBooksUseCase $getBooksUseCase,
        GetAllBooksUseCase $getAllBooksUseCase,
        BookRepositoryInterface $bookRepository,
        AuthMiddleware $authMiddleware,
        EditUserBookUseCase $editUserBookUseCase
    ) {
        $this->addBookUseCase = $addBookUseCase;
        $this->deleteBookUseCase = $deleteBookUseCase;
        $this->updateBookRatingUseCase = $updateBookRatingUseCase;
        $this->updateBookUserStatusesUseCase = $updateBookUserStatusesUseCase;
        $this->getBooksUseCase = $getBooksUseCase;
        $this->getAllBooksUseCase = $getAllBooksUseCase;
        $this->bookRepository = $bookRepository;
        $this->authMiddleware = $authMiddleware;
        $this->editUserBookUseCase = $editUserBookUseCase;
    }

    public function addBook(array $bookData, int $userId): array
    {
        if (empty($bookData)) {
            throw new \InvalidArgumentException('Book data is required for add_book action.');
        }
        
        $addedBook = $this->addBookUseCase->execute($bookData, $userId);
        return $this->successResponse('Book added: ' . $addedBook->getTitle(), $addedBook->toArray(), 201);
    }

    public function deleteBook(string $isbn, int $userId): array
    {
        if (empty($isbn)) {
            throw new \InvalidArgumentException('ISBN is required for delete_book action.');
        }
        
        $this->deleteBookUseCase->execute($userId, $isbn);
        return $this->successResponse('Book removed from your library: ' . $isbn);
    }

    public function updateBookRating(string $isbn, ?float $rating, int $userId): array
    {
        if (empty($isbn)) {
            throw new \InvalidArgumentException('ISBN is required for update_book_rating.');
        }
        
        // Rating can be null, float, or 0 (which will be treated as null by use case/entity)
        if ($rating !== null) {
            if (!is_numeric($rating)) {
                throw new \InvalidArgumentException('Rating must be a number or null.');
            }
            $rating = (float)$rating;
            if ($rating == 0) { // Treat explicit 0 as unrate intention
                $rating = null;
            }
        }
        
        $this->updateBookRatingUseCase->execute($userId, $isbn, $rating);
        return $this->successResponse('Rating updated for ISBN ' . $isbn);
    }

    public function updateBookUserStatuses(string $isbn, array $statuses, int $userId): array
    {
        if (empty($isbn)) {
            throw new \InvalidArgumentException('ISBN is required for update_book_user_statuses.');
        }
        
        if (empty($statuses)) {
            throw new \InvalidArgumentException('Statuses must be a non-empty array.');
        }
        
        $this->updateBookUserStatusesUseCase->execute($userId, $isbn, $statuses);
        return $this->successResponse('User statuses updated for ISBN ' . $isbn);
    }

    public function getBookAllowedStatuses(): array
    {
        $statuses = $this->bookRepository->fetchAllowedStatuses();
        return $this->successResponse('Allowed book statuses retrieved.', $statuses);
    }

    public function getBooks(int $userId): array
    {
        $books = $this->getBooksUseCase->execute($userId);
        return $this->successResponse('Library data retrieved.', $books);
    }

    public function getAllBooks(): array
    {
        $books = $this->getAllBooksUseCase->execute();
        return $this->successResponse('All books retrieved.', $books);
    }

    /**
     * Modifica todos los aspectos de un user_book: datos principales, tags y notas por página.
     * @param string $isbn
     * @param int $userId
     * @param array $data
     * @param array $tags
     * @param array $notes
     * @return array
     */
    public function editUserBook(string $isbn, int $userId, array $data = [], array $tags = [], array $notes = []): array
    {
        // Validación básica
        if (empty($isbn) || empty($userId)) {
            throw new \InvalidArgumentException('ISBN y userId son requeridos para editar user_book.');
        }

        try {
            $this->editUserBookUseCase->execute($userId, $isbn, $data, $tags, $notes);
            return $this->successResponse('User book actualizado correctamente.');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al editar user book: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene todos los tags del usuario
     */
    public function getUserBookTags(int $userId): array
    {
        try {
            $tags = $this->bookRepository->getUserBookTags($userId);
            return $this->successResponse('Tags obtenidos correctamente', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tags: ' . $e->getMessage());
        }
    }

    /**
     * Crea un nuevo tag para el usuario
     */
    public function createUserBookTag(int $userId, string $name, string $color = '#1976d2'): array
    {
        try {
            $tagId = $this->bookRepository->addUserBookTag($userId, $name, $color);
            $newTag = ['id' => $tagId, 'name' => $name, 'color' => $color];
            return $this->successResponse('Tag creado correctamente', $newTag);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear tag: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene los tags de un libro específico
     */
    public function getBookTags(int $userId, string $isbn): array
    {
        try {
            $tags = $this->bookRepository->getBookTags($userId, $isbn);
            return $this->successResponse('Tags del libro obtenidos correctamente', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tags del libro: ' . $e->getMessage());
        }
    }

    /**
     * Handle HTTP request for book endpoints
     */
    public function handleRequest(string $method, string $path): void
    {
        try {
            $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
            $action = $inputData['action'] ?? $_REQUEST['action'] ?? null;
            
            // Handle authentication for actions that require it
            $authResult = null;
            $authRequiredActions = ['add_book', 'delete_book', 'update_book_rating', 'update_book_user_statuses', 'get_library', 'edit_user_book', 'get_user_book_tags', 'create_user_book_tag', 'get_book_tags'];
            
            if (in_array($action, $authRequiredActions)) {
                $authResult = $this->authMiddleware->requireAuth();
                if ($authResult['status'] === 'error') {
                    http_response_code(401);
                    header('Content-Type: application/json');
                    echo json_encode($authResult);
                    exit();
                }
                
                // Check CSRF for modifying actions
                $csrfRequiredActions = ['add_book', 'delete_book', 'update_book_rating', 'update_book_user_statuses', 'edit_user_book', 'create_user_book_tag'];
                if (in_array($action, $csrfRequiredActions)) {
                    $csrfResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($csrfResult['status'] === 'error') {
                        http_response_code(403);
                        header('Content-Type: application/json');
                        echo json_encode($csrfResult);
                        exit();
                    }
                    $authResult = $csrfResult;
                }
            }
            
            $response = match ($action) {
                'add_book' => $this->addBook($inputData['book'] ?? [], $authResult['user']['id']),
                'delete_book' => $this->deleteBook($inputData['isbn'] ?? '', $authResult['user']['id']),
                'update_book_rating' => $this->updateBookRating($inputData['isbn'] ?? '', $inputData['rating'] ?? null, $authResult['user']['id']),
                'update_book_user_statuses' => $this->updateBookUserStatuses($inputData['isbn'] ?? '', $inputData['statuses'] ?? [], $authResult['user']['id']),
                'get_book_allowed_statuses' => $this->getBookAllowedStatuses(),
                'get_books' => $this->getAllBooks(),
                'get_library' => $this->getBooks($authResult['user']['id']),
                'edit_user_book' => $this->editUserBook($inputData['isbn'] ?? '', $authResult['user']['id'], $inputData['data'] ?? [], $inputData['tags'] ?? [], $inputData['notes'] ?? []),
                'get_user_book_tags' => $this->getUserBookTags($authResult['user']['id']),
                'create_user_book_tag' => $this->createUserBookTag($authResult['user']['id'], $inputData['name'] ?? '', $inputData['color'] ?? '#1976d2'),
                'get_book_tags' => $this->getBookTags($authResult['user']['id'], $inputData['isbn'] ?? ''),
                default => $this->errorResponse('Invalid book action: ' . $action)
            };
            
            $statusCode = $response['status'] === 'success' ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit(); // Asegurar que la respuesta termine aquí
            
        } catch (\Throwable $e) {
            // Log the error for debugging
            error_log('BookController Error: ' . $e->getMessage());
            error_log('BookController Trace: ' . $e->getTraceAsString());
            
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], JSON_PRETTY_PRINT);
            exit(); // Asegurar que la respuesta termine aquí
        }
    }
}
