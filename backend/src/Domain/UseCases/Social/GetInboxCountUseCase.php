<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Queries\GetInboxCountQuery;
use App\Domain\Model\Recommendation;
use App\Domain\Repository\Social\RecommendationRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Deliberadamente pobre: devuelve un número y nada más.
 *
 * Es la que pide la campanita en cada navegación, así que es la acción más
 * llamada de la app: un `SELECT COUNT(*)` que sale entero de `idx_inbox`.
 */
class GetInboxCountUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly RecommendationRepositoryInterface $recommendationRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetInboxCount'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetInboxCountQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetInboxCountQuery');
        }

        return [
            'pending' => $this->recommendationRepository->countForRecipient(
                $query->userId,
                Recommendation::STATUS_PENDING
            ),
        ];
    }
}
