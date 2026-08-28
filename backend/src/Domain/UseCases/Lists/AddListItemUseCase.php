<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Commands\AddListItemCommand;
use App\Domain\Model\MediaListItem;
use App\Domain\Repository\MediaList\MediaListItemRepositoryInterface;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\Services\ListAccess;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class AddListItemUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MediaListRepositoryInterface     $listRepository,
        private readonly MediaListItemRepositoryInterface $itemRepository,
        private readonly ListAccess                       $access,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'AddListItem'; }

    protected function doExecute($command): MediaListItem
    {
        if (!$command instanceof AddListItemCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddListItemCommand');
        }

        $lista = $this->listRepository->findById($command->listId);
        if ($lista === null) {
            throw new RuntimeException('List not found');
        }

        // Aquí sí manda `canEdit`: el contenido es lo que un colaborador
        // colabora. Renombrar o borrar la lista sigue siendo del dueño.
        if (!$this->access->canEdit($lista, $command->userId)) {
            throw new DomainException('You cannot add items to this list');
        }

        // El UNIQUE de la tabla ya lo impide; esto es lo que lo convierte en un
        // 409 legible en vez de un error de clave duplicada.
        if ($this->itemRepository->exists($command->listId, $command->entityType, $command->entityId)) {
            throw new RuntimeException('This item is already in the list');
        }

        return $this->itemRepository->add(new MediaListItem(
            id:          null,
            listId:      $command->listId,
            entityType:  $command->entityType,
            entityId:    $command->entityId,
            addedBy:     $command->userId,
            entityTitle: $command->entityTitle,
            entityCover: $command->entityCover
        ));
    }
}
