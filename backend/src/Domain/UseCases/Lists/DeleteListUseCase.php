<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Commands\DeleteListCommand;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class DeleteListUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MediaListRepositoryInterface $listRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'DeleteList'; }

    protected function doExecute($command): bool
    {
        if (!$command instanceof DeleteListCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteListCommand');
        }

        $lista = $this->listRepository->findById($command->listId);
        if ($lista === null) {
            throw new RuntimeException('List not found');
        }

        // Borrar es del dueño, por el mismo motivo que renombrar.
        if (!$lista->isOwnedBy($command->userId)) {
            throw new DomainException('Only the owner can delete this list');
        }

        // Los ítems y los colaboradores caen por CASCADE. Lo que NO se toca es
        // la biblioteca de nadie: la lista solo guardaba claves de catálogo.
        $this->listRepository->delete($command->listId);

        return true;
    }
}
