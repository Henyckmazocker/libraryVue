<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Common;

use App\Domain\Repository\OwnedFormatRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;
use App\Infrastructure\Logging\LoggingService;
use PDO;
use PDOException;
use RuntimeException;

/**
 * MySQL implementation of OwnedFormatRepositoryInterface.
 * Reads from the item_owned_formats lookup table.
 */
final class MySqlOwnedFormatRepository implements OwnedFormatRepositoryInterface
{
    use HydrationHelpersTrait;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByEntityType(string $entityType): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT id, value, label, sort_order
                 FROM item_owned_formats
                 WHERE entity_type = :entityType AND is_active = 1
                 ORDER BY sort_order ASC'
            );
            $stmt->execute([':entityType' => $entityType]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($r) => [
                'id'         => (int) $r['id'],
                'value'      => (string) $r['value'],
                'label'      => (string) $r['label'],
                'sort_order' => (int) $r['sort_order'],
            ], $rows);
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Could not find ownership formats for entity type '{$entityType}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function findByEntityTypeAndValue(string $entityType, string $value): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT id, value, label
                 FROM item_owned_formats
                 WHERE entity_type = :entityType AND value = :value AND is_active = 1
                 LIMIT 1'
            );
            $stmt->execute([':entityType' => $entityType, ':value' => $value]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return [
                'id'    => (int) $row['id'],
                'value' => (string) $row['value'],
                'label' => (string) $row['label'],
            ];
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Could not find ownership format '{$value}' for '{$entityType}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT id, entity_type, value, label
                 FROM item_owned_formats
                 WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return [
                'id'          => (int) $row['id'],
                'entity_type' => (string) $row['entity_type'],
                'value'       => (string) $row['value'],
                'label'       => (string) $row['label'],
            ];
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Could not find ownership format with id {$id}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
