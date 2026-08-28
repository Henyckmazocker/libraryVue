<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\DTO\Commands\AcceptCollaborationCommand;
use App\Domain\DTO\Commands\AddListItemCommand;
use App\Domain\DTO\Commands\InviteCollaboratorCommand;
use App\Domain\DTO\Commands\RemoveCollaboratorCommand;
use App\Domain\DTO\Commands\CreateListCommand;
use App\Domain\DTO\Commands\DeleteListCommand;
use App\Domain\DTO\Commands\RemoveListItemCommand;
use App\Domain\DTO\Commands\UpdateListCommand;
use App\Domain\DTO\Queries\GetListQuery;
use App\Domain\DTO\Queries\GetMyListsQuery;
use App\Domain\DTO\Queries\GetUserListsQuery;
use App\Domain\UseCases\Lists\AcceptCollaborationUseCase;
use App\Domain\UseCases\Lists\AddListItemUseCase;
use App\Domain\UseCases\Lists\InviteCollaboratorUseCase;
use App\Domain\UseCases\Lists\RemoveCollaboratorUseCase;
use App\Domain\UseCases\Lists\CreateListUseCase;
use App\Domain\UseCases\Lists\DeleteListUseCase;
use App\Domain\UseCases\Lists\GetListUseCase;
use App\Domain\UseCases\Lists\GetMyListsUseCase;
use App\Domain\UseCases\Lists\GetUserListsUseCase;
use App\Domain\UseCases\Lists\RemoveListItemUseCase;
use App\Domain\UseCases\Lists\UpdateListUseCase;
use DomainException;
use RuntimeException;

/**
 * Las listas tienen controller propio y no entran en `SocialController`: son
 * siete acciones hoy y once cuando llegue la colaboración, y una lista privada
 * no es una función social — funciona sin amigos y sin bandeja.
 *
 * `DomainException` significa **403** en todo este controller, igual que en
 * `SocialController::resolveRecommendation`: es «existe pero no es tuya», y se
 * distingue de `RuntimeException` («no existe», 404) a propósito.
 */
class ListController extends BaseController
{
    public function __construct(
        private readonly CreateListUseCase     $createListUseCase,
        private readonly UpdateListUseCase     $updateListUseCase,
        private readonly DeleteListUseCase     $deleteListUseCase,
        private readonly GetMyListsUseCase     $getMyListsUseCase,
        private readonly GetListUseCase        $getListUseCase,
        private readonly AddListItemUseCase    $addListItemUseCase,
        private readonly RemoveListItemUseCase $removeListItemUseCase,
        private readonly GetUserListsUseCase   $getUserListsUseCase,
        private readonly InviteCollaboratorUseCase  $inviteCollaboratorUseCase,
        private readonly AcceptCollaborationUseCase $acceptCollaborationUseCase,
        private readonly RemoveCollaboratorUseCase  $removeCollaboratorUseCase
    ) {}

    public function createList(CreateListCommand $command): array
    {
        $lista = $this->createListUseCase->execute($command);

        return $this->successResponse('List created', ['listId' => $lista->getId()], 201);
    }

    public function updateList(UpdateListCommand $command): array
    {
        try {
            $lista = $this->updateListUseCase->execute($command);
            return $this->successResponse('List updated', $lista->toArray());
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function deleteList(DeleteListCommand $command): array
    {
        try {
            $this->deleteListUseCase->execute($command);
            return $this->successResponse('List deleted');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function getMyLists(GetMyListsQuery $query): array
    {
        return $this->successResponse('Lists retrieved', $this->getMyListsUseCase->execute($query));
    }

    /**
     * Las listas PÚBLICAS de otro usuario. Un usuario que no existe es un 404;
     * no hay 403 posible porque no se pregunta por ninguna lista concreta —lo
     * que no es público sencillamente no sale de la consulta—.
     */
    public function getUserLists(GetUserListsQuery $query): array
    {
        try {
            return $this->successResponse('Lists retrieved', $this->getUserListsUseCase->execute($query));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function getList(GetListQuery $query): array
    {
        try {
            return $this->successResponse('List retrieved', $this->getListUseCase->execute($query));
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function addListItem(AddListItemCommand $command): array
    {
        try {
            $item = $this->addListItemUseCase->execute($command);
            return $this->successResponse('Item added to the list', $item->toArray(), 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            // «Ya está en la lista» es un 409, no un 404: la lista existe y el
            // ítem también. Los separa el mensaje, no la clase de excepción.
            $codigo = str_contains($e->getMessage(), 'already in the list') ? 409 : 404;
            return $this->errorResponse($e->getMessage(), $codigo);
        }
    }

    public function inviteCollaborator(InviteCollaboratorCommand $command): array
    {
        try {
            $invitacion = $this->inviteCollaboratorUseCase->execute($command);
            return $this->successResponse('Invitation sent', [
                'recommendationId' => $invitacion->getId(),
            ], 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            // «No sois amigos» es 400 y «ya está invitado / ya colabora» es 409:
            // los separa el estado, no la clase de excepción. Mismo criterio que
            // `SocialController::sendRecommendation`.
            $yaEsta = str_contains($e->getMessage(), 'already');
            $codigo = str_contains($e->getMessage(), 'not found') ? 404 : ($yaEsta ? 409 : 400);
            return $this->errorResponse($e->getMessage(), $codigo);
        }
    }

    public function acceptCollaboration(AcceptCollaborationCommand $command): array
    {
        try {
            $resultado = $this->acceptCollaborationUseCase->execute($command);
            return $this->successResponse('Invitation accepted', $resultado);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function removeCollaborator(RemoveCollaboratorCommand $command): array
    {
        try {
            $this->removeCollaboratorUseCase->execute($command);
            return $this->successResponse('Collaborator removed');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function removeListItem(RemoveListItemCommand $command): array
    {
        try {
            $this->removeListItemUseCase->execute($command);
            return $this->successResponse('Item removed from the list');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
