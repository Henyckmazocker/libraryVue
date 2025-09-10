<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Movie;

interface MovieRepositoryInterface
{
        /**
     * Actualiza el rating de una película por imdbID o isbn
     * @param string $id Puede ser imdbID o isbn
     * @param float $rating
     * @return void
     */
    public function updateMovieRating(string $id, float $rating): void;
    
    /**
     * Obtiene todas las películas con filtros avanzados (título, estado)
     * @param array $filters ['title' => string|null, 'status' => string|null]
     * @return array
     */
    public function findAllWithFilters(array $filters = []): array;

    public function fetchAllowedStatuses(): array;

    /**
     * @param array $filters Optional filters (e.g., ['userStatus' => 'watched'])
     * @return array
     */
    public function findAll(array $filters = []): array;

    public function save(Movie $movie): void;

    public function deleteByIsbn(string $isbn): bool;

    public function deleteById(int $id): bool;

    public function deleteByName(string $title): bool;

    /**
     * @param string $isbn
     * @return array|null
     */
    public function findById(string $isbn): ?array;

    /**
     * Actualiza los estados de usuario de una película por imdbID
     * @param string $imdbID
     * @param array $statuses
     * @return void
     */
    public function updateUserStatuses(string $imdbID, array $statuses): void;

    // User-related methods
    public function addMovieToUser(int $userId, string $movieId, array $statuses = []): void;
    
    public function removeMovieFromUser(int $userId, string $movieId): bool;

    /**
     * Obtiene los tags asignados a una película específica de un usuario.
     * Devuelve un array de tags (id, name, color).
     */
    public function getMovieTags(int $userId, string $movieIsbn): array;

    /**
     * Obtiene todos los tags creados por el usuario para películas.
     * Devuelve un array de tags (id, name, color).
     */
    public function getUserMovieTags(int $userId): array;

    /**
     * Obtiene las notas de una película por página para un usuario.
     * Devuelve un array de notas (id, page_number, note_text, note_type, is_private, created_at).
     */
    public function getMovieNotesByPage(int $userId, string $movieIsbn): array;
    
    public function findMoviesByUser(int $userId, array $filters = []): array;
    
    public function updateUserMovieStatuses(int $userId, string $movieId, array $statuses, bool $manageTransaction = true): void;
    
    public function updateUserMovieRating(int $userId, string $movieId, ?float $rating): void;
    
    public function getUserMovieStatuses(int $userId, string $movieId): array;

    public function editUserMovie(int $userId, string $movieIsbn, ?float $personalRating = null, ?string $personalNotes = null, ?string $consumedAt = null): void;

    public function addUserMovieNote(int $userId, string $movieIsbn, string $noteText, string $noteType = 'note', bool $isPrivate = true): int;

    public function addUserMovieTag(int $userId, string $name, string $color = '#007bff'): int;

    public function assignUserMovieTag(int $userId, string $movieIsbn, int $tagId): void;

    public function removeAllUserMovieTags(int $userId, string $movieIsbn): void;
}
