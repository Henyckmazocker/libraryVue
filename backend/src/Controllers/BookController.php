<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\UseCases\Books\AddBookUseCase;
use App\Domain\UseCases\Books\DeleteBookUseCase;
use App\Domain\UseCases\Books\UpdateBookRatingUseCase;
use App\Domain\UseCases\Books\UpdateBookUserStatusesUseCase;
use App\Domain\UseCases\Books\GetBooksUseCase;
use App\Domain\UseCases\Books\GetAllBooksUseCase;
use App\Domain\UseCases\Books\GetBookAllowedStatusesUseCase;
use App\Domain\UseCases\Books\EditUserBookUseCase;
use App\Domain\UseCases\Books\GetTrendingBooksUseCase;
use App\Domain\UseCases\Books\UpdateReadingProgressUseCase;
use App\Domain\UseCases\Books\AddEditionNoteUseCase;
use App\Domain\UseCases\Books\UpdateEditionNoteUseCase;
use App\Domain\UseCases\Books\DeleteEditionNoteUseCase;
use App\Domain\UseCases\Books\GetEditionNotesUseCase;
use App\Domain\UseCases\Books\GetEditionNoteUseCase;
use App\Domain\DTO\Commands\AddBookCommand;
use App\Domain\DTO\Commands\DeleteBookCommand;
use App\Domain\DTO\Commands\UpdateBookRatingCommand;
use App\Domain\DTO\Commands\UpdateBookStatusesCommand;
use App\Domain\DTO\Commands\EditUserBookCommand;
use App\Domain\DTO\Commands\CreateReadingSessionCommand;
use App\Domain\DTO\Commands\CompleteReadingSessionCommand;
use App\Domain\DTO\Commands\UpdateReadingProgressCommand;
use App\Domain\DTO\Commands\ManageReadingSessionCommand;
use App\Domain\DTO\Commands\AddEditionNoteCommand;
use App\Domain\DTO\Commands\UpdateEditionNoteCommand;
use App\Domain\DTO\Commands\DeleteEditionNoteCommand;
use App\Domain\DTO\Queries\GetBooksByUserQuery;
use App\Domain\DTO\Queries\GetEditionNotesQuery;
use App\Domain\DTO\Queries\GetEditionNoteQuery;
use App\Domain\DTO\Queries\GetReadingSessionQuery;
use App\Domain\DTO\Queries\GetUserReadingStatsQuery;
use App\Domain\DTO\Queries\GetTrendingBooksQuery;
use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\Repository\Book\BookTagRepositoryInterface;
use App\Domain\Repository\Book\ReadingSessionRepositoryInterface;
use App\Domain\Repository\Book\ReadingProgressRepositoryInterface;
use App\Domain\Repository\Book\WorkRepositoryInterface;
use App\Domain\Services\WorkSearchService;
use App\Domain\Services\GoogleBooksService;
use Psr\Log\LoggerInterface;

class BookController extends BaseController implements Contracts\BookControllerInterface
{

    private AddBookUseCase $addBookUseCase;
    private DeleteBookUseCase $deleteBookUseCase;
    private UpdateBookRatingUseCase $updateBookRatingUseCase;
    private UpdateBookUserStatusesUseCase $updateBookUserStatusesUseCase;
    private GetBooksUseCase $getBooksUseCase;
    private GetAllBooksUseCase $getAllBooksUseCase;
    private GetBookAllowedStatusesUseCase $getBookAllowedStatusesUseCase;
    private BookRepositoryInterface $bookRepository;
    private BookTagRepositoryInterface $bookTagRepository;
    private ReadingSessionRepositoryInterface $readingSessionRepository;
    private ReadingProgressRepositoryInterface $readingProgressRepository;
    private EditUserBookUseCase $editUserBookUseCase;
    private GetTrendingBooksUseCase $getTrendingBooksUseCase;
    private UpdateReadingProgressUseCase $updateReadingProgressUseCase;
    private WorkSearchService $workSearchService;
    private WorkRepositoryInterface $workRepository;
    private GoogleBooksService $googleBooksService;
    private AddEditionNoteUseCase $addEditionNoteUseCase;
    private UpdateEditionNoteUseCase $updateEditionNoteUseCase;
    private DeleteEditionNoteUseCase $deleteEditionNoteUseCase;
    private GetEditionNotesUseCase $getEditionNotesUseCase;
    private GetEditionNoteUseCase $getEditionNoteUseCase;
    private LoggerInterface $logger;

