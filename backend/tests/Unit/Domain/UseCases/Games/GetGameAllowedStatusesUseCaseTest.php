<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Games;

use App\Domain\UseCases\Games\GetGameAllowedStatusesUseCase;
use App\Domain\Repository\Game\GameRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GetGameAllowedStatusesUseCaseTest extends TestCase
{
    private GetGameAllowedStatusesUseCase $useCase;
    private GameRepositoryInterface $gameRepo;

    protected function setUp(): void
    {
        $this->gameRepo = $this->createMock(GameRepositoryInterface::class);
        $this->useCase = new GetGameAllowedStatusesUseCase($this->gameRepo, new NullLogger());
    }

    #[Test]
    public function successfully_returns_allowed_statuses(): void
    {
        $statuses = ['playing', 'completed', 'wishlist', 'owned', 'backlog'];
        $this->gameRepo->method('fetchAllowedStatuses')->willReturn($statuses);

        // GetGameAllowedStatusesUseCase ignores the command entirely
        $result = $this->useCase->execute(null);
        $this->assertSame($statuses, $result);
    }

    #[Test]
    public function returns_empty_array_when_no_statuses(): void
    {
        $this->gameRepo->method('fetchAllowedStatuses')->willReturn([]);

        $result = $this->useCase->execute(null);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
