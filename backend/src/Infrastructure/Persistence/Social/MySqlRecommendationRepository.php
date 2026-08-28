<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Social;

use App\Domain\Model\Recommendation;
use App\Domain\Repository\Social\RecommendationRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Social\Mappers\RecommendationDataMapper;
use PDO;
use PDOException;
use RuntimeException;
use Psr\Log\LoggerInterface;

final class MySqlRecommendationRepository implements RecommendationRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'recommendations';

    public function __construct(
        private readonly PDO                      $db,
        private readonly RecommendationDataMapper $mapper,
        private readonly LoggerInterface          $logger
    ) {}

    public function save(Recommendation $recommendation): Recommendation
    {
        try {
            $data = $this->mapper->toPersistence($recommendation);

            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE
                . " (sender_id, recipient_id, entity_type, entity_id, entity_title, entity_cover, comment, status)"
                . " VALUES (:sender_id, :recipient_id, :entity_type, :entity_id, :entity_title, :entity_cover, :comment, :status)"
            );
            $stmt->execute([
                ':sender_id'    => $data['sender_id'],
                ':recipient_id' => $data['recipient_id'],
                ':entity_type'  => $data['entity_type'],
                ':entity_id'    => $data['entity_id'],
                ':entity_title' => $data['entity_title'],
                ':entity_cover' => $data['entity_cover'],
                ':comment'      => $data['comment'],
                ':status'       => $data['status'],
            ]);

            $guardada = $this->findById((int) $this->db->lastInsertId());
            if ($guardada === null) {
                throw new RuntimeException('The recommendation was inserted but could not be read back');
            }

            return $guardada;
        } catch (PDOException $e) {
            $this->logError('save failed', $e, [
                'sender_id'    => $recommendation->getSenderId(),
                'recipient_id' => $recommendation->getRecipientId(),
            ]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findById(int $recommendationId): ?Recommendation
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $recommendationId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;
        } catch (PDOException $e) {
            $this->logError('findById failed', $e, ['id' => $recommendationId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findForRecipient(int $recipientId, string $status, int $limit, int $offset): array
    {
        try {
            // LIMIT y OFFSET no admiten parámetro con emulación desactivada, así
            // que van casteados a entero, nunca interpolados desde el payload.
            $stmt = $this->db->prepare(
                "SELECT * FROM " . self::TABLE
                . " WHERE recipient_id = :uid AND status = :status"
                . " ORDER BY created_at DESC, id DESC"
                . " LIMIT " . max(1, min(100, $limit)) . " OFFSET " . max(0, $offset)
            );
            $stmt->execute([':uid' => $recipientId, ':status' => $status]);

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findForRecipient failed', $e, ['user_id' => $recipientId, 'status' => $status]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function countForRecipient(int $recipientId, string $status): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM " . self::TABLE . " WHERE recipient_id = :uid AND status = :status"
            );
            $stmt->execute([':uid' => $recipientId, ':status' => $status]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('countForRecipient failed', $e, ['user_id' => $recipientId, 'status' => $status]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function existsBetween(int $senderId, int $recipientId, string $entityType, string $entityId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM " . self::TABLE
                . " WHERE sender_id = :sender AND recipient_id = :recipient"
                . "   AND entity_type = :type AND entity_id = :eid"
                . " LIMIT 1"
            );
            $stmt->execute([
                ':sender'    => $senderId,
                ':recipient' => $recipientId,
                ':type'      => $entityType,
                ':eid'       => $entityId,
            ]);

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            $this->logError('existsBetween failed', $e, ['sender_id' => $senderId, 'recipient_id' => $recipientId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(Recommendation $recommendation): void
    {
        try {
            // `resolved_at` se sella aquí y no en el modelo: la hora que vale es
            // la del servidor de base de datos, la misma que puso `created_at`.
            $stmt = $this->db->prepare(
                "UPDATE " . self::TABLE
                . " SET status = :status, resolved_at = CURRENT_TIMESTAMP"
                . " WHERE id = :id"
            );
            $stmt->execute([
                ':status' => $recommendation->getStatus(),
                ':id'     => $recommendation->getId(),
            ]);
        } catch (PDOException $e) {
            $this->logError('update failed', $e, ['id' => $recommendation->getId()]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
