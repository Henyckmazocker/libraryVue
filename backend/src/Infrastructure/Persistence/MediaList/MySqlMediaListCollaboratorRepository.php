<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MediaList;

use App\Domain\Repository\MediaList\MediaListCollaboratorRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Nació en el M1 con solo `isCollaborator` —y no en el M4, como decía el plan—
 * porque `ListAccess` se inyecta en los use cases de aquel hito: sin una clase
 * concreta, PHP-DI no puede construirlo y `get_list` respondería 500. El resto
 * de operaciones llegó con el M4.
 */
final class MySqlMediaListCollaboratorRepository implements MediaListCollaboratorRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'media_list_collaborator';

    public function __construct(
        private readonly PDO             $db,
        private readonly LoggerInterface $logger
    ) {}

    public function isCollaborator(int $listId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM " . self::TABLE . " WHERE list_id = :lid AND user_id = :uid LIMIT 1"
            );
            $stmt->execute([':lid' => $listId, ':uid' => $userId]);

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            $this->logError('isCollaborator failed', $e, ['list_id' => $listId, 'user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function add(int $listId, int $userId): void
    {
        try {
            // La PK compuesta ya impide el duplicado; `IGNORE` lo convierte en
            // una operación idempotente en vez de un 500 cuando la misma
            // invitación se acepta desde dos pestañas.
            $stmt = $this->db->prepare(
                "INSERT IGNORE INTO " . self::TABLE . " (list_id, user_id) VALUES (:lid, :uid)"
            );
            $stmt->execute([':lid' => $listId, ':uid' => $userId]);
        } catch (PDOException $e) {
            $this->logError('add failed', $e, ['list_id' => $listId, 'user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function remove(int $listId, int $userId): void
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM " . self::TABLE . " WHERE list_id = :lid AND user_id = :uid"
            );
            $stmt->execute([':lid' => $listId, ':uid' => $userId]);
        } catch (PDOException $e) {
            $this->logError('remove failed', $e, ['list_id' => $listId, 'user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findByList(int $listId): array
    {
        try {
            // El JOIN con `users` va aquí y no en el use case: pedir el nombre
            // de cada colaborador por separado sería el N+1 de siempre, y aquí
            // la relación es una clave ajena, no una entidad distinta.
            $stmt = $this->db->prepare(
                "SELECT c.user_id, u.username, u.name, u.picture, c.added_at"
                . " FROM " . self::TABLE . " c"
                . " INNER JOIN users u ON u.id = c.user_id"
                . " WHERE c.list_id = :lid"
                . " ORDER BY c.added_at ASC"
            );
            $stmt->execute([':lid' => $listId]);

            return array_map(static fn (array $fila) => [
                'user_id'  => (int) $fila['user_id'],
                // Quien no tiene username se muestra por su nombre, como en el
                // resto de la app.
                'username' => (string) ($fila['username'] ?? $fila['name']),
                'name'     => (string) $fila['name'],
                'picture'  => $fila['picture'],
                'added_at' => (string) $fila['added_at'],
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findByList failed', $e, ['list_id' => $listId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function removeAll(int $listId): void
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . self::TABLE . " WHERE list_id = :lid");
            $stmt->execute([':lid' => $listId]);
        } catch (PDOException $e) {
            $this->logError('removeAll failed', $e, ['list_id' => $listId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