    public function __construct(
        AddBookUseCase $addBookUseCase,
        DeleteBookUseCase $deleteBookUseCase,
        UpdateBookRatingUseCase $updateBookRatingUseCase,
        UpdateBookUserStatusesUseCase $updateBookUserStatusesUseCase,
        GetBooksUseCase $getBooksUseCase,
        GetAllBooksUseCase $getAllBooksUseCase,
        GetBookAllowedStatusesUseCase $getBookAllowedStatusesUseCase,
        BookRepositoryInterface $bookRepository,
        BookTagRepositoryInterface $bookTagRepository,
        ReadingSessionRepositoryInterface $readingSessionRepository,
        ReadingProgressRepositoryInterface $readingProgressRepository,
        EditUserBookUseCase $editUserBookUseCase,
        GetTrendingBooksUseCase $getTrendingBooksUseCase,
        UpdateReadingProgressUseCase $updateReadingProgressUseCase,
        WorkSearchService $workSearchService,
        WorkRepositoryInterface $workRepository,
        GoogleBooksService $googleBooksService,
        AddEditionNoteUseCase $addEditionNoteUseCase,
        UpdateEditionNoteUseCase $updateEditionNoteUseCase,
        DeleteEditionNoteUseCase $deleteEditionNoteUseCase,
        GetEditionNotesUseCase $getEditionNotesUseCase,
        GetEditionNoteUseCase $getEditionNoteUseCase,
        LoggerInterface $logger
    ) {
        $this->addBookUseCase = $addBookUseCase;
        $this->deleteBookUseCase = $deleteBookUseCase;
        $this->updateBookRatingUseCase = $updateBookRatingUseCase;
        $this->updateBookUserStatusesUseCase = $updateBookUserStatusesUseCase;
        $this->getBooksUseCase = $getBooksUseCase;
        $this->getAllBooksUseCase = $getAllBooksUseCase;
        $this->getBookAllowedStatusesUseCase = $getBookAllowedStatusesUseCase;
        $this->bookRepository = $bookRepository;
        $this->bookTagRepository = $bookTagRepository;
        $this->readingSessionRepository = $readingSessionRepository;
        $this->readingProgressRepository = $readingProgressRepository;
        $this->editUserBookUseCase = $editUserBookUseCase;
        $this->getTrendingBooksUseCase = $getTrendingBooksUseCase;
        $this->updateReadingProgressUseCase = $updateReadingProgressUseCase;
        $this->workSearchService = $workSearchService;
        $this->workRepository = $workRepository;
        $this->googleBooksService = $googleBooksService;
        $this->addEditionNoteUseCase = $addEditionNoteUseCase;
        $this->updateEditionNoteUseCase = $updateEditionNoteUseCase;
        $this->deleteEditionNoteUseCase = $deleteEditionNoteUseCase;
        $this->getEditionNotesUseCase = $getEditionNotesUseCase;
        $this->getEditionNoteUseCase = $getEditionNoteUseCase;
        $this->logger = $logger;
    }

    /**
     * Add a new book to user's library
     * 
     * @param AddBookCommand $command Command containing book data and user ID
     * @return array Success response with book data
     */
    public function addBook(AddBookCommand $command): array
    {
        $addedBook = $this->addBookUseCase->execute($command);
        $title = $addedBook['title'] ?? 'Unknown';
        return $this->successResponse('Book added: ' . $title, $addedBook, 201);
    }

    /**
     * Delete a book from user's library
     * 
     * @param DeleteBookCommand $command Command containing user ID and ISBN
     * @return array Success response
     */
    public function deleteBook(DeleteBookCommand $command): array
    {
        $this->deleteBookUseCase->execute($command);
        return $this->successResponse('Book removed from your library: ' . $command->isbn);
    }

    /**
     * Update book rating
     * 
     * @param UpdateBookRatingCommand $command Command containing user ID, ISBN, and rating
     * @return array Success response
     */
    public function updateBookRating(UpdateBookRatingCommand $command): array
    {
        $this->updateBookRatingUseCase->execute($command);
        return $this->successResponse('Rating updated for ISBN ' . $command->isbn);
    }

