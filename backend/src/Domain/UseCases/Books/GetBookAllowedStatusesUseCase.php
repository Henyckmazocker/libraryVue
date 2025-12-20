<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetAllowedStatusesQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetBookAllowedStatusesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository,
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

        if ($command->entityType !== 'book') {
            throw new InvalidArgumentException('This use case only handles book statuses');
        }

        return $this->bookRepository->fetchAllowedStatuses();
    }

    protected function getLogContext(): string
    {
        return 'GetBookAllowedStatusesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Book allowed statuses retrieved successfully';
    }
}
