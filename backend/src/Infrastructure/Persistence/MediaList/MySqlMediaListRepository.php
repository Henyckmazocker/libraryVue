<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MediaList;

use App\Domain\Model\MediaList;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\MediaList\Mappers\MediaListDataMapper;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlMediaListRepository implements MediaListRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'media_list';

    public function __construct(
        private readonly PDO                 $db,
        private readonly MediaListDataMapper $mapper,
        private readonly LoggerInterface     $logger
    ) {}

    public function save(MediaList $list): MediaList
    {
        try {
            $data = $this->mapper->toPersistence($list);

            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE . " (owner_id, name, description, visibility)"
                . " VALUES (:owner_id, :name, :description, :visibility)"
            );
            $stmt->execute([
                ':owner_id'    => $data['owner_id'],
                ':name'        => $data['name'],
                ':description' => $data['description'],
                ':visibility'  => $data['visibility'],
            ]);

            $guardada = $this->findById((int) $this->db->lastInsertId());
            if ($guardada === null) {
                throw new RuntimeException('The list was inserted but could not be read back');
            }

            return $guardada;
        } catch (PDOException $e) {
            $this->logError('save failed', $e, ['owner_id' => $list->getOwnerId()]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findById(int $listId): ?MediaList
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $listId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;
        } catch (PDOException $e) {
            $this->logError('findById failed', $e, ['id' => $listId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findForUser(int $userId): array
    {
        try {
            // Mías o en las que colaboro, en una sola consulta. Traerse todas y
            // filtrar con `ListAccess` en PHP significaría leer las listas de
            // todos los usuarios de la instalación.
            $stmt = $this->db->prepare(
                "SELECT l.* FROM " . self::TABLE . " l"
                . " WHERE l.owner_id = :uid"
                . " UNION"
                . " SELECT l.* FROM " . self::TABLE . " l"
                . " INNER JOIN media_list_collaborator c ON c.list_id = l.id"
                . " WHERE c.user_id = :uid2"
                . " ORDER BY updated_at DESC, id DESC"
            );
            // Dos marcadores para el mismo valor: con `emulate prepares` en OFF,
            // MySQL no permite reusar un nombre en dos sitios de la sentencia.
            $stmt->execute([':uid' => $userId, ':uid2' => $userId]);

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findForUser failed', $e, ['user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findPublicByOwner(int $ownerId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM " . self::TABLE
                . " WHERE owner_id = :uid AND visibility = :vis"
                . " ORDER BY updated_at DESC, id DESC"
            );
            $stmt->execute([':uid' => $ownerId, ':vis' => MediaList::VISIBILITY_PUBLIC]);

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findPublicByOwner failed', $e, ['owner_id' => $ownerId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(MediaList $list): void
    {
        try {
            // `updated_at` lo sella la columna con ON UPDATE, no este SQL: la
            // hora que vale es la del servidor de base de datos, la misma que
            // puso `created_at`.
            $stmt = $this->db->prepare(
                "UPDATE " . self::TABLE
                . " SET name = :name, description = :description, visibility = :visibility"
                . " WHERE id = :id"
            );
            $stmt->execute([
                ':name'        => $list->getName(),
                ':description' => $list->getDescription(),
                ':visibility'  => $list->getVisibility(),
                ':id'          => $list->getId(),
            ]);
        } catch (PDOException $e) {
            $this->logError('update failed', $e, ['id' => $list->getId()]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $listId): void
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
            $stmt->execute([':id' => $listId]);
        } catch (PDOException $e) {
            $this->logError('delete failed', $e, ['id' => $listId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
