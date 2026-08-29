<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Commands\LeaveClubCommand;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubProposalRepositoryInterface;
use App\Domain\Repository\Club\ClubVoteRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Salir del club, que es el ÚNICO control de privacidad que hay aquí: entrar es
 * consentir que los miembros vean tu progreso y tus notas públicas sobre el
 * ítem activo, y `user_privacy_settings` no gobierna nada dentro de un club.
 *
 * **Salir no borra tus notas ni toca tu biblioteca**: son tuyas y viven donde
 * vivían. Solo dejas de aparecer en la pantalla del club.
 *
 * El dueño no puede salir: dejaría un club sin nadie que pueda invitar ni
 * elegir ítem. Borra el club, que es otra acción y se lleva `club_member` y
 * `club_pick` por CASCADE.
 *
 * **Lo que sí hay que borrar a mano son la propuesta y el voto de la ronda en
 * curso.** `club_member` no es clave ajena ni de `club_proposal` ni de
 * `club_vote`, así que salir no los arrastra por CASCADE, y sin esto «han
 * propuesto todos» y «han votado todos» comparan miembros actuales contra
 * participaciones de gente que ya no está: las fases no cerrarían nunca. Solo
 * las de rondas **abiertas** — las cerradas son historia y de ellas sale el
 * ganador anterior de la rotación.
 */
class LeaveClubUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface       $clubRepository,
        private readonly ClubMemberRepositoryInterface $memberRepository,
        private readonly ClubProposalRepositoryInterface $proposalRepository,
        private readonly ClubVoteRepositoryInterface   $voteRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'LeaveClub'; }

    protected function doExecute($command): array
    {
        if (!$command instanceof LeaveClubCommand) {
            throw new InvalidArgumentException('Command must be an instance of LeaveClubCommand');
        }

        $club = $this->clubRepository->findById($command->clubId);
        if ($club === null) {
            throw new RuntimeException('Club not found');
        }

        if (!$this->memberRepository->isMember($command->clubId, $command->userId)) {
            throw new DomainException('You are not a member of this club');
        }

        if ($club->isOwnedBy($command->userId)) {
            throw new DomainException('The owner cannot leave their own club; delete it instead');
        }

        $this->memberRepository->remove($command->clubId, $command->userId);
        $this->proposalRepository->deleteByUser($command->clubId, $command->userId);
        $this->voteRepository->deleteByUser($command->clubId, $command->userId);

        return ['clubId' => $command->clubId];
    }
}
