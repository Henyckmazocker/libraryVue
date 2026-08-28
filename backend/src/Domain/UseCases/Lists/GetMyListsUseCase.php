<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Queries\GetMyListsQuery;
use App\Domain\Repository\MediaList\MediaListItemRepositoryInterface;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Mis listas: las que poseo y aquellas en las que colaboro.
 *
 * Tampoco pasa por `ListAccess`, y a propósito: la pertenencia va en el WHERE
 * de la consulta, no en un filtro posterior. Preguntar lista por lista
 * significaría traerse las de todos los usuarios de la instalación para
 * descartarlas después.
 */
class GetMyListsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MediaListRepositoryInterface     $listRepository,
        private readonly MediaListItemRepositoryInterface $itemRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetMyLists'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetMyListsQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetMyListsQuery');
        }

        $listas = $this->listRepository->findForUser($query->userId);

        // Un solo GROUP BY para todas, no una consulta por tarjeta.
        $conteos = $this->itemRepository->countByLists(
            array_values(array_filter(array_map(
                static fn ($lista) => $lista->getId(),
                $listas
            )))
        );

        return [
            'lists' => array_map(
                static fn ($lista) => $lista->toArray() + [
                    'item_count' => $conteos[$lista->getId()] ?? 0,
                    'is_owner'   => $lista->isOwnedBy($query->userId),
                ],
                $listas
            ),
        ];
    }
}
