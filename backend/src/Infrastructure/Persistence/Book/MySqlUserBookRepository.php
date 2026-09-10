<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use InvalidArgumentException;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for User-Book relationships
 * Handles user-specific book operations and statuses
 */
final class MySqlUserBookRepository implements UserBookRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE = 'book_statuses';
    private const STATUS_LINK_TABLE = 'user_book_statuses';
    // `user_edition_id`, no `book_isbn`: es la columna que `user_book_statuses` tiene de
    // verdad desde el refactor Work/Edition, y la que declara el repositorio hermano
    // (`MySqlUserBookEditionRepository`). Hoy ningun helper de `StatusManagementTrait`
    // se invoca desde esta clase, asi que el valor viejo no rompia nada — pero mentia,
    // y habria roto al primero que llamase a `assignStatus()` o `fetchStatusNames()`.
    private const STATUS_COLUMN = 'user_edition_id';

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function hasBook(int $userId, string $isbn): bool
    {
        try {
            // Por el modelo Work/Edition, como ya hace `updateStatuses()` unas
            // lineas mas abajo. Consultaba `user_books`, que NO existe en el
            // esquema: la PDOException caia en el catch de abajo y devolvia
            // `false`, asi que `update_book_user_statuses` respondia
            // «Book not found in your library» con el libro delante.
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM user_book_editions ube
                INNER JOIN book_editions be ON be.edition_id = ube.edition_id
                WHERE ube.user_id = :userId
                  AND (be.isbn_13 = :isbn OR be.isbn_10 = :isbn2)
            ");
            $stmt->execute([':userId' => $userId, ':isbn' => $isbn, ':isbn2' => $isbn]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result && (int) $result['count'] > 0;

        } catch (PDOException $e) {
            $this->logError('Error checking if user has book', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return false;
        }
    }

    public function edit(int $userId, string $isbn, array $data): void
    {
        $this->db->beginTransaction();
        try {
            // First, find the edition_id from the ISBN
            $sql = "SELECT edition_id FROM book_editions WHERE isbn_13 = :isbn OR isbn_10 = :isbn2 LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':isbn' => $isbn, ':isbn2' => $isbn]);
            $edition = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$edition) {
                $this->db->rollBack();
                throw new RuntimeException("Edition not found for ISBN: {$isbn}");
            }
            
            $editionId = (int) $edition['edition_id'];
            
            $updates = [];
            $params = [':userId' => $userId, ':editionId' => $editionId];

            if (isset($data['current_page'])) {
                $updates[] = "current_page = :current_page";
                $params[':current_page'] = (int) $data['current_page'];
            }

            if (isset($data['personal_rating'])) {
                $updates[] = "edition_rating = :edition_rating";
                $params[':edition_rating'] = $data['personal_rating'] !== null ? (float) $data['personal_rating'] : null;
            }

            if (array_key_exists('ownership_format_id', $data)) {
                $updates[] = "ownership_format_id = :ownership_format_id";
                $params[':ownership_format_id'] = $data['ownership_format_id'] !== null ? (int) $data['ownership_format_id'] : null;
            }

            if (empty($updates)) {
                $this->db->rollBack();
                return;
            }

            $sql = "UPDATE user_book_editions SET " . implode(', ', $updates) . " WHERE user_id = :userId AND edition_id = :editionId";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Update statuses if provided
            if (isset($data['statuses'])) {
                $this->updateStatuses($userId, $isbn, $data['statuses']);
            }

            $this->db->commit();
            $this->logInfo('User book edited', ['userId' => $userId, 'isbn' => $isbn, 'editionId' => $editionId]);

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error editing user book', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("Could not edit user book: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateStatuses(int $userId, string $isbn, array $statuses): void
    {
        $weStartedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $weStartedTransaction = true;
        }

        try {
            // First, find the edition_id from the ISBN
            $sql = "SELECT edition_id FROM book_editions WHERE isbn_13 = :isbn OR isbn_10 = :isbn2 LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':isbn' => $isbn, ':isbn2' => $isbn]);
            $edition = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$edition) {
                if ($weStartedTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw new RuntimeException("Edition not found for ISBN: {$isbn}");
            }
            
            $editionId = (int) $edition['edition_id'];
            
            // Find the user_book_edition id
            $sql = "SELECT id FROM user_book_editions WHERE user_id = :userId AND edition_id = :editionId LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId, ':editionId' => $editionId]);
            $userBookEdition = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$userBookEdition) {
                if ($weStartedTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw new RuntimeException("User book edition not found for user: {$userId}, edition: {$editionId}");
            }
            
            $userBookEditionId = (int) $userBookEdition['id'];

            // Validar lógica de estados excluyentes
            $this->validateStatusLogic($statuses);

            // Delete existing statuses
            $stmtDelete = $this->db->prepare("
                DELETE FROM user_book_statuses 
                WHERE user_edition_id = :userEditionId
            ");
            $stmtDelete->execute([':userEditionId' => $userBookEditionId]);

            // Insert new statuses
            if (!empty($statuses)) {
                $stmtInsert = $this->db->prepare("
                    INSERT INTO user_book_statuses (user_edition_id, status_id) 
                    VALUES (:userEditionId, :statusId)
                ");

                foreach ($statuses as $statusName) {
                    $statusId = $this->getStatusId($statusName);
                    if ($statusId !== null) {
                        $stmtInsert->execute([
                            ':userEditionId' => $userBookEditionId,
                            ':statusId' => $statusId
                        ]);
                    } else {
                        $this->logWarning('Invalid status name', ['status' => $statusName]);
                    }
                }
            }

            if ($weStartedTransaction) {
                $this->db->commit();
            }

            $this->logInfo('User book statuses updated', ['userId' => $userId, 'isbn' => $isbn, 'editionId' => $editionId, 'statuses' => $statuses]);

        } catch (PDOException $e) {
            if ($weStartedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error updating user book statuses', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("Could not update user book statuses: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserStatuses(int $userId, string $isbn): array
    {
        try {
            // Por el modelo Work/Edition, como `hasBook()` arriba y `updateStatuses()`
            // aqui al lado. Consultaba `ubs.user_id` y `ubs.book_isbn`, columnas que
            // `user_book_statuses` NO tiene —sus campos son `user_edition_id`,
            // `status_id` y `updated_at`—: la query moria con
            // «1054 Unknown column 'ubs.user_id'», la PDOException caia en el catch
            // de abajo y el metodo devolvia `[]` SIEMPRE. Consecuencia visible:
            // `UpdateBookUserStatusesUseCase` leia estados previos vacios, asi que
            // reguardar los estados de un libro ya `read` apuntaba una entrada de
            // diario nueva que no debia existir. Es daño colateral del refactor
            // Work/Edition, que movio la tabla a `user_edition_id` y dejo atras esta
            // query de lectura.
            $sql = "SELECT bs.name
                    FROM book_statuses bs
                    INNER JOIN user_book_statuses ubs ON bs.id = ubs.status_id
                    INNER JOIN user_book_editions ube ON ube.id = ubs.user_edition_id
                    INNER JOIN book_editions be ON be.edition_id = ube.edition_id
                    WHERE ube.user_id = :userId
                      AND (be.isbn_13 = :isbn OR be.isbn_10 = :isbn2)
                    ORDER BY bs.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId, ':isbn' => $isbn, ':isbn2' => $isbn]);

            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        } catch (PDOException $e) {
            $this->logError('Error getting user book statuses', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return [];
        }
    }

    public function getCurrentPage(int $userId, string $isbn): ?int
    {
        try {
            // Misma resolucion Work/Edition que `hasBook()` y `getUserStatuses()`
            // aqui arriba: ISBN -> `book_editions` -> `user_book_editions`.
            $stmt = $this->db->prepare("
                SELECT ube.current_page
                FROM user_book_editions ube
                INNER JOIN book_editions be ON be.edition_id = ube.edition_id
                WHERE ube.user_id = :userId
                  AND (be.isbn_13 = :isbn OR be.isbn_10 = :isbn2)
                LIMIT 1
            ");
            $stmt->execute([':userId' => $userId, ':isbn' => $isbn, ':isbn2' => $isbn]);
            $currentPage = $stmt->fetchColumn();

            // `false` es «no hay fila»; un `current_page` NULL es una fila sin
            // pagina apuntada. Los dos salen como `null`, que es lo que el
            // llamante distingue de un 0 real.
            return $currentPage === false || $currentPage === null ? null : (int) $currentPage;

        } catch (PDOException $e) {
            $this->logError('Error getting current page', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return null;
        }
    }

    public function countByStatus(int $userId, string $statusName): int
    {
        try {
            $statusId = $this->getStatusId($statusName);
            if ($statusId === null) {
                return 0;
            }

            // Por el modelo Work/Edition, igual que `getUserStatuses()` y `hasBook()`.
            // Consultaba `ubs.book_isbn` y `ubs.user_id`, columnas que
            // `user_book_statuses` NO tiene —sus campos son `user_edition_id`,
            // `status_id` y `updated_at`—: la query moria con «1054 Unknown column»,
            // la PDOException caia en el catch de abajo y el metodo devolvia `0`
            // SIEMPRE, asi que los contadores de libros por estado salian a cero sin
            // que nada lo delatara. Mismo dano colateral del refactor Work/Edition que
            // arrastraba `getUserStatuses()`.
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT ubs.user_edition_id) as total
                FROM user_book_statuses ubs
                INNER JOIN user_book_editions ube ON ube.id = ubs.user_edition_id
                WHERE ube.user_id = :userId AND ubs.status_id = :statusId
            ");
            $stmt->execute([':userId' => $userId, ':statusId' => $statusId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? (int) $result['total'] : 0;

        } catch (PDOException $e) {
            $this->logError('Error counting books by status', $e, ['userId' => $userId, 'status' => $statusName]);
            return 0;
        }
    }

    public function getTrendingBooks(int $limit = 20, int $daysWindow = 90, ?int $userId = null): array
    {
        try {
            $readingStatusId = $this->getStatusId('reading');
            $recentDays = 30;
            
            // Build user library check if userId provided
            $userLibraryCheck = $userId 
                ? "EXISTS(SELECT 1 FROM user_book_editions ube2 INNER JOIN book_editions be2 ON ube2.edition_id = be2.edition_id WHERE be2.work_id = w.work_id AND ube2.user_id = {$userId}) as is_in_user_library,"
                : "0 as is_in_user_library,";
            
            // Use string interpolation for INTERVAL and repeated parameters
            // Values are type-hinted as int, so they're safe
            $sql = "
                SELECT 
                    COALESCE(be.isbn_13, be.isbn_10) as isbn,
                    w.title as title,
                    JSON_UNQUOTE(JSON_EXTRACT(w.authors, '$[0].name')) as author,
                    COALESCE(be.cover_url_large, be.cover_url_medium, be.cover_url_small) as coverUrl,
                    be.publisher as publisher,
                    be.pages as pages,
                    {$userLibraryCheck}
                    COUNT(DISTINCT ube.user_id) as user_count,
                    AVG(ube.work_rating) as avg_rating,
                    SUM(CASE 
                        WHEN ube.added_at >= DATE_SUB(NOW(), INTERVAL {$recentDays} DAY) 
                        THEN 1 ELSE 0 
                    END) as recent_adds,
                    SUM(CASE 
                        WHEN ubs.status_id = {$readingStatusId}
                        THEN 1 ELSE 0 
                    END) as reading_count,
                    MAX(ube.added_at) as last_added,
                    -- Trending score calculation
                    (
                        (COUNT(DISTINCT ube.user_id) * 10) +
                        (COALESCE(AVG(ube.work_rating), 0) * 5) +
                        (SUM(CASE WHEN ube.added_at >= DATE_SUB(NOW(), INTERVAL {$recentDays} DAY) THEN 1 ELSE 0 END) * 15) +
                        (SUM(CASE WHEN ubs.status_id = {$readingStatusId} THEN 1 ELSE 0 END) * 8) -
                        (DATEDIFF(NOW(), MAX(ube.added_at)) * 0.1)
                    ) as trending_score
                FROM user_book_editions ube
                INNER JOIN book_editions be ON ube.edition_id = be.edition_id
                INNER JOIN book_works w ON be.work_id = w.work_id
                LEFT JOIN user_book_statuses ubs ON ube.id = ubs.user_edition_id
                WHERE ube.added_at >= DATE_SUB(NOW(), INTERVAL {$daysWindow} DAY)
                GROUP BY w.work_id, COALESCE(be.isbn_13, be.isbn_10), w.title, be.publisher, be.pages,
                         be.cover_url_large, be.cover_url_medium, be.cover_url_small
                HAVING user_count >= 1
                ORDER BY trending_score DESC
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logDebug('Trending books fetched', [
                'count' => count($results),
                'limit' => $limit,
                'daysWindow' => $daysWindow
            ]);

            return $results;

        } catch (PDOException $e) {
            $this->logError('Error getting trending books', $e, [
                'limit' => $limit,
                'daysWindow' => $daysWindow
            ]);
            throw new RuntimeException("Could not get trending books: " . $e->getMessage(), 0, $e);
        }
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

    /**
     * Valida que los estados sean lógicamente compatibles
     *
     * Reglas:
     * - 'read' puede coexistir con cualquier otro estado (es histórico)
     * - Solo uno de: 'to-read', 'reading', 're-reading', 'paused', 'abandoned' (estado actual de lectura)
     * - Solo uno de: 'owned', 'want-to-buy' (estado de propiedad)
     *
     * @throws InvalidArgumentException si hay estados incompatibles
     */
    private function validateStatusLogic(array $statuses): void
    {
        // Categorías de estados
        $readingStates = ['to-read', 'reading', 're-reading', 'paused', 'abandoned'];
        $ownershipStates = ['owned', 'want-to-buy'];

        // Validar estados de lectura (solo uno permitido)
        $selectedReadingStates = array_intersect($statuses, $readingStates);
        if (count($selectedReadingStates) > 1) {
            throw new InvalidArgumentException(
                "Solo se permite un estado de actividad de lectura simultáneamente. " .
                "Recibidos: " . implode(', ', $selectedReadingStates)
            );
        }

        // Validar estados de propiedad (solo uno permitido)
        $selectedOwnershipStates = array_intersect($statuses, $ownershipStates);
        if (count($selectedOwnershipStates) > 1) {
            throw new InvalidArgumentException(
                "Solo se permite un estado de propiedad simultáneamente. " .
                "Recibidos: " . implode(', ', $selectedOwnershipStates)
            );
        }
    }
}
