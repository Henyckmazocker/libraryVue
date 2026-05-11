<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Videos;

use App\Domain\UseCases\Videos\GetVideoAllowedStatusesUseCase;
use App\Domain\Repository\Video\VideoRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetVideoAllowedStatusesUseCaseTest extends TestCase
{
    private GetVideoAllowedStatusesUseCase $useCase;
    private VideoRepositoryInterface $videoRepo;

    protected function setUp(): void
    {
        $this->videoRepo = $this->createMock(VideoRepositoryInterface::class);

        $this->useCase = new GetVideoAllowedStatusesUseCase(
            $this->videoRepo,
            new NullLogger()
        );
    }

    #[Test]
    public function returns_allowed_statuses(): void
    {
        $expected = [
            ['id' => 1, 'name' => 'watched'],
            ['id' => 2, 'name' => 'watch_later'],
        ];
        $this->videoRepo->method('fetchAllowedStatuses')->willReturn($expected);

        $result = $this->useCase->execute(null);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function returns_empty_when_no_statuses(): void
    {
        $this->videoRepo->method('fetchAllowedStatuses')->willReturn([]);

        $result = $this->useCase->execute(null);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
