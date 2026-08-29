<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club;

use App\Domain\Model\ClubRound;
use App\Domain\Repository\Club\ClubRoundRepositoryInterface;
use App\Infrastructure\Persistence\Club\Mappers\ClubRoundDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlClubRoundRepository implements ClubRoundRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'club_round';

    public function __construct(
        private readonly PDO                 $db,
        private readonly ClubRoundDataMapper $mapper,
        private readonly LoggerInterface     $logger
    ) {}

    public function findOpen(int $clubId): ?ClubRound
    {
        try {
            // Por `idx_club_round_open (club_id, phase)`. El `LIMIT 1` no es fe
            // en la idempotencia de `openIfNone`: si por lo que fuera hubiera
            // dos abiertas, se sirve la más antigua —la legítima, la que
            // ganó la carrera— en vez de reventar la pantalla del club.
            $stmt = $this->db->prepare(
                "SELECT * FROM " . self::TABLE
                . " WHERE club_id = :cid AND phase <> 'closed'"
                . " ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute([':cid' => $clubId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;
        } catch (PDOException $e) {
            $this->logError('findOpen failed', $e, ['club_id' => $clubId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function openIfNone(int $clubId): ClubRound
    {
        try {
            // La guarda va DENTRO de la sentencia, no en un `findOpen` previo:
            // entre la lectura y el `INSERT` cabe la petición de la otra
            // pestaña. MySQL no tiene índices parciales, así que un UNIQUE
            // sobre «abierta» no es posible y esta es la aproximación que sí
            // se puede escribir.
            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE . " (club_id, phase, ballot)"
                . " SELECT :cid, 'proposing', 1 FROM DUAL"
                . " WHERE NOT EXISTS ("
                . "   SELECT 1 FROM " . self::TABLE . " WHERE club_id = :cid2 AND phase <> 'closed'"
                . " )"
            );
            $stmt->execute([':cid' => $clubId, ':cid2' => $clubId]);

            // Se relee SIEMPRE, haya insertado o no: quien pierde la carrera se
            // encuentra la ronda del otro y la usa, que es justo lo que se
            // quiere. Devolver la recién insertada por `lastInsertId()` daría
            // 0 en ese caso.
            $abierta = $this->findOpen($clubId);
            if ($abierta === null) {
                throw new RuntimeException('The round was opened but could not be read back');
            }

            return $abierta;
        } catch (PDOException $e) {
            $this->logError('openIfNone failed', $e, ['club_id' => $clubId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findPreviousWinnerUserId(int $clubId): ?int
    {
        try {
            // `winning_proposal_id` no tiene clave ajena a propósito —la
            // propuesta ganadora tiene que sobrevivir a que su autor se vaya
            // del club—, así que el JOIN puede no casar. Un `null` aquí
            // significa «no hay a quién rotar», que es lo correcto: la ronda
            // no excluye a nadie.
            $stmt = $this->db->prepare(
                "SELECT p.user_id FROM " . self::TABLE . " r"
                . " JOIN club_proposal p ON p.id = r.winning_proposal_id"
                . " WHERE r.club_id = :cid AND r.phase = 'closed'"
                . " ORDER BY r.closed_at DESC, r.id DESC LIMIT 1"
            );
            $stmt->execute([':cid' => $clubId]);
            $userId = $stmt->fetchColumn();

            return $userId === false ? null : (int) $userId;
        } catch (PDOException $e) {
            $this->logError('findPreviousWinnerUserId failed', $e, ['club_id' => $clubId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function startVoting(int $roundId): bool
    {
        return $this->transicion(
            "UPDATE " . self::TABLE . " SET phase = 'voting'"
            . " WHERE id = :id AND phase = 'proposing'",
            [':id' => $roundId],
            'startVoting',
            $roundId
        );
    }

    public function nextBallot(int $roundId, int $currentBallot): bool
    {
        return $this->transicion(
            "UPDATE " . self::TABLE . " SET ballot = ballot + 1"
            . " WHERE id = :id AND phase = 'voting' AND ballot = :ballot",
            [':id' => $roundId, ':ballot' => $currentBallot],
            'nextBallot',
            $roundId
        );
    }

    public function close(int $roundId, int $winningProposalId): bool
    {
        return $this->transicion(
            "UPDATE " . self::TABLE
            . " SET phase = 'closed', winning_proposal_id = :winner, closed_at = NOW()"
            . " WHERE id = :id AND phase <> 'closed'",
            [':id' => $roundId, ':winner' => $winningProposalId],
            'close',
            $roundId
        );
    }

    /**
     * Las tres transiciones son el mismo `UPDATE` condicionado: la condición va
     * en el `WHERE` y el veredicto es `rowCount()`. Quien recibe `false` es que
     * llegó tarde, no que fallara — igual que `ClubPickRepository::finish()`, y
     * por el mismo motivo: esto se ejecuta desde una LECTURA del club, que dos
     * pestañas pueden lanzar a la vez.
     */
    private function transicion(string $sql, array $params, string $que, int $roundId): bool
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError($que . ' failed', $e, ['round_id' => $roundId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
