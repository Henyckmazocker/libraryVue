<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Commands\ResolveRecommendationCommand;
use App\Domain\Model\Recommendation;
use App\Domain\Repository\Social\RecommendationRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ResolveRecommendationUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly RecommendationRepositoryInterface $recommendationRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'ResolveRecommendation'; }

    protected function doExecute($command): Recommendation
    {
        if (!$command instanceof ResolveRecommendationCommand) {
            throw new InvalidArgumentException('Command must be an instance of ResolveRecommendationCommand');
        }

        $recomendacion = $this->recommendationRepository->findById($command->recommendationId);
        if ($recomendacion === null) {
            throw new RuntimeException('Recommendation not found');
        }

        // Ajena: se distingue de «no existe» a propósito, y el controller lo
        // traduce a 403. Decir «no existe» de la de otro sería más discreto,
        // pero también le escondería al usuario un fallo de su propia sesión.
        if ($recomendacion->getRecipientId() !== $command->userId) {
            throw new DomainException('This recommendation is not yours');
        }

        // `resolve()` ya rechaza pasar de resuelta a resuelta; se traduce a un
        // mensaje en vez de dejar salir la LogicException.
        if (!$recomendacion->isPending()) {
            throw new RuntimeException('This recommendation was already resolved');
        }

        $recomendacion->resolve($command->resolution);
        $this->recommendationRepository->update($recomendacion);

        return $recomendacion;
    }
}
