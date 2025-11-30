<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Book;

interface BookRepositoryInterface
{
    public function fetchAllowedStatuses(): array;

    /**
     * @param array $filters Optional filters (e.g., ['userStatus' => 'read'])
     * @return Book[]
     */
    public function findAll(array $filters = []): array;

    public function findById(string $isbn): ?Book;

    /**
     * Finds books by a specific user status.
     * @param string $statusName The user status to filter by.
     * @return Book[]
     */
    public function findByUserStatus(string $statusName): array;

    public function save(Book $book): void;

    public function deleteByIsbn(string $isbn): bool;

    // User-related methods
    public function addBookToUser(int $userId, string $isbn, array $statuses = []): void;
    
    public function removeBookFromUser(int $userId, string $isbn): bool;
    
    public function findBooksByUser(int $userId, array $filters = []): array;
    
    public function updateUserBookStatuses(int|string $userId, string $isbn, array $statuses): void;
    
    public function updateUserBookRating(int $userId, string $isbn, ?float $rating): void;
    
    public function getUserBookStatuses(int|string $userId, string $isbn): array;

    public function editUserBook(int $userId, string $isbn, ?int $currentPage = null, ?float $personalRating = null, ?string $personalNotes = null, ?string $consumedAt = null): void;

    public function addUserBookNote(int $userId, string $isbn, int $pageNumber, string $noteText, string $noteType = 'note', bool $isPrivate = true): int;

    public function addUserBookTag(int $userId, string $name, string $color = '#007bff'): int;

    public function assignUserBookTag(int $userId, string $isbn, int $tagId): void;

    public function removeAllUserBookTags(int $userId, string $isbn): void;

    /**
     * Obtiene los tags asignados a un libro específico de un usuario.
     * Devuelve un array de tags (id, name, color).
     */
    public function getBookTags(int $userId, string $isbn): array;

    /**
     * Obtiene todos los tags creados por el usuario.
     * Devuelve un array de tags (id, name, color).
     */
    public function getUserBookTags(int $userId): array;

    /**
     * Obtiene las notas de un libro por página para un usuario.
     * Devuelve un array de notas (id, page_number, note_text, note_type, is_private, created_at).
     */
    public function getBookNotesByPage(int $userId, string $isbn): array;

    /**
     * Obtiene la página actual de un libro para un usuario.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @return int Página actual (0 si no se ha empezado)
     */
    public function getCurrentPage(int $userId, string $isbn): int;

    /**
     * Obtiene el número total de páginas de un libro.
     * @param string $isbn ISBN del libro
     * @return int Número total de páginas (0 si no está definido)
     */
    public function getTotalPages(string $isbn): int;

    /**
     * Obtiene la página del último progreso registrado en el historial.
     * Si no hay historial, obtiene la página actual de user_books.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @return int Última página de progreso registrada
     */
    public function getLastProgressPage(int $userId, string $isbn): int;

    /**
     * Registra un nuevo progreso de lectura en el historial.
     * Solo registra si currentPage > previousPage.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @param int $currentPage Página actual
     * @param int $previousPage Página anterior
     */
    public function addReadingProgressHistory(int $userId, string $isbn, int $currentPage, int $previousPage): void;

    /**
     * Obtiene el historial de progreso de lectura de un libro para un usuario.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @return array Array de registros del historial [id, current_page, previous_page, logged_at]
     */
    public function getReadingProgressHistory(int $userId, string $isbn): array;

    /**
     * Obtiene estadísticas de páginas leídas por mes para un usuario.
     * @param int $userId ID del usuario
     * @param int $months Número de meses hacia atrás (por defecto 12)
     * @return array Array con datos mensuales [año-mes => páginas_leídas]
     */
    public function getMonthlyPagesReadStats(int $userId, int $months = 12): array;

    // ============================================================================
    // MÉTODOS PARA GESTIÓN DE SESIONES DE LECTURA
    // ============================================================================

    /**
     * Crea una nueva sesión de lectura para un libro.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @param int|null $sessionNumber Número de sesión (null para auto-calcular)
     * @param int|null $startPage Página inicial (null para usar current_page del usuario)
     * @return int ID de la sesión creada
     * @throws \RuntimeException Si ya existe una sesión activa
     */
    public function createReadingSession(int|string $userId, string $isbn, ?int $sessionNumber = null, ?int $startPage = null): int;

