<?php

declare(strict_types=1);

namespace App\Domain\Repository\Club;

use App\Domain\Model\ClubProposal;

interface ClubProposalRepositoryInterface
{
    /**
     * Guarda la propuesta. El `UNIQUE (round_id, user_id)` puede saltar: es la
     * regla «una por persona» y el use case la traduce a 400, no a 500.
     */
    public function save(ClubProposal $proposal): ClubProposal;

    /**
     * Las propuestas de una ronda, de la más antigua a la más nueva — que es el
     * orden en que se pintan y, si algún día hiciera falta, el criterio de
     * desempate de reserva del plan B.
     *
     * @return ClubProposal[]
     */
    public function findByRound(int $roundId): array;

    public function findById(int $proposalId): ?ClubProposal;

    public function hasProposed(int $roundId, int $userId): bool;

    public function countByRound(int $roundId): int;

    /**
     * Borra la propuesta de quien se va del club. `club_member` no es clave
     * ajena de esta tabla, así que salir NO la arrastra por CASCADE: sin esto,
     * «han propuesto todos» cuenta miembros actuales contra propuestas de gente
     * que ya no está y la fase no cierra nunca.
     */
    public function deleteByUser(int $clubId, int $userId): void;
}
