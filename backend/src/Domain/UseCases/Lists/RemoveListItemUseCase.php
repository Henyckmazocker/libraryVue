<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Commands\RemoveListItemCommand;
use App\Domain\Repository\MediaList\MediaListItemRepositoryInterface;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\Services\ListAccess;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RemoveListItemUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MediaListRepositoryInterface     $listRepository,
        private readonly MediaListItemRepositoryInterface $itemRepository,
        private readonly ListAccess                       $access,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'RemoveListItem'; }

    protected function doExecute($command): bool
    {
        if (!$command instanceof RemoveListItemCommand) {
            throw new InvalidArgumentException('Command must be an instance of RemoveListItemCommand');
        }

        $lista = $this->listRepository->findById($command->listId);
        if ($lista === null) {
            throw new RuntimeException('List not found');
        }

        if (!$this->access->canEdit($lista, $command->userId)) {
            throw new DomainException('You cannot remove items from this list');
        }

        $item = $this->itemRepository->findById($command->itemId);
        if ($item === null) {
            throw new RuntimeException('Item not found');
        }

        // El ítem tiene que ser DE ESTA lista. Sin esta comprobación, quien
        // pueda editar una lista cualquiera podría borrar un ítem de otra
        // pasando su `itemId`: el permiso se comprobó sobre `listId`, y el
        // borrado iría por `itemId`.
        if ($item->getListId() !== $command->listId) {
            throw new RuntimeException('That item does not belong to this list');
        }

        // Quita la clave de la lista. NO borra el ítem de la biblioteca de
        // nadie: la lista nunca fue su dueña.
        $this->itemRepository->remove($command->itemId);

        return true;
    }
}
