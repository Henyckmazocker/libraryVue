<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Commands\SendRecommendationCommand;
use App\Domain\Model\Recommendation;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\Social\RecommendationRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SendRecommendationUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly RecommendationRepositoryInterface $recommendationRepository,
        private readonly FriendshipRepositoryInterface     $friendshipRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'SendRecommendation'; }

    protected function doExecute($command): Recommendation
    {
        if (!$command instanceof SendRecommendationCommand) {
            throw new InvalidArgumentException('Command must be an instance of SendRecommendationCommand');
        }

        // La comprobación de amistad va aquí y no en el controller: es una regla
        // de dominio. Solo se puede recomendar a alguien con una amistad
        // aceptada, que es la única relación que el proyecto entiende.
        $amistad = $this->friendshipRepository->findByUsers($command->senderId, $command->recipientId);
        if ($amistad === null || !$amistad->isAccepted()) {
            throw new RuntimeException('You can only recommend items to your friends');
        }

        // El UNIQUE de la tabla ya lo impide; esto es lo que lo convierte en un
        // mensaje legible en vez de un 500 por clave duplicada.
        if ($this->recommendationRepository->existsBetween(
            $command->senderId,
            $command->recipientId,
            $command->entityType,
            $command->entityId
        )) {
            throw new RuntimeException('You already recommended this item to this friend');
        }

        return $this->recommendationRepository->save(new Recommendation(
            id:          null,
            senderId:    $command->senderId,
            recipientId: $command->recipientId,
            entityType:  $command->entityType,
            entityId:    $command->entityId,
            entityTitle: $command->entityTitle,
            entityCover: $command->entityCover,
            comment:     $command->comment
        ));
    }
}
