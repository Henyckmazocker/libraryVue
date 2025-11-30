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
            $logger = \App\Infrastructure\Logging\LoggingService::getInstance()->getLogger('books');
            $logger->info('Editando user_book', [
                'user_id' => $userId,
                'isbn' => $isbn,
                'data' => $data,
                'tags_count' => count($tags),
                'notes_count' => count($notes)
            ]);
            
            $this->editUserBookUseCase->execute($userId, $isbn, $data, $tags, $notes);
            return $this->successResponse('User book actualizado correctamente.');
        } catch (\Exception $e) {
            $logger = \App\Infrastructure\Logging\LoggingService::getInstance()->getLogger('books');
            $logger->error('Error al editar user_book', [
                'user_id' => $userId,
                'isbn' => $isbn,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
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

    // ===================================
    // MÉTODOS DE SESIONES DE LECTURA
    // ===================================

    public function createReadingSession(int $userId, string $isbn, ?int $startPage = null): array
    {
        try {
            $sessionId = $this->bookRepository->createReadingSession($userId, $isbn, null, true, $startPage);
            
            // Obtener la sesión completa recién creada
            $session = $this->bookRepository->getActiveReadingSession($userId, $isbn);
            
            return $this->successResponse('Sesión de lectura creada exitosamente', $session, 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear sesión de lectura: ' . $e->getMessage());
        }
    }

    public function getActiveReadingSession(int|string $userId, string $isbn): array
    {
        $userId = (int) $userId;
        try {
            $session = $this->bookRepository->getActiveReadingSession($userId, $isbn);
            if ($session === null) {
                return $this->successResponse('No hay sesión activa para este libro', null);
            }
            return $this->successResponse('Sesión activa obtenida', $session);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener sesión activa: ' . $e->getMessage());
        }
    }

    public function completeReadingSession(int $sessionId, int $endPage, string $reason = 'completed'): array
    {
        try {
            $success = $this->bookRepository->completeReadingSession($sessionId, $endPage, $reason);
            if (!$success) {
                return $this->errorResponse('No se encontró sesión activa para completar');
            }
            return $this->successResponse('Sesión completada exitosamente', [
                'sessionId' => $sessionId,
                'endPage' => $endPage,
                'reason' => $reason
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al completar sesión: ' . $e->getMessage());
        }
    }

    public function updateReadingProgressWithSession(int $userId, string $isbn, int $currentPage, ?int $sessionId = null): array
    {
        try {
            $success = $this->bookRepository->updateReadingProgressWithSession($userId, $isbn, $currentPage, $sessionId);
            if (!$success) {
                return $this->errorResponse('No se pudo actualizar el progreso');
            }
            return $this->successResponse('Progreso actualizado con sesión', [
                'userId' => $userId,
                'bookId' => $bookId,
                'currentPage' => $currentPage,
                'sessionId' => $sessionId
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar progreso: ' . $e->getMessage());
        }
    }

    public function getReadingSessionHistory(int $userId, string $isbn): array
    {
        try {
            $history = $this->bookRepository->getReadingSessionHistory($userId, $isbn);
            return $this->successResponse('Historial de sesiones obtenido', $history);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener historial de sesiones: ' . $e->getMessage());
        }
    }

    public function getSessionProgress(int $sessionId): array
    {
        try {
            $progress = $this->bookRepository->getSessionProgress($sessionId);
            return $this->successResponse('Progreso de sesión obtenido', $progress);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener progreso de sesión: ' . $e->getMessage());
        }
    }

    public function getUserActiveReadingSessions(int $userId): array
    {
        try {
            $sessions = $this->bookRepository->getUserActiveReadingSessions($userId);
            return $this->successResponse('Sesiones activas del usuario obtenidas', $sessions);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener sesiones activas: ' . $e->getMessage());
        }
    }

    public function pauseReadingSession(int $sessionId): array
    {
        try {
            $success = $this->bookRepository->pauseReadingSession($sessionId);
            if (!$success) {
                return $this->errorResponse('No se pudo pausar la sesión. Verifique que esté activa.');
            }
            return $this->successResponse('Sesión pausada exitosamente', ['sessionId' => $sessionId]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al pausar sesión: ' . $e->getMessage());
        }
    }

    public function resumeReadingSession(int $sessionId): array
    {
        try {
            $success = $this->bookRepository->resumeReadingSession($sessionId);
            if (!$success) {
                return $this->errorResponse('No se pudo reanudar la sesión. Verifique que esté pausada.');
            }
            return $this->successResponse('Sesión reanudada exitosamente', ['sessionId' => $sessionId]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al reanudar sesión: ' . $e->getMessage());
        }
    }

    public function deleteReadingSession(int $sessionId): array
    {
        try {
            $success = $this->bookRepository->deleteReadingSession($sessionId);
            if (!$success) {
                return $this->errorResponse('No se encontró la sesión para eliminar');
            }
            return $this->successResponse('Sesión eliminada exitosamente', ['sessionId' => $sessionId]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar sesión: ' . $e->getMessage());
        }
    }

    public function getBookReadingSummary(int $userId, string $isbn): array
    {
        try {
            $summary = $this->bookRepository->getBookReadingSummary($userId, $isbn);
            return $this->successResponse('Resumen de lectura obtenido', $summary);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener resumen de lectura: ' . $e->getMessage());
        }
    }

    public function getDetailedProgressHistory(int $userId, string $isbn): array
    {
        try {
            $history = $this->bookRepository->getDetailedProgressHistory($userId, $isbn);
            return $this->successResponse('Historial detallado obtenido', $history);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener historial detallado: ' . $e->getMessage());
        }
    }

    public function getUserReadingStats(int $userId): array
    {
        try {
            $stats = $this->bookRepository->getUserReadingStats($userId);
            return $this->successResponse('Estadísticas de usuario obtenidas', $stats);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas: ' . $e->getMessage());
        }
    }

    public function getCurrentReadingSessions(int $userId): array
    {
        try {
            $sessions = $this->bookRepository->getCurrentReadingSessions($userId);
            return $this->successResponse('Sesiones actuales obtenidas', $sessions);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener sesiones actuales: ' . $e->getMessage());
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
            $authRequiredActions = [
                'add_book', 'delete_book', 'update_book_rating', 'update_book_user_statuses', 
                'get_library', 'edit_user_book', 'get_user_book_tags', 'create_user_book_tag', 'get_book_tags',
                // Sesiones de lectura - todas requieren autenticación
                'create_reading_session', 'get_active_reading_session', 'complete_reading_session', 
                'update_reading_progress_with_session', 'get_reading_session_history', 'get_session_progress',
                'get_user_active_reading_sessions', 'pause_reading_session', 'resume_reading_session', 
                'delete_reading_session', 'get_book_reading_summary', 'get_detailed_progress_history',
                'get_user_reading_stats', 'get_current_reading_sessions'
            ];
            
            if (in_array($action, $authRequiredActions)) {
                $authResult = $this->authMiddleware->requireAuth();
                if ($authResult['status'] === 'error') {
                    http_response_code(401);
                    header('Content-Type: application/json');
                    echo json_encode($authResult);
                    exit();
                }
                
                // Asegurar que el user_id sea un int (viene como string desde la sesión)
                if (isset($authResult['user']['id'])) {
                    $authResult['user']['id'] = (int)$authResult['user']['id'];
                }
                
                // Check CSRF for modifying actions
                $csrfRequiredActions = [
                    'add_book', 'delete_book', 'update_book_rating', 'update_book_user_statuses', 
                    'edit_user_book', 'create_user_book_tag',
                    // Sesiones de lectura que modifican datos
                    'create_reading_session', 'complete_reading_session', 'update_reading_progress_with_session',
                    'pause_reading_session', 'resume_reading_session', 'delete_reading_session'
                ];
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
                // Acciones originales de libros
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
                
                // Nuevas acciones de sesiones de lectura
                'create_reading_session' => $this->createReadingSession($authResult['user']['id'], $inputData['isbn'] ?? '', $inputData['startPage'] ?? null),
                'get_active_reading_session' => $this->getActiveReadingSession($authResult['user']['id'], $inputData['isbn'] ?? ''),
                'complete_reading_session' => $this->completeReadingSession($inputData['sessionId'] ?? 0, $inputData['endPage'] ?? 0, $inputData['reason'] ?? 'completed'),
                'update_reading_progress_with_session' => $this->updateReadingProgressWithSession($authResult['user']['id'], $inputData['isbn'] ?? '', $inputData['currentPage'] ?? 0, $inputData['sessionId'] ?? null),
                'get_reading_session_history' => $this->getReadingSessionHistory($authResult['user']['id'], $inputData['isbn'] ?? ''),
                'get_session_progress' => $this->getSessionProgress($inputData['sessionId'] ?? 0),
                'get_user_active_reading_sessions' => $this->getUserActiveReadingSessions($authResult['user']['id']),
                'pause_reading_session' => $this->pauseReadingSession($inputData['sessionId'] ?? 0),
                'resume_reading_session' => $this->resumeReadingSession($inputData['sessionId'] ?? 0),
                'delete_reading_session' => $this->deleteReadingSession($inputData['sessionId'] ?? 0),
                'get_book_reading_summary' => $this->getBookReadingSummary($authResult['user']['id'], $inputData['isbn'] ?? ''),
                'get_detailed_progress_history' => $this->getDetailedProgressHistory($authResult['user']['id'], $inputData['isbn'] ?? ''),
                'get_user_reading_stats' => $this->getUserReadingStats($authResult['user']['id']),
                'get_current_reading_sessions' => $this->getCurrentReadingSessions($authResult['user']['id']),
                
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
