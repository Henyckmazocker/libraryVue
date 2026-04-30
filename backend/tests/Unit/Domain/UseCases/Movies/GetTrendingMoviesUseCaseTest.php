<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\GetTrendingMoviesUseCase;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\DTO\Queries\GetTrendingMoviesQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetTrendingMoviesUseCaseTest extends TestCase
{
    private GetTrendingMoviesUseCase $useCase;
    private UserMovieRepositoryInterface $userMovieRepo;

    protected function setUp(): void
    {
        $this->userMovieRepo = $this->createMock(UserMovieRepositoryInterface::class);
        $this->useCase = new GetTrendingMoviesUseCase($this->userMovieRepo, new NullLogger());
    }

    #[Test]
    public function successfully_returns_trending_movies(): void
    {
        $trending = [['title' => 'Hot Movie', 'count' => 20]];
        $this->userMovieRepo->method('getTrendingMovies')
            ->with(20, 90, null)->willReturn($trending);

        $result = $this->useCase->execute(GetTrendingMoviesQuery::create());
        $this->assertCount(1, $result);
    }

    #[Test]
    public function passes_custom_params(): void
    {
        $this->userMovieRepo->expects($this->once())->method('getTrendingMovies')
            ->with(5, 30, 1)->willReturn([]);

        $result = $this->useCase->execute(GetTrendingMoviesQuery::create(5, 30, 1));
        $this->assertEmpty($result);
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }
}