    /**
     * Update book user statuses
     * 
     * @param UpdateBookStatusesCommand $command Command containing user ID, ISBN, and statuses
     * @return array Success response
     */
    public function updateBookUserStatuses(UpdateBookStatusesCommand $command): array
    {
        $this->updateBookUserStatusesUseCase->execute($command);
        return $this->successResponse('User statuses updated for ISBN ' . $command->isbn);
    }

    public function getBookAllowedStatuses(): array
    {
        $query = \App\Domain\DTO\Queries\GetAllowedStatusesQuery::forBooks();
        $statuses = $this->getBookAllowedStatusesUseCase->execute($query);
        return $this->successResponse('Allowed book statuses retrieved.', $statuses);
    }

    /**
     * Get user's books
     * 
     * @param GetBooksByUserQuery $query Query containing user ID
     * @return array Success response with books data
     */
    public function getBooks(GetBooksByUserQuery $query): array
    {
        $books = $this->getBooksUseCase->execute($query);
        return $this->successResponse('Library data retrieved.', $books);
    }

    public function getAllBooks(): array
    {
        $query = \App\Domain\DTO\Queries\GetAllBooksQuery::create();
        $books = $this->getAllBooksUseCase->execute($query);
        return $this->successResponse('All books retrieved.', $books);
    }

    /**
     * Edit all aspects of a user_book: main data, tags, and notes
     * 
     * @param EditUserBookCommand $command Command containing all edit data
     * @return array Success response
     */
    public function editUserBook(EditUserBookCommand $command): array
    {
        $this->editUserBookUseCase->execute($command);
        return $this->successResponse('User book actualizado correctamente.');
    }

