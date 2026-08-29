<?php

declare(strict_types=1);

namespace App\Domain\Services;

use InvalidArgumentException;

/**
 * La máquina de la ronda de votación del club: quién puede proponer, cuándo
 * cada fase puede cerrar, y qué sale del recuento.
 *
 * La regla existe AQUÍ y en ningún otro sitio, igual que `ListAccess` con la
 * visibilidad de las listas y `ClubCompletion` con el completado. **Es lógica
 * pura**: no toca la base de datos, no sabe qué es un `PDO` y no persiste nada.
 * Quien la llama —`GetClubUseCase` al leer el club, y los use cases de
 * proponer y votar— es quien escribe lo que ella decide.
 *
 * ## Por qué no basta con contar votos
 *
 * Una ronda puede quedarse clavada de cuatro formas, y **una ronda muerta es un
 * club muerto**: sin ronda no hay ítem, y sin ítem no hay club. Las cuatro
 * salidas están en esta clase:
 *
 * | # | El atasco                                     | La salida                                    |
 * |---|-----------------------------------------------|----------------------------------------------|
 * | 1 | Un miembro no propone nunca                   | el dueño abre el voto con lo que haya         |
 * | 2 | Un miembro no vota nunca                      | el dueño cierra con los votos que haya        |
 * | 3 | Empate                                        | revotación entre las empatadas, y luego sorteo|
 * | 4 | La rotación deja menos de dos proponentes     | esa ronda no excluye a nadie                  |
 *
 * Los dos primeros son la **válvula del dueño** (`$forcedByOwner`), y por eso
 * `canOpenVote` y `canCloseVote` la reciben en vez de tener dos métodos cada
 * uno: la condición de cierre automático y la de cierre forzado son la misma
 * pregunta con distinta respuesta, y separarlas invitaba a que un llamante
 * comprobara solo una.
 *
 * ## La rotación, y la excepción que salva a los clubs pequeños
 *
 * «Quien ganó la ronda anterior no propone en la siguiente» deja **muerto** un
 * club de dos —si gana A, solo puede proponer B: una propuesta y nada que
 * votar— y uno de uno, donde el dueño gana su propia ronda y no vuelve a
 * proponer jamás. Por eso la exclusión **se salta si dejaría menos de dos
 * proponentes**: con tres o más miembros funciona como se espera, y con dos o
 * con uno no excluye a nadie.
 *
 * ## Y el sorteo se sortea UNA vez
 *
 * `resolve()` decide, pero **no recuerda**. Quien la llama tiene que persistir
 * el ganador en `club_round.winning_proposal_id` y cerrar la fase: la ronda se
 * resuelve al leer el club, y un sorteo recalculado en cada `get_club` haría
 * que dos miembros mirando a la vez vieran ganadores distintos.
 */
final class ClubRoundResolver
{
    /** Hay un único más votado: se cierra la ronda con él. */
    public const ACTION_CLOSE = 'close';

    /** Empate en el primer recuento: se revota solo entre las empatadas. */
    public const ACTION_REVOTE = 'revote';

    /** No hay ni un voto que contar: no hay nada que resolver todavía. */
    public const ACTION_NONE = 'none';

    /**
     * Quién puede proponer en esta ronda.
     *
     * @param int[]    $memberIds            los miembros ACTUALES del club
     * @param int|null $previousWinnerUserId el autor de la propuesta ganadora de
     *                                       la última ronda cerrada, o `null` si
     *                                       es la primera ronda del club
     *
     * @return int[] los ids que pueden proponer, en el orden recibido
     */
    public function eligibleProposers(array $memberIds, ?int $previousWinnerUserId): array
    {
        $miembros = array_values(array_unique($memberIds));

        if ($previousWinnerUserId === null) {
            return $miembros;
        }

        $sinElGanador = array_values(
            array_filter($miembros, static fn (int $id): bool => $id !== $previousWinnerUserId)
        );

        // La excepción que impide que la rotación se coma a los clubs
        // pequeños. Se compara con 2 y no con 1 a propósito: con un solo
        // proponente posible no hay nada que votar, así que la ronda estaría
        // igual de muerta que sin proponentes.
        if (count($sinElGanador) < 2) {
            return $miembros;
        }

        return $sinElGanador;
    }

    /**
     * Si a este usuario le toca rotar en esta ronda. Es la pregunta que responde
     * el `canPropose` / `reasonBlocked` de `get_club`, y va resuelta desde el
     * servidor: recalcular la rotación en el cliente sería la segunda copia de
     * la regla.
     *
     * @param int[] $memberIds
     */
    public function mustRotate(int $userId, array $memberIds, ?int $previousWinnerUserId): bool
    {
        return !in_array($userId, $this->eligibleProposers($memberIds, $previousWinnerUserId), true);
    }

    /** Por qué no puedes proponer: la ronda ya está votando. */
    public const REASON_VOTING = 'voting';

    /** Por qué no puedes proponer: ganaste la ronda anterior y te toca rotar. */
    public const REASON_ROTATION = 'rotation';

