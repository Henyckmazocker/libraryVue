<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club;

use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlClubMemberRepository implements ClubMemberRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'club_member';

    public function __construct(
        private readonly PDO             $db,
        private readonly LoggerInterface $logger
    ) {}

    public function add(int $clubId, int $userId): void
    {
        try {
            // `IGNORE` sobre la PK compuesta: aceptar dos veces la misma
            // invitación no puede ser un 500. Es lo que permite que
            // `AcceptClubInvitationUseCase` haga el alta ANTES de resolver la
            // fila del buzón y que un fallo intermedio se arregle solo.
            $stmt = $this->db->prepare(
                "INSERT IGNORE INTO " . self::TABLE . " (club_id, user_id) VALUES (:cid, :uid)"
            );
            $stmt->execute([':cid' => $clubId, ':uid' => $userId]);
        } catch (PDOException $e) {
            $this->logError('add failed', $e, ['club_id' => $clubId, 'user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function remove(int $clubId, int $userId): void
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM " . self::TABLE . " WHERE club_id = :cid AND user_id = :uid"
            );
            $stmt->execute([':cid' => $clubId, ':uid' => $userId]);
        } catch (PDOException $e) {
            $this->logError('remove failed', $e, ['club_id' => $clubId, 'user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function isMember(int $clubId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM " . self::TABLE . " WHERE club_id = :cid AND user_id = :uid LIMIT 1"
            );
            $stmt->execute([':cid' => $clubId, ':uid' => $userId]);

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            $this->logError('isMember failed', $e, ['club_id' => $clubId, 'user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findByClub(int $clubId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT m.user_id, u.name, u.username, u.picture, m.joined_at"
                . " FROM " . self::TABLE . " m"
                . " INNER JOIN users u ON u.id = m.user_id"
                . " WHERE m.club_id = :cid"
                . " ORDER BY m.joined_at ASC, m.user_id ASC"
            );
            $stmt->execute([':cid' => $clubId]);

            return array_map(static fn (array $row) => [
                'user_id'   => (int) $row['user_id'],
                'name'      => (string) $row['name'],
                'username'  => $row['username'] !== null ? (string) $row['username'] : null,
                'picture'   => $row['picture'] !== null ? (string) $row['picture'] : null,
                'joined_at' => (string) $row['joined_at'],
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findByClub failed', $e, ['club_id' => $clubId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function countMembers(int $clubId): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM " . self::TABLE . " WHERE club_id = :cid"
            );
            $stmt->execute([':cid' => $clubId]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('countMembers failed', $e, ['club_id' => $clubId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
