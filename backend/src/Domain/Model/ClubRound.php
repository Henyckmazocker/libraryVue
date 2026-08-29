<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

/**
 * Una ronda de votación: se abre cuando el club se queda sin ítem activo y se
 * cierra creando el siguiente.
 *
 * **La fase es un ENUM y no dos fechas nulas.** `club_pick` puede permitirse
 * decir «activo es `finished_at IS NULL`» porque son dos estados; aquí son tres
 * con transiciones propias, y escribirlos como timestamps obligaría a leer
 * «`voting_started_at IS NOT NULL AND closed_at IS NULL`» cada vez que alguien
 * quiere saber si se está votando.
 *
 * **Y el ganador se guarda.** `winningProposalId` no se deduce del recuento: el
 * desempate final es un sorteo y la ronda se resuelve al LEER el club, así que
 * recalcularlo daría un ganador distinto en cada `get_club`. Ver
 * `Domain\Services\ClubRoundResolver`.
 */
class ClubRound
{
    public const PHASE_PROPOSING = 'proposing';
    public const PHASE_VOTING    = 'voting';
    public const PHASE_CLOSED    = 'closed';

    public const PHASES = [self::PHASE_PROPOSING, self::PHASE_VOTING, self::PHASE_CLOSED];

    public function __construct(
        private ?int   $id,
        private int    $clubId,
        private string $phase = self::PHASE_PROPOSING,
        private int    $ballot = 1,
        private ?int   $winningProposalId = null,
        private ?string $createdAt = null,
        private ?string $closedAt = null
    ) {
        if (!in_array($phase, self::PHASES, true)) {
            throw new InvalidArgumentException('Invalid round phase: ' . $phase);
        }
        if ($ballot < 1) {
            throw new InvalidArgumentException('Ballot must be 1 or greater');
        }
    }

    public function getId(): ?int                { return $this->id; }
    public function getClubId(): int             { return $this->clubId; }
    public function getPhase(): string           { return $this->phase; }
    public function getBallot(): int             { return $this->ballot; }
    public function getWinningProposalId(): ?int { return $this->winningProposalId; }
    public function getCreatedAt(): ?string      { return $this->createdAt; }
    public function getClosedAt(): ?string       { return $this->closedAt; }

    /**
     * Abierta es «no cerrada», que incluye las dos fases vivas. Es la pregunta
     * que hace `get_club` para no abrir una segunda.
     */
    public function isOpen(): bool
    {
        return $this->phase !== self::PHASE_CLOSED;
    }

    public function isProposing(): bool
    {
        return $this->phase === self::PHASE_PROPOSING;
    }

    public function isVoting(): bool
    {
        return $this->phase === self::PHASE_VOTING;
    }

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'club_id'             => $this->clubId,
            'phase'               => $this->phase,
            'ballot'              => $this->ballot,
            'winning_proposal_id' => $this->winningProposalId,
            'created_at'          => $this->createdAt,
            'closed_at'           => $this->closedAt,
        ];
    }
}
