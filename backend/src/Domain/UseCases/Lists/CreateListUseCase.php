<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Commands\CreateListCommand;
use App\Domain\Model\MediaList;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * El único use case de listas que NO consulta `ListAccess`: no hay lista
 * todavía sobre la que preguntar nada, y el dueño es quien la crea.
 */
class CreateListUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MediaListRepositoryInterface $listRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'CreateList'; }

    protected function doExecute($command): MediaList
    {
        if (!$command instanceof CreateListCommand) {
            throw new InvalidArgumentException('Command must be an instance of CreateListCommand');
        }

        return $this->listRepository->save(new MediaList(
            id:          null,
            ownerId:     $command->ownerId,
            name:        $command->name,
            description: $command->description,
            visibility:  $command->visibility
        ));
    }
}
