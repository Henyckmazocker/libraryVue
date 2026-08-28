<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Commands\UpdateListCommand;
use App\Domain\Model\MediaList;
use App\Domain\Repository\MediaList\MediaListCollaboratorRepositoryInterface;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\Services\ListAccess;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class UpdateListUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MediaListRepositoryInterface             $listRepository,
        private readonly MediaListCollaboratorRepositoryInterface $collaboratorRepository,
        private readonly ListAccess                               $access,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'UpdateList'; }

    protected function doExecute($command): MediaList
    {
        if (!$command instanceof UpdateListCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateListCommand');
        }

        $lista = $this->listRepository->findById($command->listId);
        if ($lista === null) {
            throw new RuntimeException('List not found');
        }

        // Renombrar y cambiar la visibilidad son cosa del DUEÑO, no de un
        // colaborador: `canEdit` le deja tocar el contenido, no la lista misma.
        // Un colaborador que pudiera ponerla en `private` dejaría al dueño
        // fuera de su propia lista.
        if (!$lista->isOwnedBy($command->userId)) {
            throw new DomainException('Only the owner can modify this list');
        }

        $actualizada = new MediaList(
            id:          $lista->getId(),
            ownerId:     $lista->getOwnerId(),
            name:        $command->name ?? $lista->getName(),
            description: $command->descriptionProvided ? $command->description : $lista->getDescription(),
            visibility:  $command->visibility ?? $lista->getVisibility(),
            createdAt:   $lista->getCreatedAt(),
            updatedAt:   $lista->getUpdatedAt()
        );

        $this->listRepository->update($actualizada);

        // Bajar de `collaborative` deja colaboradores huérfanos: seguirían
        // pudiendo editar —`canEdit` consulta la tabla en las TRES
        // visibilidades— sin que la interfaz lo enseñe en ninguna parte. Se
        // borran, y el diálogo lo avisa antes de guardar.
        if ($lista->isCollaborative() && !$actualizada->isCollaborative()) {
            $this->collaboratorRepository->removeAll($command->listId);
        }

        return $actualizada;
    }
}
