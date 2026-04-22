<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use Psr\Log\LoggerInterface;

class GetAlbumAllowedStatusesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly AlbumRepositoryInterface $albumRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): array
    {
        return $this->albumRepository->fetchAllowedStatuses();
    }

    protected function getLogContext(): string
    {
        return 'GetAlbumAllowedStatusesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Album allowed statuses retrieved successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to retrieve album allowed statuses';
    }
}
