<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Queries\GetListQuery;
use App\Domain\Repository\MediaList\MediaListCollaboratorRepositoryInterface;
use App\Domain\Repository\MediaList\MediaListItemRepositoryInterface;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\Services\ListAccess;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class GetListUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MediaListRepositoryInterface             $listRepository,
        private readonly MediaListItemRepositoryInterface         $itemRepository,
        private readonly MediaListCollaboratorRepositoryInterface $collaboratorRepository,
        private readonly ListAccess                               $access,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetList'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetListQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetListQuery');
        }

        $lista = $this->listRepository->findById($query->listId);
        if ($lista === null) {
            throw new RuntimeException('List not found');
        }

        // La única copia de la regla. El controller lo traduce a 403, igual que
        // hace `ResolveRecommendationUseCase` con la recomendación ajena.
        if (!$this->access->canView($lista, $query->userId)) {
            throw new DomainException('You cannot view this list');
        }

        return [
            'list'  => $lista->toArray() + [
                // Lo que la interfaz necesita para decidir si pinta los botones
                // de editar. Se manda resuelto y no se recalcula en el cliente:
                // la regla vive en el servidor y solo ahí.
                'can_edit' => $this->access->canEdit($lista, $query->userId),
                'is_owner' => $lista->isOwnedBy($query->userId),
            ],
            'items' => array_map(
                static fn ($item) => $item->toArray(),
                $this->itemRepository->findByList($query->listId)
            ),
            // Quién colabora solo se le dice a quien ya puede ver la lista, que
            // es todo el que llega hasta aquí: `canView` ya decidió arriba.
            'collaborators' => $this->collaboratorRepository->findByList($query->listId),
        ];
    }
}