    /**
     * Obtiene la sesión de lectura activa de un usuario para un libro.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @return array|null Datos de la sesión activa o null si no existe
     */
    public function getActiveReadingSession(int|string $userId, string $isbn): ?array;

    /**
     * Completa una sesión de lectura marcándola como terminada.
     * @param int $sessionId ID de la sesión
     * @param int|null $finalPage Página final (null para usar página actual)
     * @throws \RuntimeException Si la sesión no existe o no está activa
     */
    public function completeReadingSession(int $sessionId, ?int $finalPage = null): void;

    /**
     * Actualiza el estado de una sesión de lectura.
     * @param int $sessionId ID de la sesión
     * @param string $status Nuevo estado ('active', 'completed', 'paused', 'abandoned')
     * @param int|null $finalPage Página final (opcional, para estados completed/abandoned)
     * @throws \RuntimeException Si el estado es inválido o la sesión no existe
     */
    public function updateSessionStatus(int $sessionId, string $status, ?int $finalPage = null): void;

    /**
     * Abandona una sesión de lectura con una razón opcional.
     * @param int $sessionId ID de la sesión
     * @param string|null $reason Razón del abandono
     * @throws \RuntimeException Si la sesión no existe o no está activa
     */
    public function abandonReadingSession(int $sessionId, ?string $reason = null): void;

    /**
     * Pausa una sesión de lectura activa.
     * @param int $sessionId ID de la sesión
     * @param string|null $reason Razón de la pausa
     * @throws \RuntimeException Si la sesión no existe o no está activa
     */
    public function pauseReadingSession(int $sessionId, ?string $reason = null): void;

    /**
     * Reanuda una sesión de lectura pausada.
     * @param int $sessionId ID de la sesión
     * @throws \RuntimeException Si la sesión no existe o no está pausada
     */
    public function resumeReadingSession(int $sessionId): void;

    /**
     * Obtiene el historial de sesiones de lectura de un usuario para un libro.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @return array Array de sesiones [id, session_number, started_at, completed_at, status, final_page, notes]
     */
    public function getReadingSessionHistory(int $userId, string $isbn): array;

    /**
     * Obtiene todas las sesiones de lectura de un usuario (todos los libros).
     * @param int $userId ID del usuario
     * @param string|null $status Filtrar por estado ('active', 'completed', 'abandoned', 'paused')
     * @return array Array de sesiones con información del libro
     */
    public function getUserReadingSessions(int $userId, ?string $status = null): array;

    /**
     * Obtiene el siguiente número de sesión para un libro y usuario.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @return int Siguiente número de sesión
     */
    public function getNextSessionNumber(int $userId, string $isbn): int;

    /**
     * Actualiza el progreso de lectura con soporte para sesiones y progreso negativo.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @param int $currentPage Página actual
     * @param string $progressType Tipo de progreso ('advance', 'backtrack', 'restart')
     * @param string|null $notes Notas opcionales sobre el progreso
     * @throws \RuntimeException Si ocurre un error en la actualización
     */
    public function updateReadingProgressWithSession(int $userId, string $isbn, int $currentPage, string $progressType = 'advance', ?string $notes = null): void;

    /**
     * Obtiene estadísticas completas de sesiones de lectura de un usuario.
     * @param int $userId ID del usuario
     * @return array Estadísticas [total_sessions, completed_sessions, active_sessions, etc.]
     */
    public function getReadingSessionStats(int $userId): array;

    /**
     * Verifica si un libro ha sido completado al menos una vez por el usuario.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @return bool True si ha sido completado al menos una vez
     */
    public function hasCompletedBook(int $userId, string $isbn): bool;

    /**
     * Obtiene el número de veces que un usuario ha completado un libro.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     * @return int Número de sesiones completadas
     */
    public function getBookCompletionCount(int $userId, string $isbn): int;

    /**
     * Actualiza automáticamente los estados de un libro basado en las sesiones.
     * @param int $userId ID del usuario
     * @param string $isbn ISBN del libro
     */
    public function updateBookStatusesBasedOnSessions(int $userId, string $isbn): void;

    /**
     * Elimina una sesión de lectura y su historial asociado.
     * @param int $sessionId ID de la sesión
     * @param bool $keepHistory Si mantener el historial de progreso
     * @throws \RuntimeException Si la sesión está activa o no existe
     */
    public function deleteReadingSession(int $sessionId, bool $keepHistory = true): void;
}