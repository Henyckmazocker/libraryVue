<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Commands\SetClubPickCommand;
use App\Domain\Model\ClubPick;
use App\Domain\Repository\Club\ClubPickRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Elegir el ítem que el club va a consumir.
 *
 * **Es del dueño**, como invitar y como cerrar, y por el mismo criterio: son
 * las acciones que deciden por todos los demás. Ser miembro no basta.
 *
 * Cuando llegue [[Plan - Votación del Club]] este use case NO desaparece: gana
 * un segundo llamante —el cierre de ronda, que crea el `club_pick` del
 * ganador— y se queda además como la vía de escape del dueño para el club
 * atascado. Por eso la comprobación de dueño vive en el use case y no en una
 * guarda del controller.
 */
class SetClubPickUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface     $clubRepository,
        private readonly ClubPickRepositoryInterface $pickRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'SetClubPick'; }

    protected function doExecute($command): ClubPick
    {
        if (!$command instanceof SetClubPickCommand) {
            throw new InvalidArgumentException('Command must be an instance of SetClubPickCommand');
        }

        $club = $this->clubRepository->findById($command->clubId);
        if ($club === null) {
            throw new RuntimeException('Club not found');
        }

        if (!$club->isOwnedBy($command->userId)) {
            throw new DomainException('Only the owner can choose what this club reads');
        }

        // La regla «solo un activo por club» vive AQUÍ y no en el esquema:
        // MySQL no tiene índices parciales, y un `UNIQUE (club_id,
        // finished_at)` solo funcionaría con un valor centinela en lugar de
        // NULL, que es peor que la comprobación.
        if ($this->pickRepository->findActive($command->clubId) !== null) {
            throw new ClubPickConflictException('This club already has an active item');
        }

        return $this->pickRepository->save(new ClubPick(
            id:          null,
            clubId:      $command->clubId,
            entityType:  $command->entityType,
            entityId:    $command->entityId,
            entityTitle: $command->entityTitle,
            entityCover: $command->entityCover
        ));
    }
}
