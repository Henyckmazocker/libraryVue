<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Queries\GetInboxQuery;
use App\Domain\Repository\Social\RecommendationRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class GetInboxUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly RecommendationRepositoryInterface $recommendationRepository,
        private readonly UserRepositoryInterface           $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetInbox'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetInboxQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetInboxQuery');
        }

        $recomendaciones = $this->recommendationRepository->findForRecipient(
            $query->userId,
            $query->status,
            $query->limit,
            $query->offset
        );

        // Quién te la manda se resuelve aquí y no se copia en la fila: el nombre
        // de un usuario cambia, el título de una película no.
        $remitentes = [];
        $lista = [];

        foreach ($recomendaciones as $recomendacion) {
            $senderId = $recomendacion->getSenderId();

            if (!array_key_exists($senderId, $remitentes)) {
                $usuario = $this->userRepository->findById($senderId);
                $remitentes[$senderId] = $usuario === null ? null : [
                    'id'       => $senderId,
                    'username' => $usuario->getUsername() ?? $usuario->getName(),
                    'name'     => $usuario->getName(),
                    'picture'  => $usuario->getPicture(),
                ];
            }

            $lista[] = $recomendacion->toArray() + ['sender' => $remitentes[$senderId]];
        }

        return [
            'recommendations' => $lista,
            'total'           => $this->recommendationRepository->countForRecipient($query->userId, $query->status),
        ];
    }
}
