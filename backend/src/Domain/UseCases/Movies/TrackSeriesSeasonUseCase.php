<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\DTO\Commands\TrackSeriesSeasonCommand;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Repository\Movie\SeriesSeasonRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class TrackSeriesSeasonUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SeriesSeasonRepositoryInterface $seriesSeasonRepository,
        private readonly MovieRepositoryInterface $movieRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute(mixed ...$args): void
    {
        $command = $args[0] ?? null;

        if (!$command instanceof TrackSeriesSeasonCommand) {
            throw new InvalidArgumentException('Command must be an instance of TrackSeriesSeasonCommand');
        }

        if (empty($command->seriesIsbn)) {
            throw new InvalidArgumentException('Series ISBN cannot be empty');
        }

        // Verify the entry exists and is actually a series
        $movie = $this->movieRepository->findById($command->seriesIsbn);
        if (!$movie) {
            throw new InvalidArgumentException("Series with ISBN '{$command->seriesIsbn}' not found");
        }

        if ($movie->getMediaType() !== 'series') {
            throw new InvalidArgumentException(
                "The item with ISBN '{$command->seriesIsbn}' is not a series (media_type={$movie->getMediaType()})"
            );
        }

        $this->seriesSeasonRepository->trackSeason(
            $command->userId,
            $command->seriesIsbn,
            $command->seasonNumber,
            $command->status,
            $command->dateViewed,
            $command->personalRating,
            $command->notes,
        );
    }

    protected function getLogContext(): string
    {
        return 'TrackSeriesSeason';
    }
}
