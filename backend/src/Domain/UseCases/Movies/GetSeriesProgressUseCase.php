<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\DTO\Queries\GetSeriesProgressQuery;
use App\Domain\Repository\Movie\SeriesSeasonRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class GetSeriesProgressUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SeriesSeasonRepositoryInterface $seriesSeasonRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute(mixed ...$args): array
    {
        $query = $args[0] ?? null;

        if (!$query instanceof GetSeriesProgressQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetSeriesProgressQuery');
        }

        if (empty($query->seriesIsbn)) {
            throw new InvalidArgumentException('Series ISBN cannot be empty');
        }

        return $this->seriesSeasonRepository->getProgress($query->userId, $query->seriesIsbn);
    }

    protected function getLogContext(): string
    {
        return 'GetSeriesProgress';
    }
}
