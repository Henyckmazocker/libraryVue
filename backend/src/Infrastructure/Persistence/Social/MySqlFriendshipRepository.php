<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Social;

use App\Domain\Model\Friendship;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Social\Mappers\FriendshipDataMapper;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlFriendshipRepository implements FriendshipRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'friendships';

    public function __construct(
        private readonly PDO                 $db,
        private readonly FriendshipDataMapper $mapper,
        private readonly LoggerInterface     $logger
    ) {}

    public function findByUsers(int $userId1, int $userId2): ?Friendship
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM " . self::TABLE
                . " WHERE (requester_id = :u1 AND addressee_id = :u2)"
                . "    OR (requester_id = :u2b AND addressee_id = :u1b)"
                . " LIMIT 1"
            );
            $stmt->execute([':u1' => $userId1, ':u2' => $userId2, ':u2b' => $userId2, ':u1b' => $userId1]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->mapper->toDomain($row) : null;
        } catch (PDOException $e) {
            $this->logError('findByUsers failed', $e, ['u1' => $userId1, 'u2' => $userId2]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findAcceptedByUser(int $userId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM " . self::TABLE
                . " WHERE (requester_id = :uid OR addressee_id = :uid2)"
                . "   AND status = 'accepted'"
            );
            $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findAcceptedByUser failed', $e, ['user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findPendingRequestsForUser(int $userId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM " . self::TABLE
                . " WHERE addressee_id = :uid AND status = 'pending'"
                . " ORDER BY created_at DESC"
            );
            $stmt->execute([':uid' => $userId]);
            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findPendingRequestsForUser failed', $e, ['user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function save(Friendship $friendship): Friendship
    {
        try {
            $data = $this->mapper->toPersistence($friendship);
            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE . " (requester_id, addressee_id, status) VALUES (:requester_id, :addressee_id, :status)"
            );
            $stmt->execute($data);
            $id = (int) $this->db->lastInsertId();
            return new Friendship($id, $friendship->getRequesterId(), $friendship->getAddresseeId(), $friendship->getStatus());
        } catch (PDOException $e) {
            $this->logError('save friendship failed', $e);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(Friendship $friendship): void
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE " . self::TABLE . " SET status = :status WHERE id = :id"
            );
            $stmt->execute([':status' => $friendship->getStatus(), ':id' => $friendship->getId()]);
        } catch (PDOException $e) {
            $this->logError('update friendship failed', $e, ['id' => $friendship->getId()]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $friendshipId): void
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
            $stmt->execute([':id' => $friendshipId]);
        } catch (PDOException $e) {
            $this->logError('delete friendship failed', $e, ['id' => $friendshipId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function countPendingRequestsForUser(int $userId): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM " . self::TABLE . " WHERE addressee_id = :uid AND status = 'pending'"
            );
            $stmt->execute([':uid' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('countPendingRequestsForUser failed', $e, ['user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
