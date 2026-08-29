<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * Los estados permitidos de un libro, y nada más.
 *
 * Aquí vivía el CRUD del modelo de libros anterior a Work/Edition —`findById`,
 * `findAll`, `save`, `update`, `delete`, `updateRating`, `getTotalPages`—, todo
 * contra una tabla `books` que el esquema no crea desde la migración a
 * Work/Edition. La única de esas acciones alcanzable desde el router,
 * `get_books`, respondía 500 y no la llamaba nadie desde el frontend. El
 * catálogo de libros lo sirven hoy `book_works` / `book_editions` a través de
 * `WorkRepositoryInterface` y `EditionRepositoryInterface`.
 *
 * `getEntityStatusTableName()` sigue devolviendo `book_has_statuses`, que
 * tampoco existe: lo exige `StatusManagementTrait` como método abstracto, pero
 * solo lo usan `assignStatus` y `clearAllStatuses`, y a esta clase ya nadie se
 * los pide. El único camino vivo es `fetchAllowedStatuses()` →
 * `getAllowedStatusesFromDb()`, que lee `book_statuses` y esa sí existe.
 */
final class MySqlBookRepository implements BookRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE = 'book_statuses';
    private const STATUS_LINK_TABLE = 'book_has_statuses';
    private const STATUS_COLUMN = 'book_isbn';

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function fetchAllowedStatuses(): array
    {
        return $this->getAllowedStatusesFromDb();
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    protected function getDatabase(): PDO
    {
        return $this->db;
    }

    protected function getStatusTableName(): string
    {
        return self::STATUS_TABLE;
    }

    protected function getEntityStatusTableName(): string
    {
        return self::STATUS_LINK_TABLE;
    }

    protected function getEntityIdColumnName(): string
    {
        return self::STATUS_COLUMN;
    }
}