    /** Por qué no puedes proponer: ya propusiste, y es una por persona. */
    public const REASON_ALREADY_PROPOSED = 'already_proposed';

    /**
     * Por qué este usuario no puede proponer, o `null` si puede.
     *
     * Es lo que `get_club` manda como `reasonBlocked` **resuelto desde el
     * servidor**, y lo que el use case de proponer traduce a su código de
     * error. Vive aquí y no en los dos sitios porque es la misma regla: si el
     * cliente la recalculase para pintar el aviso, sería la segunda copia, y la
     * que se olvidaría de actualizar.
     *
     * El orden importa: la fase gana a la rotación, y la rotación a haber
     * propuesto ya. A quien le toca rotar no le sirve saber que además no ha
     * propuesto —no puede—, y con el voto abierto no propone nadie.
     */
    public function proposalBlockReason(string $phase, bool $mustRotate, bool $hasProposed): ?string
    {
        if ($phase !== 'proposing') {
            return self::REASON_VOTING;
        }

        if ($mustRotate) {
            return self::REASON_ROTATION;
        }

        if ($hasProposed) {
            return self::REASON_ALREADY_PROPOSED;
        }

        return null;
    }

    /**
     * ¿Puede pasar la ronda de `proposing` a `voting`?
     *
     * Automático cuando han propuesto **todos los que podían**; forzado por el
     * dueño con lo que haya, que es la salida del atasco 1. En los dos casos
     * hace falta **al menos una** propuesta: abrir un voto sin nada que votar
     * dejaría la ronda clavada un escalón más allá, que es peor.
     */
    public function canOpenVote(int $proposalCount, int $eligibleCount, bool $forcedByOwner): bool
    {
        if ($proposalCount < 1) {
            return false;
        }

        return $forcedByOwner || $proposalCount >= $eligibleCount;
    }

    /**
     * ¿Puede cerrarse la votación?
     *
     * Automático cuando han votado **todos los miembros actuales** —los de
     * ahora, no los que había al abrirla: quien se fue no vuelve a votar y su
     * voto se borra al salir, o el recuento no cuadra nunca—; forzado por el
     * dueño con los votos que haya, que es la salida del atasco 2. Sin un solo
     * voto no se cierra ni forzando: no habría ganador que escribir.
     */
    public function canCloseVote(int $voteCount, int $memberCount, bool $forcedByOwner): bool
    {
        if ($voteCount < 1) {
            return false;
        }

        return $forcedByOwner || $voteCount >= $memberCount;
    }

    /**
     * Qué sale del recuento, en el orden de la sección 🧭 del plan:
     *
     *  1. un único máximo            → se cierra con él
     *  2. empate y `ballot === 1`    → se revota entre las empatadas
     *  3. empate y `ballot >= 2`     → **sorteo** entre las empatadas, y se cierra
     *
     * El paso 3 es lo que garantiza terminación: sin él, dos personas votando lo
     * mismo cada vez repiten el empate para siempre, y con todas las propuestas
     * a un voto no hay «la menos votada» que eliminar.
     *
     * @param array<int,int> $tally  propuesta => votos, del ballot en curso
     * @param callable|null  $sorteo elige un índice de `[0, n)`; por defecto
     *                               `random_int`. Va como parámetro y no por el
     *                               constructor para que `new ClubRoundResolver()`
     *                               no necesite cableado en `container.php` — y
     *                               porque PHP-DI no autowirea los opcionales.
     *
     * @return array{action:string, winnerProposalId:?int, tied:int[]}
     */
    public function resolve(array $tally, int $ballot, ?callable $sorteo = null): array
    {
        if ($ballot < 1) {
            throw new InvalidArgumentException('Ballot must be 1 or greater');
        }

        $conVotos = array_filter($tally, static fn (int $votos): bool => $votos > 0);

        if ($conVotos === []) {
            return ['action' => self::ACTION_NONE, 'winnerProposalId' => null, 'tied' => []];
        }

        $maximo    = max($conVotos);
        $empatadas = array_keys($conVotos, $maximo, true);

        if (count($empatadas) === 1) {
            return [
                'action'           => self::ACTION_CLOSE,
                'winnerProposalId' => $empatadas[0],
                'tied'             => [],
            ];
        }

        if ($ballot === 1) {
            // Las que no empatan en el máximo quedan eliminadas: la revotación
            // es del MISMO round con `ballot + 1`, no una ronda nueva, que es
            // para lo que `ballot` está en la PK de `club_vote`.
            return [
                'action'           => self::ACTION_REVOTE,
                'winnerProposalId' => null,
                'tied'             => $empatadas,
            ];
        }

        $elegir = $sorteo ?? static fn (int $n): int => random_int(0, $n - 1);

        return [
            'action'           => self::ACTION_CLOSE,
            'winnerProposalId' => $empatadas[$elegir(count($empatadas))],
            'tied'             => $empatadas,
        ];
    }
}
