<?php

declare(strict_types=1);

namespace App\Domain\Repository\Club;

use App\Domain\Model\ClubRound;

interface ClubRoundRepositoryInterface
{
    /**
     * La ronda viva del club —la que no está `closed`—, o `null`. Sale por
     * `idx_club_round_open (club_id, phase)` y es la consulta de cada
     * `get_club`.
     */
    public function findOpen(int $clubId): ?ClubRound;

    /**
     * Abre una ronda en `proposing` **solo si no hay ninguna abierta**, y
     * devuelve la que quede viva —la recién creada o la que ya estaba—.
     *
     * Es idempotente a propósito: dos pestañas cargando el club a la vez
     * llamarían dos veces, y un club con dos rondas abiertas no tiene forma de
     * arreglarse solo. La guarda es un `INSERT ... SELECT ... WHERE NOT EXISTS`
     * en una sola sentencia, y quien pierda la carrera relee y usa la del otro.
     */
    public function openIfNone(int $clubId): ClubRound;

    /**
     * Quién ganó la ronda anterior, que es lo único que la rotación necesita
     * saber de ella. `null` si el club no ha cerrado ninguna todavía.
     *
     * Es una consulta y no dos —«la última cerrada» y luego «de quién es su
     * propuesta»— porque la necesitan **dos** llamantes: el use case de
     * proponer y el `canPropose` de `get_club`. Partida en dos, la unión
     * `club_round → club_proposal` sería la segunda copia de la regla.
     */
    public function findPreviousWinnerUserId(int $clubId): ?int;

    /**
     * Pasa la ronda a `voting`. Devuelve `true` solo si esta llamada fue la que
     * la movió: el `UPDATE` lleva `AND phase = 'proposing'`, así que dos
     * llamadas simultáneas no abren el voto dos veces.
     */
    public function startVoting(int $roundId): bool;

    /**
     * Sube el recuento a `ballot + 1` sin cambiar de fase: la revotación del
     * desempate son votos nuevos de la MISMA ronda. Lleva `AND ballot = :actual`
     * para que dos lecturas simultáneas no la suban dos veces.
     */
    public function nextBallot(int $roundId, int $currentBallot): bool;

    /**
     * Cierra la ronda escribiendo el ganador. Devuelve `true` solo si esta
     * llamada fue la que la cerró (`AND phase <> 'closed'`): es lo que impide
     * que dos `get_club` simultáneos creen dos `club_pick` del mismo ganador, y
     * lo que hace que un ganador SORTEADO no cambie al releer.
     */
    public function close(int $roundId, int $winningProposalId): bool;
}
