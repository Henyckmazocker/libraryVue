<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Albums;

use App\Domain\UseCases\Albums\GetAlbumAllowedStatusesUseCase;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GetAlbumAllowedStatusesUseCaseTest extends TestCase
{
    private GetAlbumAllowedStatusesUseCase $useCase;
    private AlbumRepositoryInterface $albumRepo;

    protected function setUp(): void
    {
        $this->albumRepo = $this->createMock(AlbumRepositoryInterface::class);
        $this->useCase   = new GetAlbumAllowedStatusesUseCase($this->albumRepo, new NullLogger());
    }

    #[Test]
    public function returns_allowed_statuses_from_repository(): void
    {
        $statuses = ['listened', 'listening', 're-listening', 'in-wishlist', 'favorite'];
        $this->albumRepo->method('fetchAllowedStatuses')->willReturn($statuses);

        $result = $this->useCase->execute(null);

        $this->assertEquals($statuses, $result);
    }

    #[Test]
    public function returns_empty_array_when_no_statuses(): void
    {
        $this->albumRepo->method('fetchAllowedStatuses')->willReturn([]);

        $result = $this->useCase->execute(null);
        $this->assertEmpty($result);
    }

    #[Test]
    public function delegates_to_repository_regardless_of_command(): void
    {
        $this->albumRepo->expects($this->once())->method('fetchAllowedStatuses')->willReturn(['listened']);

        $this->useCase->execute('anything');
    }
}
