<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Lists;

use App\Domain\DTO\Commands\AcceptCollaborationCommand;
use App\Domain\Model\Recommendation;
use App\Domain\Repository\MediaList\MediaListCollaboratorRepositoryInterface;
use App\Domain\Repository\MediaList\MediaListRepositoryInterface;
use App\Domain\Repository\Social\RecommendationRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Aceptar una invitación: da de alta al colaborador y marca la fila resuelta.
 *
 * **Las dos cosas, y en este orden.** Si se marcara resuelta primero y el alta
 * fallara, la invitación desaparecería del buzón sin haber dado acceso: no
 * habría manera de recuperarla, porque el UNIQUE impide volver a mandarla. Al
 * revés, un fallo deja al colaborador dentro con la invitación aún pendiente,
 * que se arregla solo la próxima vez —`add()` es idempotente—.
 */
class AcceptCollaborationUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly RecommendationRepositoryInterface        $recommendationRepository,
        private readonly MediaListRepositoryInterface             $listRepository,
        private readonly MediaListCollaboratorRepositoryInterface $collaboratorRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'AcceptCollaboration'; }

    protected function doExecute($command): array
    {
        if (!$command instanceof AcceptCollaborationCommand) {
            throw new InvalidArgumentException('Command must be an instance of AcceptCollaborationCommand');
        }

        $invitacion = $this->recommendationRepository->findById($command->recommendationId);
        if ($invitacion === null) {
            throw new RuntimeException('Invitation not found');
        }

        // Ajena: 403, y se distingue de «no existe» a propósito, igual que en
        // `ResolveRecommendationUseCase`.
        if ($invitacion->getRecipientId() !== $command->userId) {
            throw new DomainException('This invitation is not yours');
        }

        // Una recomendación de un ítem no se acepta por aquí: si se dejara,
        // `entity_id` sería un ISBN y se intentaría usar como id de lista.
        if (!$invitacion->isListInvitation()) {
            throw new RuntimeException('That is not a list invitation');
        }

        if (!$invitacion->isPending()) {
            throw new RuntimeException('This invitation was already resolved');
        }

        $listId = (int) $invitacion->getEntityId();
        $lista  = $this->listRepository->findById($listId);
        if ($lista === null) {
            // La lista se borró entre la invitación y la respuesta. Se resuelve
            // la fila para que no se quede clavada en el buzón para siempre.
            $invitacion->resolve(Recommendation::STATUS_DISMISSED);
            $this->recommendationRepository->update($invitacion);

            throw new RuntimeException('That list no longer exists');
        }

        $this->collaboratorRepository->add($listId, $command->userId);

        $invitacion->resolve(Recommendation::STATUS_ADDED);
        $this->recommendationRepository->update($invitacion);

        return ['listId' => $listId, 'name' => $lista->getName()];
    }
}
