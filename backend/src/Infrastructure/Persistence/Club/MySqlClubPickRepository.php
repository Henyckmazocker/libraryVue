<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club;

use App\Domain\Model\ClubPick;
use App\Domain\Repository\Club\ClubPickRepositoryInterface;
use App\Infrastructure\Persistence\Club\Mappers\ClubPickDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlClubPickRepository implements ClubPickRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'club_pick';

    public function __construct(
        private readonly PDO                $db,
        private readonly ClubPickDataMapper $mapper,
        private readonly LoggerInterface    $logger
    ) {}

    public function save(ClubPick $pick): ClubPick
    {
        try {
            $data = $this->mapper->toPersistence($pick);

            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE
                . " (club_id, entity_type, entity_id, entity_title, entity_cover)"
                . " VALUES (:club_id, :entity_type, :entity_id, :entity_title, :entity_cover)"
            );
            $stmt->execute([
                ':club_id'      => $data['club_id'],
                ':entity_type'  => $data['entity_type'],
                ':entity_id'    => $data['entity_id'],
                ':entity_title' => $data['entity_title'],
                ':entity_cover' => $data['entity_cover'],
            ]);

            $guardado = $this->findById((int) $this->db->lastInsertId());
            if ($guardado === null) {
                throw new RuntimeException('The pick was inserted but could not be read back');
            }

            return $guardado;
        } catch (PDOException $e) {
            $this->logError('save failed', $e, ['club_id' => $pick->getClubId()]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findActive(int $clubId): ?ClubPick
    {
        try {
            // Por `idx_club_pick_active (club_id, finished_at)`. El `LIMIT 1`
            // no es fe en la regla: si por lo que sea hubiera dos activos, se
            // sirve el más reciente en vez de reventar la pantalla del club.
            $stmt = $this->db->prepare(
                "SELECT * FROM " . self::TABLE
                . " WHERE club_id = :cid AND finished_at IS NULL"
                . " ORDER BY started_at DESC, id DESC LIMIT 1"
            );
            $stmt->execute([':cid' => $clubId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;
        } catch (PDOException $e) {
            $this->logError('findActive failed', $e, ['club_id' => $clubId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findHistory(int $clubId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM " . self::TABLE
                . " WHERE club_id = :cid AND finished_at IS NOT NULL"
                . " ORDER BY finished_at DESC, id DESC"
            );
            $stmt->execute([':cid' => $clubId]);

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findHistory failed', $e, ['club_id' => $clubId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function finish(int $pickId): bool
    {
        try {
            // `AND finished_at IS NULL` es lo que hace segura la escritura
            // desde una LECTURA: dos `get_club` simultáneos entran los dos, y
            // solo uno afecta a una fila. El otro recibe `false` y no pisa la
            // fecha ya puesta.
            $stmt = $this->db->prepare(
                "UPDATE " . self::TABLE . " SET finished_at = NOW()"
                . " WHERE id = :id AND finished_at IS NULL"
            );
            $stmt->execute([':id' => $pickId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('finish failed', $e, ['id' => $pickId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    private function findById(int $pickId): ?ClubPick
    {
        $stmt = $this->db->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $pickId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapper->toDomain($row) : null;
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