    /**
     * Obtiene todos los tags del usuario
     */
    public function getUserBookTags(int $userId): array
    {
        try {
            $tags = $this->bookTagRepository->getByUser($userId);
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
            $tagId = $this->bookTagRepository->create($userId, $name, $color);
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
            $tags = $this->bookTagRepository->getByBook($userId, $isbn);
            return $this->successResponse('Tags del libro obtenidos correctamente', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tags del libro: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza los tags de un libro
     * @param int $userId User ID
     * @param string $isbn Book ISBN
     * @param array $tagIds Array of tag IDs to assign
     * @return array Response
     */
    public function updateBookTags(int $userId, string $isbn, array $tagIds): array
    {
        try {
            // Remove all current tags
            $this->bookTagRepository->removeAll($userId, $isbn);
            
            // Assign new tags
            foreach ($tagIds as $tagId) {
                $this->bookTagRepository->assign($userId, $isbn, (int)$tagId);
            }
            
            return $this->successResponse('Tags del libro actualizados correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar tags del libro: ' . $e->getMessage());
        }
    }

    // ===================================
    // MÉTODOS DE SESIONES DE LECTURA
    // ===================================

    public function createReadingSession(CreateReadingSessionCommand $command): array
    {
        try {
            $sessionId = $this->readingSessionRepository->create($command->userId, $command->isbn, null, $command->startPage);
            
            // Obtener la sesión completa recién creada
            $session = $this->readingSessionRepository->getActive($command->userId, $command->isbn);
            
            return $this->successResponse('Sesión de lectura creada exitosamente', $session, 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear sesión de lectura: ' . $e->getMessage());
        }
    }

    public function getActiveReadingSession(GetReadingSessionQuery $query): array
    {
        try {
            $session = $this->readingSessionRepository->getActive($query->userId, $query->isbn);
            if ($session === null) {
                return $this->successResponse('No hay sesión activa para este libro', null);
            }
            return $this->successResponse('Sesión activa obtenida', $session);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener sesión activa: ' . $e->getMessage());
        }
    }

    public function completeReadingSession(CompleteReadingSessionCommand $command): array
    {
        try {
            $this->readingSessionRepository->complete($command->sessionId, $command->endPage);
            return $this->successResponse('Sesión completada exitosamente', [
                'sessionId' => $command->sessionId,
                'endPage' => $command->endPage,
                'reason' => $command->reason
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al completar sesión: ' . $e->getMessage());
        }
    }

    public function updateReadingProgressWithSession(UpdateReadingProgressCommand $command): array
    {
        try {
            $result = $this->updateReadingProgressUseCase->execute($command);
            return $this->successResponse('Progreso actualizado correctamente', $result);
        } catch (\Exception $e) {
            $this->logger->error('Error updating reading progress', [
                'error' => $e->getMessage(),
                'userId' => $command->userId,
                'isbn' => $command->isbn
            ]);
            return $this->errorResponse('Error al actualizar progreso: ' . $e->getMessage());
        }
    }

    public function getReadingSessionHistory(GetReadingSessionQuery $query): array
    {
        try {
            $history = $this->readingSessionRepository->getHistory($query->userId, $query->isbn);
            return $this->successResponse('Historial de sesiones obtenido', $history);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener historial: ' . $e->getMessage());
        }
    }

    public function getProgressHistory(int $userId, string $isbn): array
    {
        try {
            $history = $this->readingProgressRepository->getHistory($userId, $isbn);
            return $this->successResponse('Historial de progreso obtenido correctamente', $history);
        } catch (\Exception $e) {
            $this->logger->error('Error getting progress history', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            return $this->errorResponse('Error al obtener historial de progreso: ' . $e->getMessage());
        }
    }

    public function getSessionProgress(ManageReadingSessionCommand $command): array
    {
        try {
            $progress = $this->readingProgressRepository->getProgressForSession($command->sessionId);
            return $this->successResponse('Progreso de sesión obtenido', $progress);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener progreso: ' . $e->getMessage());
        }
    }

    public function getUserActiveReadingSessions(GetUserReadingStatsQuery $query): array
    {
        try {
            $sessions = $this->readingSessionRepository->getActiveSessions($query->userId);
            return $this->successResponse('Sesiones activas del usuario obtenidas', $sessions);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener sesiones activas: ' . $e->getMessage());
        }
    }

    public function pauseReadingSession(ManageReadingSessionCommand $command): array
    {
        try {
            $this->readingSessionRepository->pause($command->sessionId);
            return $this->successResponse('Sesión pausada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al pausar sesión: ' . $e->getMessage());
        }
    }

    public function resumeReadingSession(ManageReadingSessionCommand $command): array
    {
        try {
            $this->readingSessionRepository->resume($command->sessionId);
            return $this->successResponse('Sesión reanudada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al reanudar sesión: ' . $e->getMessage());
        }
    }

    public function deleteReadingSession(ManageReadingSessionCommand $command): array
    {
        try {
            $this->readingSessionRepository->delete($command->sessionId);
            return $this->successResponse('Sesión eliminada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar sesión: ' . $e->getMessage());
        }
    }

    public function getBookReadingSummary(GetReadingSessionQuery $query): array
    {
        try {
            // Nota: Este método necesita bookId, por ahora usamos History como alternativa
            $history = $this->readingProgressRepository->getHistory($query->userId, $query->isbn);
            return $this->successResponse('Resumen de lectura obtenido', $history);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener resumen de lectura: ' . $e->getMessage());
        }
    }

    public function getDetailedProgressHistory(GetReadingSessionQuery $query): array
    {
        try {
            $history = $this->readingProgressRepository->getDetailedHistory($query->userId, $query->isbn);
            return $this->successResponse('Historial detallado obtenido', $history);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener historial detallado: ' . $e->getMessage());
        }
    }

    public function getUserReadingStats(GetUserReadingStatsQuery $query): array
    {
        try {
            $stats = $this->readingProgressRepository->getUserStats($query->userId);
            return $this->successResponse('Estadísticas de lectura obtenidas', $stats);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas: ' . $e->getMessage());
        }
    }
    public function getCurrentReadingSessions(GetUserReadingStatsQuery $query): array
    {
        try {
            $sessions = $this->readingProgressRepository->getCurrentReadingSessions($query->userId);
            return $this->successResponse('Sesiones actuales obtenidas', $sessions);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener sesiones actuales: ' . $e->getMessage());
        }
    }

    /**
     * Get trending books based on user activity
     * 
     * @param GetTrendingBooksQuery $query Query containing limit and daysWindow
     * @return array Success response with trending books data
     */
    public function getTrendingBooks(GetTrendingBooksQuery $query): array
    {
        // Get authenticated user ID from session
        $userId = $_SESSION['user_data']['id'] ?? null;
        
        // Create query with userId
        $queryWithUser = GetTrendingBooksQuery::create(
            $query->limit,
            $query->daysWindow,
            $userId
        );
        
        $trendingBooks = $this->getTrendingBooksUseCase->execute($queryWithUser);
        return $this->successResponse('Trending books retrieved.', $trendingBooks);
    }

    /**
     * Search for works by title
     * Uses OpenLibrary as primary source with optional Google Books enrichment
     * 
     * @param array $data Request data containing 'q' (query), 'limit', and 'enrich'
     * @return array Success response with works data
     */
    public function searchWorks(array $data): array
    {
        try {
            $query = $data['q'] ?? '';
            $limit = isset($data['limit']) ? (int)$data['limit'] : 20;
            $enrichWithGoogle = isset($data['enrich']) ? 
                filter_var($data['enrich'], FILTER_VALIDATE_BOOLEAN) : false;

            if (empty($query)) {
                return $this->errorResponse('Search query is required', 400);
            }

            $limit = min($limit, 50);

            $this->logger->info("BookController: Searching works", [
                'query' => $query,
                'limit' => $limit,
                'enrich' => $enrichWithGoogle
            ]);

            $works = $this->workSearchService->searchWorks($query, $limit, $enrichWithGoogle);

            return $this->successResponse('Works search completed', [
                'works' => $works,
                'total' => count($works),
                'query' => $query,
                'enriched' => $enrichWithGoogle
            ]);

        } catch (\Exception $e) {
            $this->logger->error("BookController: Search works failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->externalServiceError('OpenLibrary');
        }
    }

    /**
     * Get detailed information about a specific work
     * 
     * @param array $data Request data containing 'workKey' and 'enrich'
     * @return array Success response with work details
     */
    public function getWork(array $data): array
    {
        try {
            $workKey = $data['workKey'] ?? '';
            $enrichWithGoogle = isset($data['enrich']) ? 
                filter_var($data['enrich'], FILTER_VALIDATE_BOOLEAN) : true;

            if (empty($workKey)) {
                return $this->errorResponse('Work key is required', 400);
            }

            $this->logger->info("BookController: Getting work details", [
                'work_key' => $workKey,
                'enrich' => $enrichWithGoogle
            ]);

            $work = $this->workSearchService->getWorkDetails($workKey, $enrichWithGoogle);

            if (!$work) {
                return $this->errorResponse('Work not found', 404);
            }

            return $this->successResponse('Work details retrieved', $work);

        } catch (\Exception $e) {
            $this->logger->error("BookController: Get work failed", [
                'work_key' => $data['workKey'] ?? '',
                'error' => $e->getMessage()
            ]);

            return $this->externalServiceError('OpenLibrary');
        }
    }

    /**
     * Get all editions for a specific work with filters
     * 
     * @param array $data Request data with filters
     * @return array Success response with editions
     */
    public function getWorkEditions(array $data): array
    {
        try {
            $workKey = $data['workKey'] ?? '';

            if (empty($workKey)) {
                return $this->errorResponse('Work key is required', 400);
            }

            $page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
            $limit = isset($data['limit']) ? min(100, max(1, (int)$data['limit'])) : 20;

            $filters = [];
            if (!empty($data['format'])) {
                $filters['format'] = $data['format'];
            }
            if (!empty($data['language'])) {
                $filters['language'] = $data['language'];
            }
            if (!empty($data['year_from'])) {
                $filters['year_from'] = (int)$data['year_from'];
            }
            if (!empty($data['year_to'])) {
                $filters['year_to'] = (int)$data['year_to'];
            }
            if (!empty($data['has_isbn'])) {
                $filters['has_isbn'] = filter_var($data['has_isbn'], FILTER_VALIDATE_BOOLEAN);
            }

            $this->logger->info("BookController: Getting work editions", [
                'work_key' => $workKey,
                'page' => $page,
                'limit' => $limit,
                'filters' => $filters
            ]);

            $result = $this->workSearchService->getWorkEditions($workKey, $filters, $page, $limit);

            return $this->successResponse('Work editions retrieved', $result);

        } catch (\Exception $e) {
            $this->logger->error("BookController: Get work editions failed", [
                'work_key' => $data['workKey'] ?? '',
                'error' => $e->getMessage()
            ]);

            return $this->externalServiceError('OpenLibrary');
        }
    }

    /**
     * Validate ISBN and get associated work
     * 
     * @param array $data Request data containing 'isbn'
     * @return array Success response with validation result
     */
    public function validateISBN(array $data): array
    {
        try {
            $isbn = $data['isbn'] ?? '';

            if (empty($isbn)) {
                return $this->errorResponse('ISBN is required', 400);
            }

            $this->logger->info("BookController: Validating ISBN", ['isbn' => $isbn]);

            $result = $this->workSearchService->validateAndGetWorkFromISBN($isbn);

            if (!$result) {
                return $this->errorResponse('ISBN not found in any database', 404);
            }

            return $this->successResponse('ISBN validated', $result);

        } catch (\Exception $e) {
            $this->logger->error("BookController: Validate ISBN failed", [
                'isbn' => $data['isbn'] ?? '',
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to validate ISBN: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search book details by ISBN using Google Books API
     * This proxies requests to avoid quota issues from direct client calls
     * 
     * @param array $data Request data containing 'isbn'
     * @return array Success response with book details or error
     */
    public function searchGoogleBooksByISBN(array $data): array
    {
        try {
            $isbn = $data['isbn'] ?? '';

            if (empty($isbn)) {
                return $this->errorResponse('ISBN is required', 400);
            }

            $this->logger->info("BookController: Searching Google Books by ISBN", ['isbn' => $isbn]);

            $bookData = $this->googleBooksService->searchByISBN($isbn);

            if (!$bookData) {
                return $this->errorResponse('Book not found in Google Books', 404);
            }

            return $this->successResponse('Book details retrieved from Google Books', $bookData);

        } catch (\Exception $e) {
            $this->logger->error("BookController: Google Books search failed", [
                'isbn' => $data['isbn'] ?? '',
                'error' => $e->getMessage()
            ]);

            return $this->externalServiceError('Google Books');
        }
    }

    /**
     * Proxy for OpenLibrary book data by ISBN.
     * Returns edition data (work_key extraction) + full book metadata.
     *
     * @param array $data Request data containing 'isbn'
     * @return array
     */
    public function getOpenLibraryBookByISBN(array $data): array
    {
        $isbn = trim($data['isbn'] ?? '');
        if (empty($isbn)) {
            return $this->errorResponse('ISBN is required', 400);
        }

        try {
            $result = $this->workSearchService->getOpenLibraryService()->getBookByISBN($isbn);

            if ($result === null) {
                return $this->errorResponse('Book not found in OpenLibrary', 404);
            }

            return $this->successResponse('OpenLibrary book data retrieved', $result);

        } catch (\Exception $e) {
            $this->logger->error('BookController: OpenLibrary ISBN lookup failed', [
                'isbn'  => $isbn,
                'error' => $e->getMessage(),
            ]);
            return $this->externalServiceError('OpenLibrary');
        }
    }

    /**
     * Add a note to a book edition
     * 
     * @param array $data Request data
     * @return array Success or error response
     */
    public function addEditionNote(array $data, int $userId): array
    {
        try {
            $command = AddEditionNoteCommand::fromArray($data, $userId);
            
            $result = $this->addEditionNoteUseCase->execute($command);
            
            return $this->successResponse('Note added successfully', $result);
            
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add edition note', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to add note: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an edition note
     * 
     * @param array $data Request data
     * @return array Success or error response
     */
    public function updateEditionNote(array $data, int $userId): array
    {
        try {
            $command = UpdateEditionNoteCommand::fromArray($data, $userId);
            
            $result = $this->updateEditionNoteUseCase->execute($command);
            
            return $this->successResponse('Note updated successfully', $result);
            
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update edition note', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to update note: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete an edition note
     * 
     * @param array $data Request data
     * @return array Success or error response
     */
    public function deleteEditionNote(array $data, int $userId): array
    {
        try {
            $command = DeleteEditionNoteCommand::fromArray($data, $userId);
            
            $result = $this->deleteEditionNoteUseCase->execute($command);
            
            return $this->successResponse($result['message'], $result);
            
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete edition note', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to delete note: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all notes for a user edition
     * 
     * @param array $data Request data
     * @return array Success or error response
     */
    public function getEditionNotes(array $data, int $userId): array
    {
        try {
            $query = GetEditionNotesQuery::fromArray($data, $userId);
            
            $result = $this->getEditionNotesUseCase->execute($query);
            
            return $this->successResponse('Notes retrieved successfully', $result);
            
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get edition notes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to get notes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a single edition note by ID
     * 
     * @param array $data Request data
     * @return array Success or error response
     */
    public function getEditionNote(array $data, int $userId): array
    {
        try {
            $query = GetEditionNoteQuery::fromArray($data, $userId);
            
            $result = $this->getEditionNoteUseCase->execute($query);
            
            return $this->successResponse('Note retrieved successfully', $result);
            
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get edition note', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to get note: ' . $e->getMessage(), 500);
        }
    }
}
