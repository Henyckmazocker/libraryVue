<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Commands\ProposeClubItemCommand;
use App\Domain\Model\ClubProposal;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubProposalRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\Repository\Club\ClubRoundRepositoryInterface;
use App\Domain\Services\ClubRoundResolver;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Proponer un ítem para la ronda en curso.
 *
 * **Es de cualquier MIEMBRO**, no del dueño, y ahí está el sentido del plan
 * entero: elegir qué lee el club deja de ser un privilegio de quien lo creó.
 * Lo que sí es del dueño son las dos válvulas —abrir el voto y cerrarlo— y el
 * `set_club_pick` de escape.
 *
 * Las tres negativas salen con códigos distintos a propósito, porque el
 * frontend hace tres cosas distintas con ellas: **403** si no eres miembro o te
 * toca rotar (avisa y esconde el botón), **409** si la ronda ya está votando
 * (recarga: la pantalla está desfasada) y **400** si ya propusiste (una por
 * persona, y la tuya ya está en la lista).
 */
class ProposeClubItemUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface         $clubRepository,
        private readonly ClubMemberRepositoryInterface   $memberRepository,
        private readonly ClubRoundRepositoryInterface    $roundRepository,
        private readonly ClubProposalRepositoryInterface $proposalRepository,
        private readonly ClubRoundResolver               $reglas,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'ProposeClubItem'; }

    protected function doExecute($command): ClubProposal
    {
        if (!$command instanceof ProposeClubItemCommand) {
            throw new InvalidArgumentException('Command must be an instance of ProposeClubItemCommand');
        }

        if ($this->clubRepository->findById($command->clubId) === null) {
            throw new RuntimeException('Club not found');
        }

        if (!$this->memberRepository->isMember($command->clubId, $command->userId)) {
            throw new DomainException('You are not a member of this club');
        }

        // La ronda NO se abre aquí. La abre `get_club`, que es la lectura que
        // pinta la pantalla desde la que se propone, y abrirla también aquí
        // significaría copiar la regla de cuándo toca —«no hay ítem activo»— a
        // un segundo sitio. La consecuencia es que un cliente que proponga sin
        // haber leído el club recibe 409; es correcto y se arregla recargando,
        // que es justo lo que ese código le dice que haga.
        $ronda = $this->roundRepository->findOpen($command->clubId);
        if ($ronda === null) {
            throw new ClubRoundConflictException('This club is not choosing an item right now');
        }

        $motivo = $this->reglas->proposalBlockReason(
            $ronda->getPhase(),
            $this->rotaLeToca($command->clubId, $command->userId),
            $this->proposalRepository->hasProposed((int) $ronda->getId(), $command->userId)
        );

        // Un `match` y no tres `if` sueltos: si mañana el resolver devuelve un
        // motivo nuevo, esto falla ruidosamente en vez de dejarlo pasar.
        match ($motivo) {
            ClubRoundResolver::REASON_VOTING =>
                throw new ClubRoundConflictException('This round is already voting'),
            ClubRoundResolver::REASON_ROTATION =>
                throw new DomainException('You won the last round; it is someone else\'s turn to propose'),
            ClubRoundResolver::REASON_ALREADY_PROPOSED =>
                throw new InvalidArgumentException('You have already proposed an item for this round'),
            null => null,
        };

        return $this->proposalRepository->save(new ClubProposal(
            id:          null,
            roundId:     (int) $ronda->getId(),
            userId:      $command->userId,
            entityType:  $command->entityType,
            entityId:    $command->entityId,
            entityTitle: $command->entityTitle,
            entityCover: $command->entityCover
        ));
    }

    /**
     * La rotación, con los miembros **actuales**: quien se fue del club no
     * cuenta ni como proponente ni como ganador anterior a excluir.
     */
    private function rotaLeToca(int $clubId, int $userId): bool
    {
        $miembros = array_map(
            static fn (array $m): int => (int) $m['user_id'],
            $this->memberRepository->findByClub($clubId)
        );

        return $this->reglas->mustRotate(
            $userId,
            $miembros,
            $this->roundRepository->findPreviousWinnerUserId($clubId)
        );
    }
}
