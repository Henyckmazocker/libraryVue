<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\DTO\Commands\TrackSeriesSeasonCommand;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Movie\SeriesSeasonRepositoryInterface;
use App\Domain\Services\JournalService;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class TrackSeriesSeasonUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SeriesSeasonRepositoryInterface $seriesSeasonRepository,
        private readonly MovieRepositoryInterface $movieRepository,
        private readonly JournalService $journalService,
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

        // En series la entrada del diario es una TEMPORADA, no la serie: su
        // `source_id` es `"<imdbID>:<temporada>"`, y por eso volver a guardar la
        // misma temporada actualiza su entrada en vez de duplicarla.
        //
        // Solo `viewed` apunta: `partial` es «voy por la mitad» y `skipped` es
        // justo lo contrario de haberla visto. Y la fecha es la que el usuario
        // eligiera en el seguimiento; si no puso ninguna, hoy.
        if ($command->status === 'viewed') {
            $this->journalService->record(
                userId:    $command->userId,
                media:     JournalEntry::MEDIA_SERIES,
                entityId:  $command->seriesIsbn,
                title:     $movie->getTitle(),
                cover:     $movie->getCoverUrl(),
                entryDate: $command->dateViewed ?: date('Y-m-d'),
                rating:    $command->personalRating,
                source:    JournalEntry::SOURCE_SERIES_SEASON,
                sourceId:  $command->seriesIsbn . ':' . $command->seasonNumber
            );
        }
    }

    protected function getLogContext(): string
    {
        return 'TrackSeriesSeason';
    }
}
