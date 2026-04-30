<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Games;

use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use Psr\Log\LoggerInterface;

class GetGameAllowedStatusesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly GameRepositoryInterface $gameRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): array
    {
        return $this->gameRepository->fetchAllowedStatuses();
    }

    protected function getLogContext(): string
    {
        return 'GetGameAllowedStatusesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Game allowed statuses retrieved successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to retrieve game allowed statuses';
    }
}
