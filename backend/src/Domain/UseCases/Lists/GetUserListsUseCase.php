<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Queries\GetUserListsQuery;
use App\Domain\Repository\MediaList\MediaListItemRepositoryInterface;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Las listas públicas de un usuario, que es lo único que se enseña en su perfil.
 *
 * **No pasa por `ListAccess`, y eso no es saltarse la regla.** `ListAccess`
 * contesta «¿puede ESTE usuario ver ESTA lista?», y aquí la pregunta es otra:
 * «¿qué listas de fulano son públicas?». Eso es el filtro de la consulta —
 * `findPublicByOwner`—, no un permiso sobre una lista concreta. Traerse todas
 * las de fulano para descartarlas después en PHP sería leer sus listas privadas
 * en cada visita a su perfil, que es exactamente lo que no puede pasar.
 */
class GetUserListsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface          $userRepository,
        private readonly MediaListRepositoryInterface     $listRepository,
        private readonly MediaListItemRepositoryInterface $itemRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetUserLists'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetUserListsQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetUserListsQuery');
        }

        $user = $this->userRepository->findByUsername($query->username);
        if ($user === null) {
            throw new RuntimeException("User '{$query->username}' not found");
        }

        $listas = $this->listRepository->findPublicByOwner($user->getId());

        // Un solo GROUP BY para todas, como en `get_my_lists`: la tarjeta pinta
        // el contador y pedirlo lista por lista sería el N+1 de siempre.
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
                ],
                $listas
            ),
        ];
    }
}
