<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Videos;

use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use Psr\Log\LoggerInterface;

class GetVideoAllowedStatusesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): array
    {
        return $this->videoRepository->fetchAllowedStatuses();
    }

    protected function getLogContext(): string
    {
        return 'GetVideoAllowedStatusesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Video allowed statuses retrieved successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to retrieve video allowed statuses';
    }
}
