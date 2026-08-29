<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\DTO\Commands\SetClubPickCommand;
use App\Domain\Model\Club;
use App\Domain\Model\ClubRound;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubProposalRepositoryInterface;
use App\Domain\Repository\Club\ClubRoundRepositoryInterface;
use App\Domain\Repository\Club\ClubVoteRepositoryInterface;
use App\Domain\UseCases\Clubs\SetClubPickUseCase;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Hacer avanzar la ronda: abrir el voto, subir el recuento del desempate y
 * cerrarla creando el ítem.
 *
 * La regla de **cuándo** avanza vive en `ClubRoundResolver`, que es lógica pura
 * y no sabe de bases de datos. Lo que vive aquí es **quién se lo pregunta y qué
 * escribe con la respuesta**, y existe como servicio por el mismo motivo que
 * `ClubCompletion`: lo consultan **dos** llamantes —`GetClubUseCase` al leer el
 * club, y las dos acciones del dueño— y meterlo en cualquiera de ellos dejaría
 * la copia en el otro.
 *
 * ## Por qué avanza al LEER
 *
 * El proyecto no tiene cron ni workers (`Infrastructure/Http/PostResponse.php:12`),
 * así que la ronda no puede avanzar sola en el momento exacto en que el último
 * vota. Se evalúa al leer el club, que es cuando las consultas ya se hacen. De
 * ahí las dos válvulas del dueño: si alguien no participa nunca, la ronda no
 * avanzaría jamás por sí sola.
 *
 * ## Y por qué el ganador se ESCRIBE
 *
 * El desempate final es un sorteo. Si el ganador se dedujera del recuento en
 * cada lectura, dos miembros mirando a la vez verían libros distintos, y el
 * mismo miembro vería uno distinto al recargar. `close()` escribe
 * `winning_proposal_id` y pone la fase en `closed`: a partir de ahí la ronda es
 * historia y no se recalcula nada.
 */
final class ClubRoundProgress
{
    public function __construct(
        private readonly ClubRoundRepositoryInterface    $rounds,
        private readonly ClubProposalRepositoryInterface $proposals,
        private readonly ClubVoteRepositoryInterface     $votes,
        private readonly ClubMemberRepositoryInterface   $members,
        private readonly ClubRoundResolver               $reglas,
        private readonly SetClubPickUseCase              $setClubPick,
        private readonly LoggerInterface                 $logger
    ) {}

    /**
     * Abre la ronda si hace falta, la hace avanzar hasta donde pueda, y
     * devuelve cómo ha quedado.
     *
     * @param bool $forzadoPorElDueno la válvula: con `true`, la fase avanza con
     *                                lo que haya en vez de esperar a todos
     */
    public function advance(Club $club, bool $forzadoPorElDueno = false): ClubRound
    {
        $clubId = (int) $club->getId();
        $ronda  = $this->rounds->openIfNone($clubId);

        if ($ronda->isProposing() && $this->puedeAbrirElVoto($ronda, $clubId, $forzadoPorElDueno)) {
            $this->rounds->startVoting((int) $ronda->getId());

            // Se relee en vez de mutar el objeto: quien acaba de perder la
            // carrera con otra petición tiene que ver la fase de verdad, no la
            // que creía estar poniendo.
            $ronda = $this->rounds->findOpen($clubId) ?? $ronda;
        }

        if ($ronda->isVoting()) {
            $ronda = $this->resolverElVoto($club, $ronda, $forzadoPorElDueno);
        }

        return $ronda;
    }

    /**
     * Las propuestas que siguen vivas en el recuento en curso, o `null` si lo
     * están todas.
     *
     * En el `ballot` 1 vota todo el mundo a lo que quiera. En el 2 —la
     * revotación del desempate— **solo se puede votar a las que empataron**, y
     * cuáles fueron no hace falta guardarlo: el empate es el máximo del
     * recuento anterior, que es determinista. Lo aleatorio del diseño es solo
     * el sorteo final, y ESE sí se persiste.
     *
     * @return int[]|null
     */
    public function eligibleProposalIds(ClubRound $ronda): ?array
    {
        if ($ronda->getBallot() <= 1) {
            return null;
        }

        $anterior = $this->reglas->resolve(
            $this->votes->tally((int) $ronda->getId(), $ronda->getBallot() - 1),
            $ronda->getBallot() - 1
        );

        return $anterior['tied'] === [] ? null : $anterior['tied'];
    }

