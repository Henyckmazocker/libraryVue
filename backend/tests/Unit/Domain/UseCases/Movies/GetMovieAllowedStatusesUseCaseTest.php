<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\DTO\Queries\GetAllowedStatusesQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetMovieAllowedStatusesUseCaseTest extends TestCase
{
    private GetMovieAllowedStatusesUseCase $useCase;
    private MovieRepositoryInterface $movieRepo;

    protected function setUp(): void
    {
        $this->movieRepo = $this->createMock(MovieRepositoryInterface::class);
        $this->useCase = new GetMovieAllowedStatusesUseCase($this->movieRepo, new NullLogger());
    }

    #[Test]
    public function successfully_returns_allowed_statuses(): void
    {
        $statuses = ['watched', 'watchlist', 'owned'];
        $this->movieRepo->method('fetchAllowedStatuses')->willReturn($statuses);

        $result = $this->useCase->execute(GetAllowedStatusesQuery::forMovies());
        $this->assertSame($statuses, $result);
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_on_wrong_entity_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only handles movie statuses');
        $this->useCase->execute(GetAllowedStatusesQuery::forBooks());
    }
}
