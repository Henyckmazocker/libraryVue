<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club;

use App\Domain\Model\ClubProposal;
use App\Domain\Repository\Club\ClubProposalRepositoryInterface;
use App\Infrastructure\Persistence\Club\Mappers\ClubProposalDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlClubProposalRepository implements ClubProposalRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'club_proposal';

    public function __construct(
        private readonly PDO                    $db,
        private readonly ClubProposalDataMapper $mapper,
        private readonly LoggerInterface        $logger
    ) {}

    public function save(ClubProposal $proposal): ClubProposal
    {
        try {
            $data = $this->mapper->toPersistence($proposal);

            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE
                . " (round_id, user_id, entity_type, entity_id, entity_title, entity_cover)"
                . " VALUES (:round_id, :user_id, :entity_type, :entity_id, :entity_title, :entity_cover)"
            );
            $stmt->execute([
                ':round_id'     => $data['round_id'],
                ':user_id'      => $data['user_id'],
                ':entity_type'  => $data['entity_type'],
                ':entity_id'    => $data['entity_id'],
                ':entity_title' => $data['entity_title'],
                ':entity_cover' => $data['entity_cover'],
            ]);

            $guardada = $this->findById((int) $this->db->lastInsertId());
            if ($guardada === null) {
                throw new RuntimeException('The proposal was inserted but could not be read back');
            }

            return $guardada;
        } catch (PDOException $e) {
            $this->logError('save failed', $e, [
                'round_id' => $proposal->getRoundId(),
                'user_id'  => $proposal->getUserId(),
            ]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findByRound(int $roundId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM " . self::TABLE . " WHERE round_id = :rid ORDER BY created_at ASC, id ASC"
            );
            $stmt->execute([':rid' => $roundId]);

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('findByRound failed', $e, ['round_id' => $roundId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findById(int $proposalId): ?ClubProposal
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $proposalId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;
        } catch (PDOException $e) {
            $this->logError('findById failed', $e, ['id' => $proposalId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function hasProposed(int $roundId, int $userId): bool
    {
        try {
            // Sale entero del `UNIQUE (round_id, user_id)`.
            $stmt = $this->db->prepare(
                "SELECT 1 FROM " . self::TABLE . " WHERE round_id = :rid AND user_id = :uid LIMIT 1"
            );
            $stmt->execute([':rid' => $roundId, ':uid' => $userId]);

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            $this->logError('hasProposed failed', $e, ['round_id' => $roundId, 'user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function countByRound(int $roundId): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM " . self::TABLE . " WHERE round_id = :rid");
            $stmt->execute([':rid' => $roundId]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('countByRound failed', $e, ['round_id' => $roundId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deleteByUser(int $clubId, int $userId): void
    {
        try {
            // SOLO de las rondas abiertas. Las cerradas son historia y su
            // `winning_proposal_id` apunta aquí: borrar una propuesta antigua
            // dejaría la rotación sin saber quién ganó la vez anterior.
            $stmt = $this->db->prepare(
                "DELETE p FROM " . self::TABLE . " p"
                . " JOIN club_round r ON r.id = p.round_id"
                . " WHERE r.club_id = :cid AND r.phase <> 'closed' AND p.user_id = :uid"
            );
            $stmt->execute([':cid' => $clubId, ':uid' => $userId]);
        } catch (PDOException $e) {
            $this->logError('deleteByUser failed', $e, ['club_id' => $clubId, 'user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
