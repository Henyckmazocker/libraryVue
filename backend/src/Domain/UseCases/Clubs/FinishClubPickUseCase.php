<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Commands\FinishClubPickCommand;
use App\Domain\Repository\Club\ClubPickRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Dar por terminado el ítem activo.
 *
 * **No es la excepción: es la vía habitual.** El cierre automático de
 * `ClubCompletion` exige que TODOS los miembros lo hayan completado, y basta
 * uno que no lo tenga en su biblioteca —o que acabe de entrar al club— para que
 * no llegue nunca. Este botón es el mecanismo principal y así debe pintarlo la
 * interfaz.
 *
 * Terminar **no borra nada**: el `club_pick` se queda con su `finished_at` y
 * pasa a ser historial. Las notas y la biblioteca de cada uno no se tocan.
 */
class FinishClubPickUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface     $clubRepository,
        private readonly ClubPickRepositoryInterface $pickRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'FinishClubPick'; }

    protected function doExecute($command): array
    {
        if (!$command instanceof FinishClubPickCommand) {
            throw new InvalidArgumentException('Command must be an instance of FinishClubPickCommand');
        }

        $club = $this->clubRepository->findById($command->clubId);
        if ($club === null) {
            throw new RuntimeException('Club not found');
        }

        if (!$club->isOwnedBy($command->userId)) {
            throw new DomainException('Only the owner can finish this club\'s item');
        }

        $activo = $this->pickRepository->findActive($command->clubId);
        if ($activo === null) {
            throw new RuntimeException('This club has no active item');
        }

        // El `false` significa que el cierre automático se le adelantó entre la
        // lectura y el UPDATE. No es un error: el ítem está cerrado, que es lo
        // que se pedía.
        $this->pickRepository->finish((int) $activo->getId());

        return ['pickId' => $activo->getId()];
    }
}
