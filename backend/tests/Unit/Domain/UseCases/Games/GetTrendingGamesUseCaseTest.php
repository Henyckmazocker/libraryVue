<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Games;

use App\Domain\UseCases\Games\GetTrendingGamesUseCase;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\DTO\Queries\GetTrendingGamesQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetTrendingGamesUseCaseTest extends TestCase
{
    private GetTrendingGamesUseCase $useCase;
    private UserGameRepositoryInterface $userGameRepo;

    protected function setUp(): void
    {
        $this->userGameRepo = $this->createMock(UserGameRepositoryInterface::class);
        $this->useCase = new GetTrendingGamesUseCase($this->userGameRepo, new NullLogger());
    }

    #[Test]
    public function successfully_returns_trending_games(): void
    {
        $trending = [['name' => 'Hot Game', 'count' => 15]];
        $this->userGameRepo->method('getTrendingGames')
            ->with(20, 90, null)->willReturn($trending);

        $result = $this->useCase->execute(GetTrendingGamesQuery::create());
        $this->assertCount(1, $result);
    }

    #[Test]
    public function passes_custom_params(): void
    {
        $this->userGameRepo->expects($this->once())->method('getTrendingGames')
            ->with(10, 30, 1)->willReturn([]);

        $result = $this->useCase->execute(GetTrendingGamesQuery::create(10, 30, 1));
        $this->assertEmpty($result);
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }
}
