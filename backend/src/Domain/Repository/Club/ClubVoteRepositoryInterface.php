<?php

declare(strict_types=1);

namespace App\Domain\Repository\Club;

interface ClubVoteRepositoryInterface
{
    /**
     * Vota, o **cambia el voto**: mientras la ronda siga abierta se puede
     * cambiar de idea, y eso lo da el `ON DUPLICATE KEY UPDATE` sobre la PK
     * `(round_id, ballot, user_id)`. Un voto por persona y recuento.
     */
    public function cast(int $roundId, int $ballot, int $userId, int $proposalId): void;

    /**
     * El recuento del `ballot` en curso: `proposal_id => votos`. Es lo único de
     * la votación que viaja al cliente, junto con el voto propio: **quién votó
     * a quién no es asunto de nadie**, y mandarlo lo haría visible con
     * «inspeccionar elemento» por mucho que la pantalla no lo pinte.
     *
     * @return array<int,int>
     */
    public function tally(int $roundId, int $ballot): array;

    /** Cuánta gente ha votado en este recuento, que es lo que cierra la fase. */
    public function countVoters(int $roundId, int $ballot): int;

    /** A qué propuesta voté yo en este recuento, o `null`. */
    public function findVoteOf(int $roundId, int $ballot, int $userId): ?int;

    /**
     * Borra los votos de quien se va del club, en las rondas **abiertas**. Como
     * con las propuestas: `club_member` no es clave ajena de `club_vote`, así
     * que salir no los arrastra, y sin esto «han votado todos» compara miembros
     * actuales contra votos de gente que ya no está.
     */
    public function deleteByUser(int $clubId, int $userId): void;
}
