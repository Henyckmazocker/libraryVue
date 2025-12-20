<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetAllowedStatusesQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetMovieAllowedStatusesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MovieRepositoryInterface $movieRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with GetAllowedStatusesQuery
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof GetAllowedStatusesQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetAllowedStatusesQuery');
        }

        if ($command->entityType !== 'movie') {
            throw new InvalidArgumentException('This use case only handles movie statuses');
        }

        return $this->movieRepository->fetchAllowedStatuses();
    }

    protected function getLogContext(): string
    {
        return 'GetMovieAllowedStatusesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Movie allowed statuses retrieved successfully';
    }
}
