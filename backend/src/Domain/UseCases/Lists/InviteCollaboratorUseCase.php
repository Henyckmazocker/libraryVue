<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Commands\InviteCollaboratorCommand;
use App\Domain\Model\Recommendation;
use App\Domain\Repository\MediaList\MediaListCollaboratorRepositoryInterface;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\Social\RecommendationRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Invitar a alguien a colaborar en una lista.
 *
 * La invitación **no da acceso**: crea una fila pendiente en el buzón, y el
 * acceso solo aparece cuando la otra persona acepta. Meter al colaborador aquí
 * mismo sería añadir a alguien a tu lista sin preguntarle.
 */
class InviteCollaboratorUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MediaListRepositoryInterface             $listRepository,
        private readonly MediaListCollaboratorRepositoryInterface $collaboratorRepository,
        private readonly FriendshipRepositoryInterface            $friendshipRepository,
        private readonly RecommendationRepositoryInterface        $recommendationRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'InviteCollaborator'; }

    protected function doExecute($command): Recommendation
    {
        if (!$command instanceof InviteCollaboratorCommand) {
            throw new InvalidArgumentException('Command must be an instance of InviteCollaboratorCommand');
        }

        $lista = $this->listRepository->findById($command->listId);
        if ($lista === null) {
            throw new RuntimeException('List not found');
        }

        // Invitar es del DUEÑO, no de `canEdit`: un colaborador que pudiera
        // invitar decidiría por el dueño quién más entra en su lista.
        if (!$lista->isOwnedBy($command->userId)) {
            throw new DomainException('Only the owner can invite collaborators');
        }

        // La misma regla que `send_recommendation`: solo amigos aceptados. Es la
        // única relación que el proyecto entiende.
        $amistad = $this->friendshipRepository->findByUsers($command->userId, $command->inviteeId);
        if ($amistad === null || !$amistad->isAccepted()) {
            throw new RuntimeException('You can only invite your friends to collaborate');
        }

        if ($this->collaboratorRepository->isCollaborator($command->listId, $command->inviteeId)) {
            throw new RuntimeException('This person already collaborates on this list');
        }

        // El UNIQUE del buzón ya lo impide; esto lo convierte en un mensaje
        // legible en vez de un 500 por clave duplicada.
        if ($this->recommendationRepository->existsBetween(
            $command->userId,
            $command->inviteeId,
            Recommendation::ENTITY_LIST,
            (string) $command->listId
        )) {
            throw new RuntimeException('You already invited this person to this list');
        }

        // Entra por el mismo buzón que las recomendaciones: `entity_id` es el id
        // de la lista y `entity_title` su nombre, igual que se copia el título
        // de una película.
        return $this->recommendationRepository->save(new Recommendation(
            id:          null,
            senderId:    $command->userId,
            recipientId: $command->inviteeId,
            entityType:  Recommendation::ENTITY_LIST,
            entityId:    (string) $command->listId,
            entityTitle: $lista->getName()
        ));
    }
}
