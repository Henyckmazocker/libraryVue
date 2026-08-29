<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Commands\AdvanceClubRoundCommand;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\Repository\Club\ClubRoundRepositoryInterface;
use App\Domain\Services\ClubRoundProgress;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * La primera válvula del dueño: abrir el voto con las propuestas que haya.
 *
 * Existe porque **una fase de propuestas puede no cerrarse nunca**. Si un
 * miembro no propone jamás, «han propuesto todos» no se cumple, y sin cron
 * (`Infrastructure/Http/PostResponse.php:12`) no hay nada que lo destrabe con
 * el tiempo. Se descartó el plazo por reloj a propósito: solo se evaluaría
 * cuando alguien mirase el club, y un «cierra en 48 h» que en realidad cierra
 * cuando entra alguien es peor que no tenerlo.
 *
 * Lo que **no** puede hacer es abrir un voto vacío: sin una sola propuesta la
 * ronda quedaría clavada un escalón más allá, que es peor. Esa guarda está en
 * `ClubRoundResolver::canOpenVote`.
 */
class OpenClubVoteUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface      $clubRepository,
        private readonly ClubRoundRepositoryInterface $roundRepository,
        private readonly ClubRoundProgress            $progreso,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'OpenClubVote'; }

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
            throw new DomainException('Only the owner can open the vote');
        }

        $ronda = $this->roundRepository->findOpen($command->clubId);
        if ($ronda === null || !$ronda->isProposing()) {
            throw new ClubRoundConflictException('This round is not taking proposals right now');
        }

        $avanzada = $this->progreso->advance($club, forzadoPorElDueno: true);

        // Si sigue proponiendo es que no había ni una propuesta: forzar no
        // puede abrir un voto vacío.
        if ($avanzada->isProposing()) {
            throw new ClubRoundConflictException('There is nothing to vote on yet');
        }

        return ['roundId' => $avanzada->getId(), 'phase' => $avanzada->getPhase()];
    }
}
