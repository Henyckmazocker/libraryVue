<?php
declare(strict_types=1);

namespace App\Domain\Repository\Book;

/**
 * Los estados que un libro puede tener. Un solo método, y es deliberado.
 *
 * Esta interfaz declaraba el CRUD del modelo de libros anterior a Work/Edition
 * —`findById`, `findAll`, `findByUserStatus`, `save`, `update`, `delete`,
 * `updateRating`, `getTotalPages`—, todo contra una tabla `books` que el
 * esquema dejó de crear al migrar. El catálogo lo sirven hoy
 * `WorkRepositoryInterface` y `EditionRepositoryInterface`; lo del usuario,
 * `UserBookRepositoryInterface`.
 *
 * Lo único que quedó vivo es el catálogo de estados, que lee `book_statuses` y
 * lo consume `GetBookAllowedStatusesUseCase` (acción `get_book_allowed_statuses`).
 */
interface BookRepositoryInterface
{
    /**
     * Los estados que admite un libro, leídos de `book_statuses`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllowedStatuses(): array;
}
