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
use App\Domain\DTO\Commands\AddBookCommand;
use App\Domain\DTO\Commands\DeleteBookCommand;
use App\Domain\DTO\Commands\UpdateBookRatingCommand;
use App\Domain\DTO\Commands\UpdateBookStatusesCommand;
use App\Domain\DTO\Commands\EditUserBookCommand;
use App\Domain\DTO\Commands\CreateReadingSessionCommand;
use App\Domain\DTO\Commands\CompleteReadingSessionCommand;
use App\Domain\DTO\Commands\UpdateReadingProgressCommand;
use App\Domain\DTO\Commands\ManageReadingSessionCommand;
use App\Domain\DTO\Queries\GetBooksByUserQuery;
use App\Domain\DTO\Queries\GetReadingSessionQuery;
use App\Domain\DTO\Queries\GetUserReadingStatsQuery;
use App\Domain\DTO\Queries\GetTrendingBooksQuery;
use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\Repository\Book\BookTagRepositoryInterface;
use App\Domain\Repository\Book\ReadingSessionRepositoryInterface;
use App\Domain\Repository\Book\ReadingProgressRepositoryInterface;
use App\Infrastructure\Middleware\AuthMiddleware;

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
    private AuthMiddleware $authMiddleware;
    private EditUserBookUseCase $editUserBookUseCase;
    private GetTrendingBooksUseCase $getTrendingBooksUseCase;

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
        AuthMiddleware $authMiddleware,
        EditUserBookUseCase $editUserBookUseCase,
        GetTrendingBooksUseCase $getTrendingBooksUseCase
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
        $this->authMiddleware = $authMiddleware;
        $this->editUserBookUseCase = $editUserBookUseCase;
        $this->getTrendingBooksUseCase = $getTrendingBooksUseCase;
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
        return $this->successResponse('Book added: ' . $addedBook->getTitle(), $addedBook->toArray(), 201);
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
            $this->readingProgressRepository->updateWithSession($command->userId, $command->isbn, $command->currentPage, 'advance');
            return $this->successResponse('Progreso actualizado con sesión', [
                'userId' => $command->userId,
                'isbn' => $command->isbn,
                'currentPage' => $command->currentPage,
                'sessionId' => $command->sessionId
            ]);
        } catch (\Exception $e) {
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
}
