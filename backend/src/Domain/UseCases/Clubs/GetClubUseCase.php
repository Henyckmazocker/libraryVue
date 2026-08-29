<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Queries\GetClubQuery;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubPickRepositoryInterface;
use App\Domain\Repository\Club\ClubProposalRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\Repository\Club\ClubRoundRepositoryInterface;
use App\Domain\Repository\Club\ClubVoteRepositoryInterface;
use App\Domain\Services\ClubCompletion;
use App\Domain\Services\ClubRoundProgress;
use App\Domain\Services\ClubRoundResolver;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * La pantalla del club.
 *
 * **No hay visibilidad que consultar**: un club no es público, colaborativo ni
 * privado — o eres miembro o no lo eres. Por eso no existe un `ClubAccess`
 * equivalente a `ListAccess`: no habría tabla de verdad que escribir, solo un
 * `isMember`. La amistad tampoco entra: se es amigo para poder ser invitado,
 * pero lo que da acceso es haber aceptado.
 *
 * **Y aquí es donde el club avanza solo.** El proyecto no tiene cron ni
 * workers (`Infrastructure/Http/PostResponse.php:12`), así que el cierre
 * automático del ítem se evalúa al leer, que es cuando la consulta de progreso
 * se hace de todas formas. Eso significa que esta LECTURA ESCRIBE, con dos
 * reglas que no se pueden relajar (ver `cerrarSiTodosAcabaron`).
 *
 * **Y desde la votación son varias escrituras, no una**: además de cerrar el
 * ítem cuando todos acaban, esta lectura abre la ronda, abre el voto cuando han
 * propuesto todos, sube el recuento del desempate y cierra la ronda creando el
 * ítem ganador (ver `avanzarLaRonda` y `Domain\Services\ClubRoundProgress`).
 * Todas van condicionadas en el `WHERE` y **ninguna puede tumbar la lectura**:
 * un club que no se pinta es peor que un club que avanza en la siguiente
 * visita.
 */
class GetClubUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface       $clubRepository,
        private readonly ClubMemberRepositoryInterface $memberRepository,
        private readonly ClubPickRepositoryInterface   $pickRepository,
        private readonly ClubRoundRepositoryInterface  $roundRepository,
        private readonly ClubProposalRepositoryInterface $proposalRepository,
        private readonly ClubVoteRepositoryInterface   $voteRepository,
        private readonly ClubCompletion                $completion,
        private readonly ClubRoundResolver             $reglas,
        private readonly ClubRoundProgress             $progreso,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetClub'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetClubQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetClubQuery');
        }

        $club = $this->clubRepository->findById($query->clubId);
        if ($club === null) {
            throw new RuntimeException('Club not found');
        }

        // El controller lo traduce a 403, y se distingue de «no existe» (404) a
        // propósito, igual que en `GetListUseCase`.
        if (!$this->memberRepository->isMember($query->clubId, $query->userId)) {
            throw new DomainException('You are not a member of this club');
        }

        $activo = $this->pickRepository->findActive($query->clubId);
        if ($activo !== null && $this->cerrarSiTodosAcabaron($activo)) {
            // Se acaba de cerrar: se relee para que la respuesta lleve el
            // `finished_at` real de la base y no una fecha calculada aquí.
            $activo = null;
        }

        $ronda = null;
        if ($activo === null) {
            $ronda = $this->avanzarLaRonda($club, $query->userId);

            // Cerrar la ronda CREA el ítem, en esta misma petición. Hay que
            // releerlo: si no, el club aparecería sin ítem y con una ronda
            // cerrada hasta que alguien volviera a mirar.
            $activo = $this->pickRepository->findActive($query->clubId);
            if ($activo !== null) {
                $ronda = null;
            }
        }

        return [
            'club' => $club->toArray() + [
                // Renombrar, invitar, elegir ítem, cerrarlo y borrar son del
                // dueño. Se manda resuelto y no se recalcula en el cliente: la
                // regla vive en el servidor.
                'is_owner' => $club->isOwnedBy($query->userId),
            ],
            'members' => $this->memberRepository->findByClub($query->clubId),
            'pick'    => $activo?->toArray(),
            // Sin ítem activo, el club está eligiendo el siguiente. Con ítem
            // activo NO hay ronda: son estados excluyentes, y mandar las dos
            // cosas invitaría a la pantalla a pintar un voto sobre un club que
            // ya está leyendo algo.
            'round'   => $ronda,
            'history' => array_map(
                static fn ($pick) => $pick->toArray(),
                $this->pickRepository->findHistory($query->clubId)
            ),
        ];
    }

    /**
     * El cierre automático, con las dos guardas que lo hacen seguro:
     *
     *  - El `UPDATE` de `finish()` lleva `AND finished_at IS NULL`, así que dos
     *    lecturas simultáneas no pueden cerrar dos veces ni pisar una fecha ya
     *    puesta. La que pierde recibe `false`.
     *  - **Un fallo aquí no puede tumbar la lectura.** Un club que no se puede
     *    pintar es peor que un club que se cierra en la siguiente visita, así
     *    que se traga la excepción y se deja el ítem activo.
     */
    private function cerrarSiTodosAcabaron($pick): bool
    {
        try {
            if (!$this->completion->everyoneFinished($pick)) {
                return false;
            }

            return $this->pickRepository->finish((int) $pick->getId());
        } catch (Throwable $e) {
            $this->logger->warning('[GetClub] Auto-finish skipped', [
                'club_id' => $pick->getClubId(),
                'pick_id' => $pick->getId(),
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * La otra escritura de esta lectura: sin ítem activo, el club abre una
     * ronda para elegir el siguiente.
     *
     * **Abrir es idempotente o se abren N.** Dos pestañas cargando el club a la
     * vez llamarían dos veces, y un club con dos rondas abiertas no se arregla
     * solo; la guarda está en `openIfNone`, que inserta solo si no hay ninguna
     * viva y relee para que quien pierda la carrera use la del otro.
     *
     * Y como el cierre automático de arriba, **un fallo aquí no tumba la
     * lectura**: se devuelve el club sin bloque de ronda y se reintenta en la
     * siguiente visita.
     */
    private function avanzarLaRonda($club, int $userId): ?array
    {
        $clubId = (int) $club->getId();

        try {
            // Abrir, abrir el voto y cerrar viven en `ClubRoundProgress`, que es
            // la copia única de cuándo avanza cada fase; aquí solo se pinta lo
            // que haya quedado. Si la ronda se cerró, `findOpen` da `null` y el
            // club responde con su ítem nuevo en vez de con la ronda.
            $ronda = $this->progreso->advance($club);
            if (!$ronda->isOpen()) {
                return null;
            }

            $rondaId = (int) $ronda->getId();
            $ballot  = $ronda->getBallot();

            $miembros = array_map(
                static fn (array $m): int => (int) $m['user_id'],
                $this->memberRepository->findByClub($clubId)
            );

            // `canPropose` y `reasonBlocked` van RESUELTOS desde aquí: la
            // rotación es una regla de dominio, y recalcularla en el cliente
            // para decidir si pintar el botón sería la segunda copia.
            $motivo = $this->reglas->proposalBlockReason(
                $ronda->getPhase(),
                $this->reglas->mustRotate(
                    $userId,
                    $miembros,
                    $this->roundRepository->findPreviousWinnerUserId($clubId)
                ),
                $this->proposalRepository->hasProposed($rondaId, $userId)
            );

            $recuento = $this->voteRepository->tally($rondaId, $ballot);
            $vivas    = $this->progreso->eligibleProposalIds($ronda);

            return $ronda->toArray() + [
                'canPropose'    => $motivo === null,
                'reasonBlocked' => $motivo,
                'proposals'     => array_map(
                    static function ($propuesta) use ($recuento, $vivas): array {
                        $id = (int) $propuesta->getId();

                        return $propuesta->toArray() + [
                            // Solo el RECUENTO. Quién votó a quién no viaja:
                            // lo que está en el DOM está enseñado, por mucho
                            // que la pantalla no lo pinte.
                            'votes'      => $recuento[$id] ?? 0,
                            'eliminated' => $vivas !== null && !in_array($id, $vivas, true),
                        ];
                    },
                    $this->proposalRepository->findByRound($rondaId)
                ),
                // El voto PROPIO sí, que es lo que deja marcar el que elegiste
                // y permite cambiarlo.
                'myVote'        => $this->voteRepository->findVoteOf($rondaId, $ballot, $userId),
                'pendingVoters' => max(
                    0,
                    count($miembros) - $this->voteRepository->countVoters($rondaId, $ballot)
                ),
            ];
        } catch (Throwable $e) {
            $this->logger->warning('[GetClub] Round not advanced', [
                'club_id' => $clubId,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }
}
