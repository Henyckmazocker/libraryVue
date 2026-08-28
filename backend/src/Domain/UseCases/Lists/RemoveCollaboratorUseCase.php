<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Commands\RemoveCollaboratorCommand;
use App\Domain\Repository\MediaList\MediaListCollaboratorRepositoryInterface;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Sacar a un colaborador, o salirse uno mismo.
 *
 * Las dos cosas son la misma operación y por eso comparten use case: el dueño
 * puede sacar a cualquiera, y cualquiera puede sacarse a sí mismo. Lo que no
 * puede es sacar a un tercero.
 */
class RemoveCollaboratorUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MediaListRepositoryInterface             $listRepository,
        private readonly MediaListCollaboratorRepositoryInterface $collaboratorRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'RemoveCollaborator'; }

    protected function doExecute($command): bool
    {
        if (!$command instanceof RemoveCollaboratorCommand) {
            throw new InvalidArgumentException('Command must be an instance of RemoveCollaboratorCommand');
        }

        $lista = $this->listRepository->findById($command->listId);
        if ($lista === null) {
            throw new RuntimeException('List not found');
        }

        $esElDueno = $lista->isOwnedBy($command->userId);
        $seVaSolo  = $command->collaboratorId === $command->userId;

        if (!$esElDueno && !$seVaSolo) {
            throw new DomainException('Only the owner can remove other collaborators');
        }

        // Quitar a quien no está no es un error: el resultado pedido ya se
        // cumple, y con dos pestañas abiertas la segunda daría un 404 absurdo.
        $this->collaboratorRepository->remove($command->listId, $command->collaboratorId);

        return true;
    }
}
