<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MediaList;

use App\Domain\Model\MediaListItem;
use App\Domain\Repository\MediaList\MediaListItemRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\MediaList\Mappers\MediaListItemDataMapper;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlMediaListItemRepository implements MediaListItemRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'media_list_item';

    public function __construct(
        private readonly PDO                     $db,
        private readonly MediaListItemDataMapper $mapper,
        private readonly LoggerInterface         $logger
    ) {}

    public function add(MediaListItem $item): MediaListItem
    {
        try {
            $data = $this->mapper->toPersistence($item);

            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE
                . " (list_id, entity_type, entity_id, entity_title, entity_cover, added_by, position)"
                . " VALUES (:list_id, :entity_type, :entity_id, :entity_title, :entity_cover, :added_by, :position)"
            );
            $stmt->execute([
                ':list_id'      => $data['list_id'],
                ':entity_type'  => $data['entity_type'],
                ':entity_id'    => $data['entity_id'],
                ':entity_title' => $data['entity_title'],
                ':entity_cover' => $data['entity_cover'],
                ':added_by'     => $data['added_by'],
                ':position'     => $data['position'],
            ]);

            $guardado = $this->findById((int) $this->db->lastInsertId());
            if ($guardado === null) {
                throw new RuntimeException('The item was inserted but could not be read back');
            }

            return $guardado;
        } catch (PDOException $e) {
            $this->logError('add failed', $e, [
                'list_id'   => $item->getListId(),
                'entity_id' => $item->getEntityId(),
            ]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findById(int $itemId): ?MediaListItem
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $itemId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;
        } catch (PDOException $e) {
            $this->logError('findById failed', $e, ['id' => $itemId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findByList(int $listId): array
    {
        try {
            // `position` es 0 para todos mientras el orden manual no exista, así
            // que el desempate por `id` es lo que de verdad ordena hoy.
            $stmt = $this->db->prepare(
                "SELECT * FROM " . self::TABLE . " WHERE list_id = :lid ORDER BY position ASC, id ASC"
            );
            $stmt->execute([':lid' => $listId]);

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findByList failed', $e, ['list_id' => $listId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function exists(int $listId, string $entityType, string $entityId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM " . self::TABLE
                . " WHERE list_id = :lid AND entity_type = :type AND entity_id = :eid LIMIT 1"
            );
            $stmt->execute([':lid' => $listId, ':type' => $entityType, ':eid' => $entityId]);

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            $this->logError('exists failed', $e, ['list_id' => $listId, 'entity_id' => $entityId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function countByLists(array $listIds): array
    {
        if ($listIds === []) {
            return [];
        }

        try {
            // Los ids son enteros que salen de la base, no del payload, pero se
            // castean igualmente: es lo que hace segura la interpolación que el
            // `IN (...)` de longitud variable obliga a escribir.
            $ids = implode(',', array_map('intval', $listIds));

            $stmt = $this->db->query(
                "SELECT list_id, COUNT(*) AS total FROM " . self::TABLE
                . " WHERE list_id IN ({$ids}) GROUP BY list_id"
            );

            $conteos = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $conteos[(int) $fila['list_id']] = (int) $fila['total'];
            }

            // Una lista vacía no sale del GROUP BY y su tarjeta necesita el 0:
            // sin esto, el frontend tendría que distinguir «cero» de «no vino».
            foreach ($listIds as $id) {
                $conteos[(int) $id] ??= 0;
            }

            return $conteos;
        } catch (PDOException $e) {
            $this->logError('countByLists failed', $e, ['count' => count($listIds)]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function remove(int $itemId): void
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
            $stmt->execute([':id' => $itemId]);
        } catch (PDOException $e) {
            $this->logError('remove failed', $e, ['id' => $itemId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
