<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\GetSeriesProgressUseCase;
use App\Domain\Repository\Movie\SeriesSeasonRepositoryInterface;
use App\Domain\DTO\Queries\GetSeriesProgressQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetSeriesProgressUseCaseTest extends TestCase
{
    private GetSeriesProgressUseCase $useCase;
    private SeriesSeasonRepositoryInterface $seriesSeasonRepo;

    protected function setUp(): void
    {
        $this->seriesSeasonRepo = $this->createMock(SeriesSeasonRepositoryInterface::class);

        $this->useCase = new GetSeriesProgressUseCase(
            $this->seriesSeasonRepo,
            new NullLogger()
        );
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_when_isbn_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN cannot be empty');
        $this->useCase->execute(new GetSeriesProgressQuery(userId: 1, seriesIsbn: ''));
    }

    #[Test]
    public function returns_empty_array_when_no_seasons_tracked(): void
    {
        $this->seriesSeasonRepo->method('getProgress')->willReturn([]);

        $query = new GetSeriesProgressQuery(userId: 1, seriesIsbn: 'tt1234567');

        $result = $this->useCase->execute($query);

        $this->assertSame([], $result);
    }

    #[Test]
    public function returns_progress_keyed_by_season_number(): void
    {
        $progress = [
            1 => ['season_number' => 1, 'status' => 'viewed', 'personal_rating' => 4.5],
            2 => ['season_number' => 2, 'status' => 'partial', 'personal_rating' => null],
        ];

        $this->seriesSeasonRepo->method('getProgress')->willReturn($progress);

        $query = new GetSeriesProgressQuery(userId: 1, seriesIsbn: 'tt1234567');

        $result = $this->useCase->execute($query);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertSame('viewed', $result[1]['status']);
        $this->assertSame('partial', $result[2]['status']);
    }

    #[Test]
    public function passes_correct_user_and_isbn_to_repository(): void
    {
        $this->seriesSeasonRepo
            ->expects($this->once())
            ->method('getProgress')
            ->with(42, 'tt9876543')
            ->willReturn([]);

        $query = new GetSeriesProgressQuery(userId: 42, seriesIsbn: 'tt9876543');

        $this->useCase->execute($query);
    }
}
