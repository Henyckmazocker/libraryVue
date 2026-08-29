<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club;

use App\Domain\Model\Club;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Infrastructure\Persistence\Club\Mappers\ClubDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlClubRepository implements ClubRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'club';

    public function __construct(
        private readonly PDO             $db,
        private readonly ClubDataMapper  $mapper,
        private readonly LoggerInterface $logger
    ) {}

    public function save(Club $club): Club
    {
        try {
            $data = $this->mapper->toPersistence($club);

            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE . " (owner_id, name, description)"
                . " VALUES (:owner_id, :name, :description)"
            );
            $stmt->execute([
                ':owner_id'    => $data['owner_id'],
                ':name'        => $data['name'],
                ':description' => $data['description'],
            ]);

            $guardado = $this->findById((int) $this->db->lastInsertId());
            if ($guardado === null) {
                throw new RuntimeException('The club was inserted but could not be read back');
            }

            return $guardado;
        } catch (PDOException $e) {
            $this->logError('save failed', $e, ['owner_id' => $club->getOwnerId()]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findById(int $clubId): ?Club
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $clubId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;
        } catch (PDOException $e) {
            $this->logError('findById failed', $e, ['id' => $clubId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findForUser(int $userId): array
    {
        try {
            // Una sola consulta, sin `UNION`: el dueño se da de alta como
            // miembro al crear el club, así que `club_member` ya lo contiene.
            // Entra por `idx_club_member_user`.
            $stmt = $this->db->prepare(
                "SELECT c.* FROM " . self::TABLE . " c"
                . " INNER JOIN club_member m ON m.club_id = c.id"
                . " WHERE m.user_id = :uid"
                . " ORDER BY c.created_at DESC, c.id DESC"
            );
            $stmt->execute([':uid' => $userId]);

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findForUser failed', $e, ['user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $clubId): void
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
            $stmt->execute([':id' => $clubId]);
        } catch (PDOException $e) {
            $this->logError('delete failed', $e, ['id' => $clubId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
