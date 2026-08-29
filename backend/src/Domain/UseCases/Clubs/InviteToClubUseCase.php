<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Commands\InviteToClubCommand;
use App\Domain\Model\Recommendation;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\Social\RecommendationRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Invitar a alguien a entrar en un club.
 *
 * La invitación **no da acceso**: crea una fila pendiente en el buzón, y la
 * pertenencia solo aparece cuando la otra persona acepta. Meterlo aquí mismo
 * sería hacerle ver su progreso a los demás sin preguntarle — y entrar en un
 * club ES el consentimiento de que sus miembros vean tu progreso y tus notas
 * públicas sobre el ítem activo.
 */
class InviteToClubUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface           $clubRepository,
        private readonly ClubMemberRepositoryInterface     $memberRepository,
        private readonly FriendshipRepositoryInterface     $friendshipRepository,
        private readonly RecommendationRepositoryInterface $recommendationRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'InviteToClub'; }

    protected function doExecute($command): Recommendation
    {
        if (!$command instanceof InviteToClubCommand) {
            throw new InvalidArgumentException('Command must be an instance of InviteToClubCommand');
        }

        $club = $this->clubRepository->findById($command->clubId);
        if ($club === null) {
            throw new RuntimeException('Club not found');
        }

        // Invitar es del DUEÑO, no de cualquier miembro: si invitara cualquiera,
        // un miembro decidiría por el dueño ante quién se expone el progreso de
        // todos los demás.
        if (!$club->isOwnedBy($command->userId)) {
            throw new DomainException('Only the owner can invite people to this club');
        }

        // La misma regla que `send_recommendation` e `invite_collaborator`:
        // solo amigos aceptados. Es la única relación que el proyecto entiende.
        $amistad = $this->friendshipRepository->findByUsers($command->userId, $command->inviteeId);
        if ($amistad === null || !$amistad->isAccepted()) {
            throw new RuntimeException('You can only invite your friends to a club');
        }

        if ($this->memberRepository->isMember($command->clubId, $command->inviteeId)) {
            throw new RuntimeException('This person is already in this club');
        }

        // El UNIQUE del buzón ya lo impide; esto lo convierte en un mensaje
        // legible en vez de un 500 por clave duplicada.
        if ($this->recommendationRepository->existsBetween(
            $command->userId,
            $command->inviteeId,
            Recommendation::ENTITY_CLUB,
            (string) $command->clubId
        )) {
            throw new RuntimeException('You already invited this person to this club');
        }

        // Entra por el mismo buzón que las recomendaciones y las invitaciones a
        // colaborar: `entity_id` es el id del club y `entity_title` su nombre.
        return $this->recommendationRepository->save(new Recommendation(
            id:          null,
            senderId:    $command->userId,
            recipientId: $command->inviteeId,
            entityType:  Recommendation::ENTITY_CLUB,
            entityId:    (string) $command->clubId,
            entityTitle: $club->getName()
        ));
    }
}
