<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Commands\AcceptClubInvitationCommand;
use App\Domain\Model\Recommendation;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\Repository\Social\RecommendationRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Aceptar una invitación: da de alta al miembro y marca la fila resuelta.
 *
 * **Las dos cosas, y en este orden**, por lo mismo que en
 * `AcceptCollaborationUseCase`. Si se marcara resuelta primero y el alta
 * fallara, la invitación desaparecería del buzón sin haber dado acceso, y no
 * habría manera de recuperarla porque el UNIQUE impide volver a mandarla. Al
 * revés, un fallo deja al miembro dentro con la invitación aún pendiente, que
 * se arregla sola la próxima vez — `add()` es idempotente.
 */
class AcceptClubInvitationUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly RecommendationRepositoryInterface $recommendationRepository,
        private readonly ClubRepositoryInterface           $clubRepository,
        private readonly ClubMemberRepositoryInterface     $memberRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'AcceptClubInvitation'; }

    protected function doExecute($command): array
    {
        if (!$command instanceof AcceptClubInvitationCommand) {
            throw new InvalidArgumentException('Command must be an instance of AcceptClubInvitationCommand');
        }

        $invitacion = $this->recommendationRepository->findById($command->recommendationId);
        if ($invitacion === null) {
            throw new RuntimeException('Invitation not found');
        }

        // Ajena: 403, y se distingue de «no existe» a propósito.
        if ($invitacion->getRecipientId() !== $command->userId) {
            throw new DomainException('This invitation is not yours');
        }

        // Ni una recomendación de un ítem ni una invitación a una lista se
        // aceptan por aquí: si se dejara, `entity_id` sería un ISBN o el id de
        // una lista y se intentaría usar como id de club.
        if (!$invitacion->isClubInvitation()) {
            throw new RuntimeException('That is not a club invitation');
        }

        if (!$invitacion->isPending()) {
            throw new RuntimeException('This invitation was already resolved');
        }

        $clubId = (int) $invitacion->getEntityId();
        $club   = $this->clubRepository->findById($clubId);
        if ($club === null) {
            // El club se borró entre la invitación y la respuesta. Se resuelve
            // la fila para que no se quede clavada en el buzón para siempre.
            $invitacion->resolve(Recommendation::STATUS_DISMISSED);
            $this->recommendationRepository->update($invitacion);

            throw new RuntimeException('That club no longer exists');
        }

        $this->memberRepository->add($clubId, $command->userId);

        $invitacion->resolve(Recommendation::STATUS_ADDED);
        $this->recommendationRepository->update($invitacion);

        return ['clubId' => $clubId, 'name' => $club->getName()];
    }
}
