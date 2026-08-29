<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Commands\VoteClubProposalCommand;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubProposalRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\Repository\Club\ClubRoundRepositoryInterface;
use App\Domain\Repository\Club\ClubVoteRepositoryInterface;
use App\Domain\Services\ClubRoundProgress;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Votar una propuesta, o **cambiar el voto**: mientras la ronda siga abierta se
 * puede cambiar de idea, y eso sale del `ON DUPLICATE KEY UPDATE` sobre la PK
 * `(round_id, ballot, user_id)`.
 *
 * Es de cualquier MIEMBRO, y **se puede votar la propia propuesta**: se decidió
 * así con los ojos abiertos, porque prohibirlo en un club de dos convierte cada
 * ronda en un empate garantizado.
 *
 * Tres negativas, tres códigos: **403** si no eres miembro, **409** si la ronda
 * no está votando (la pantalla está desfasada: recarga) y **404** si la
 * propuesta no es de esta ronda o quedó eliminada en el desempate.
 */
class VoteClubProposalUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface         $clubRepository,
        private readonly ClubMemberRepositoryInterface   $memberRepository,
        private readonly ClubRoundRepositoryInterface    $roundRepository,
        private readonly ClubProposalRepositoryInterface $proposalRepository,
        private readonly ClubVoteRepositoryInterface     $voteRepository,
        private readonly ClubRoundProgress               $progreso,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'VoteClubProposal'; }

    protected function doExecute($command): array
    {
        if (!$command instanceof VoteClubProposalCommand) {
            throw new InvalidArgumentException('Command must be an instance of VoteClubProposalCommand');
        }

        if ($this->clubRepository->findById($command->clubId) === null) {
            throw new RuntimeException('Club not found');
        }

        if (!$this->memberRepository->isMember($command->clubId, $command->userId)) {
            throw new DomainException('You are not a member of this club');
        }

        $ronda = $this->roundRepository->findOpen($command->clubId);
        if ($ronda === null || !$ronda->isVoting()) {
            throw new ClubRoundConflictException('This round is not open for voting');
        }

        $rondaId   = (int) $ronda->getId();
        $propuesta = $this->proposalRepository->findById($command->proposalId);

        // La propuesta tiene que ser de ESTA ronda: sin la comprobación se
        // podría votar la de otro club pasando su id, y el recuento sumaría
        // votos que no son de nadie de aquí.
        if ($propuesta === null || $propuesta->getRoundId() !== $rondaId) {
            throw new RuntimeException('That proposal is not part of this round');
        }

        // Y tiene que seguir viva: en el desempate solo se vota a las que
        // empataron. Las demás quedaron eliminadas y votarlas resucitaría una
        // opción que el club ya descartó.
        $vivas = $this->progreso->eligibleProposalIds($ronda);
        if ($vivas !== null && !in_array($command->proposalId, $vivas, true)) {
            throw new RuntimeException('That proposal was eliminated in the tie-break');
        }

        $this->voteRepository->cast($rondaId, $ronda->getBallot(), $command->userId, $command->proposalId);

        return [
            'roundId'    => $rondaId,
            'ballot'     => $ronda->getBallot(),
            'proposalId' => $command->proposalId,
        ];
    }
}