    private function puedeAbrirElVoto(ClubRound $ronda, int $clubId, bool $forzado): bool
    {
        $conDerecho = $this->reglas->eligibleProposers(
            array_map(
                static fn (array $m): int => (int) $m['user_id'],
                $this->members->findByClub($clubId)
            ),
            $this->rounds->findPreviousWinnerUserId($clubId)
        );

        return $this->reglas->canOpenVote(
            $this->proposals->countByRound((int) $ronda->getId()),
            count($conDerecho),
            $forzado
        );
    }

    /**
     * El recuento y sus tres salidas: gana una, se revota entre las empatadas, o
     * el sorteo la resuelve.
     */
    private function resolverElVoto(Club $club, ClubRound $ronda, bool $forzado): ClubRound
    {
        $clubId  = (int) $club->getId();
        $rondaId = (int) $ronda->getId();

        $puedeCerrar = $this->reglas->canCloseVote(
            $this->votes->countVoters($rondaId, $ronda->getBallot()),
            $this->members->countMembers($clubId),
            $forzado
        );

        if (!$puedeCerrar) {
            return $ronda;
        }

        $salida = $this->reglas->resolve(
            $this->votes->tally($rondaId, $ronda->getBallot()),
            $ronda->getBallot()
        );

        if ($salida['action'] === ClubRoundResolver::ACTION_REVOTE) {
            $this->rounds->nextBallot($rondaId, $ronda->getBallot());

            return $this->rounds->findOpen($clubId) ?? $ronda;
        }

        if ($salida['action'] !== ClubRoundResolver::ACTION_CLOSE) {
            return $ronda;
        }

        // `close()` lleva `AND phase <> 'closed'`: si dos lecturas simultáneas
        // llegan aquí, solo una cierra y solo esa crea el ítem. La otra recibe
        // `false` y se encuentra la ronda ya cerrada, sin duplicar el pick.
        if ($this->rounds->close($rondaId, (int) $salida['winnerProposalId'])) {
            $this->crearElItem($club, (int) $salida['winnerProposalId']);
        }

        return $this->rounds->findOpen($clubId)
            ?? new ClubRound(
                id:                $rondaId,
                clubId:            $clubId,
                phase:             ClubRound::PHASE_CLOSED,
                ballot:            $ronda->getBallot(),
                winningProposalId: (int) $salida['winnerProposalId']
            );
    }

    /**
     * El ítem ganador pasa a ser el activo, **reutilizando `SetClubPickUseCase`**
     * — que a partir de aquí tiene dos llamantes: el dueño eligiendo a mano, y
     * este cierre. Es lo que mantiene la regla «solo un activo por club» en un
     * único sitio.
     *
     * Se ejecuta a nombre del **dueño** porque ese use case comprueba
     * `Club::isOwnedBy`, y quien dispara el cierre puede ser cualquier miembro
     * leyendo la pantalla. No es un rodeo a la autorización: quien decidió fue
     * el club entero votando, y el dueño es a quien pertenece el club.
     *
     * Y **un fallo aquí no puede tumbar la lectura**, igual que el cierre
     * automático del ítem: la ronda ya está cerrada con su ganador escrito, así
     * que el club se queda un momento sin ítem activo y la siguiente visita no
     * lo arregla sola —hay que mirarlo—, pero la pantalla se pinta.
     */
    private function crearElItem(Club $club, int $proposalId): void
    {
        try {
            $ganadora = $this->proposals->findById($proposalId);
            if ($ganadora === null) {
                throw new \RuntimeException('The winning proposal disappeared');
            }

            $this->setClubPick->execute(new SetClubPickCommand(
                userId:      $club->getOwnerId(),
                clubId:      (int) $club->getId(),
                entityType:  $ganadora->getEntityType(),
                entityId:    $ganadora->getEntityId(),
                entityTitle: $ganadora->getEntityTitle(),
                entityCover: $ganadora->getEntityCover()
            ));
        } catch (Throwable $e) {
            $this->logger->error('[ClubRoundProgress] The winner could not become the active item', [
                'club_id'     => $club->getId(),
                'proposal_id' => $proposalId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
