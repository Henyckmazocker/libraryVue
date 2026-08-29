<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club;

use App\Domain\Repository\Club\ClubVoteRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlClubVoteRepository implements ClubVoteRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'club_vote';

    public function __construct(
        private readonly PDO             $db,
        private readonly LoggerInterface $logger
    ) {}

    public function cast(int $roundId, int $ballot, int $userId, int $proposalId): void
    {
        try {
            // No hay mapper porque no hay modelo: un voto es una tupla sin
            // reglas propias, y las que tiene —una por persona, y se puede
            // cambiar— viven en la PK y en este `ON DUPLICATE KEY UPDATE`.
            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE . " (round_id, ballot, user_id, proposal_id)"
                . " VALUES (:rid, :ballot, :uid, :pid)"
                . " ON DUPLICATE KEY UPDATE proposal_id = VALUES(proposal_id), voted_at = NOW()"
            );
            $stmt->execute([
                ':rid'    => $roundId,
                ':ballot' => $ballot,
                ':uid'    => $userId,
                ':pid'    => $proposalId,
            ]);
        } catch (PDOException $e) {
            $this->logError('cast failed', $e, ['round_id' => $roundId, 'user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function tally(int $roundId, int $ballot): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT proposal_id, COUNT(*) AS votos FROM " . self::TABLE
                . " WHERE round_id = :rid AND ballot = :ballot GROUP BY proposal_id"
            );
            $stmt->execute([':rid' => $roundId, ':ballot' => $ballot]);

            $recuento = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $recuento[(int) $fila['proposal_id']] = (int) $fila['votos'];
            }

            return $recuento;
        } catch (PDOException $e) {
            $this->logError('tally failed', $e, ['round_id' => $roundId, 'ballot' => $ballot]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function countVoters(int $roundId, int $ballot): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM " . self::TABLE . " WHERE round_id = :rid AND ballot = :ballot"
            );
            $stmt->execute([':rid' => $roundId, ':ballot' => $ballot]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('countVoters failed', $e, ['round_id' => $roundId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findVoteOf(int $roundId, int $ballot, int $userId): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT proposal_id FROM " . self::TABLE
                . " WHERE round_id = :rid AND ballot = :ballot AND user_id = :uid LIMIT 1"
            );
            $stmt->execute([':rid' => $roundId, ':ballot' => $ballot, ':uid' => $userId]);
            $id = $stmt->fetchColumn();

            return $id === false ? null : (int) $id;
        } catch (PDOException $e) {
            $this->logError('findVoteOf failed', $e, ['round_id' => $roundId, 'user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deleteByUser(int $clubId, int $userId): void
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE v FROM " . self::TABLE . " v"
                . " JOIN club_round r ON r.id = v.round_id"
                . " WHERE r.club_id = :cid AND r.phase <> 'closed' AND v.user_id = :uid"
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
