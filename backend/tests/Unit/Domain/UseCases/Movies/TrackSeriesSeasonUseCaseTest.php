<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\TrackSeriesSeasonUseCase;
use App\Domain\Repository\Movie\SeriesSeasonRepositoryInterface;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\DTO\Commands\TrackSeriesSeasonCommand;
use App\Domain\Model\Movie;
use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Timestamp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class TrackSeriesSeasonUseCaseTest extends TestCase
{
    private TrackSeriesSeasonUseCase $useCase;
    private SeriesSeasonRepositoryInterface $seriesSeasonRepo;
    private MovieRepositoryInterface $movieRepo;

    protected function setUp(): void
    {
        $this->seriesSeasonRepo = $this->createMock(SeriesSeasonRepositoryInterface::class);
        $this->movieRepo        = $this->createMock(MovieRepositoryInterface::class);

        $this->useCase = new TrackSeriesSeasonUseCase(
            $this->seriesSeasonRepo,
            $this->movieRepo,
            new NullLogger()
        );
    }

    private function makeSeries(string $isbn = 'tt1234567', string $mediaType = 'series'): Movie
    {
        $movie = new Movie(
            MovieIdentifier::fromString($isbn),
            'Breaking Bad',
            null,
            'Vince Gilligan',
            null,
            null,
            null,
            null,
            [],
            Timestamp::now(),
            [],
            null,
            null,
            null,
            null,
            $mediaType,
            5
        );
        return $movie;
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_when_series_not_found(): void
    {
        $this->movieRepo->method('findById')->willReturn(null);

        $command = new TrackSeriesSeasonCommand(
            userId: 1,
            seriesIsbn: 'tt9999999',
            seasonNumber: 1,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_item_is_not_a_series(): void
    {
        $movie = $this->makeSeries('tt1234567', 'movie');
        $this->movieRepo->method('findById')->willReturn($movie);

        $command = new TrackSeriesSeasonCommand(
            userId: 1,
            seriesIsbn: 'tt1234567',
            seasonNumber: 1,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a series');
        $this->useCase->execute($command);
    }

    #[Test]
    public function tracks_season_successfully(): void
    {
        $series = $this->makeSeries('tt1234567', 'series');
        $this->movieRepo->method('findById')->willReturn($series);

        $this->seriesSeasonRepo->expects($this->once())
            ->method('trackSeason')
            ->with(1, 'tt1234567', 2, 'viewed', null, 4.5, 'Great season');

        $command = new TrackSeriesSeasonCommand(
            userId: 1,
            seriesIsbn: 'tt1234567',
            seasonNumber: 2,
            status: 'viewed',
            dateViewed: null,
            personalRating: 4.5,
            notes: 'Great season',
        );

        $this->useCase->execute($command);
    }

    #[Test]
    public function tracks_partial_season(): void
    {
        $series = $this->makeSeries();
        $this->movieRepo->method('findById')->willReturn($series);

        $this->seriesSeasonRepo->expects($this->once())
            ->method('trackSeason')
            ->with(1, 'tt1234567', 3, 'partial', null, null, null);

        $command = new TrackSeriesSeasonCommand(
            userId: 1,
            seriesIsbn: 'tt1234567',
            seasonNumber: 3,
            status: 'partial',
        );

        $this->useCase->execute($command);
    }
}
