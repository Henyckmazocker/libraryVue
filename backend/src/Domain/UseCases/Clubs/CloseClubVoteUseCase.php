<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Commands\AdvanceClubRoundCommand;
use App\Domain\Repository\Club\ClubPickRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\Repository\Club\ClubRoundRepositoryInterface;
use App\Domain\Services\ClubRoundProgress;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * La segunda válvula del dueño: cerrar la votación con los votos que haya.
 *
 * La gemela de `OpenClubVoteUseCase`, y por el mismo motivo: si un miembro no
 * vota nunca, «han votado todos» no se cumple jamás. Lo que no puede es cerrar
 * sin un solo voto —no habría ganador que escribir—, y esa guarda está en
 * `ClubRoundResolver::canCloseVote`.
 *
 * **Forzar el cierre no salta el desempate.** Si los votos que hay empatan y es
 * el primer recuento, la ronda pasa a `ballot = 2` y sigue votándose entre las
 * empatadas: la válvula destraba la espera, no la regla. Por eso la respuesta
 * dice en qué fase quedó y si hay `pickId`.
 */
class CloseClubVoteUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface      $clubRepository,
        private readonly ClubRoundRepositoryInterface $roundRepository,
        private readonly ClubPickRepositoryInterface  $pickRepository,
        private readonly ClubRoundProgress            $progreso,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'CloseClubVote'; }

    protected function doExecute($command): array
    {
        if (!$command instanceof AdvanceClubRoundCommand) {
            throw new InvalidArgumentException('Command must be an instance of AdvanceClubRoundCommand');
        }

        $club = $this->clubRepository->findById($command->clubId);
        if ($club === null) {
            throw new RuntimeException('Club not found');
        }

        if (!$club->isOwnedBy($command->userId)) {
            throw new DomainException('Only the owner can close the vote');
        }

        $ronda = $this->roundRepository->findOpen($command->clubId);
        if ($ronda === null || !$ronda->isVoting()) {
            throw new ClubRoundConflictException('This round is not open for voting');
        }

        $antes    = $ronda->getBallot();
        $avanzada = $this->progreso->advance($club, forzadoPorElDueno: true);

        // Ni cerró ni pasó a desempate: no había un solo voto que contar.
        if ($avanzada->isVoting() && $avanzada->getBallot() === $antes) {
            throw new InvalidArgumentException('Nobody has voted yet');
        }

        return [
            'roundId' => $avanzada->getId(),
            'phase'   => $avanzada->getPhase(),
            'ballot'  => $avanzada->getBallot(),
            'pickId'  => $this->pickRepository->findActive($command->clubId)?->getId(),
        ];
    }
}
