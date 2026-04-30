<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Albums;

use App\Domain\UseCases\Albums\GetTrendingAlbumsUseCase;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\DTO\Queries\GetTrendingAlbumsQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetTrendingAlbumsUseCaseTest extends TestCase
{
    private GetTrendingAlbumsUseCase $useCase;
    private UserAlbumRepositoryInterface $userAlbumRepo;

    protected function setUp(): void
    {
        $this->userAlbumRepo = $this->createMock(UserAlbumRepositoryInterface::class);
        $this->useCase       = new GetTrendingAlbumsUseCase($this->userAlbumRepo, new NullLogger());
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function returns_trending_albums(): void
    {
        $this->userAlbumRepo->method('getTrendingAlbums')->willReturn(['album1', 'album2']);

        $query = GetTrendingAlbumsQuery::create(limit: 5, daysWindow: 30);
        $result = $this->useCase->execute($query);

        $this->assertEquals(['album1', 'album2'], $result);
    }

    #[Test]
    public function passes_query_params_to_repository(): void
    {
        $this->userAlbumRepo->expects($this->once())
            ->method('getTrendingAlbums')
            ->with(10, 14, 7)
            ->willReturn([]);

        $query = GetTrendingAlbumsQuery::create(limit: 10, daysWindow: 14, userId: 7);
        $this->useCase->execute($query);
    }

    #[Test]
    public function passes_null_user_id_when_not_specified(): void
    {
        $this->userAlbumRepo->expects($this->once())
            ->method('getTrendingAlbums')
            ->with(20, 90, null)
            ->willReturn([]);

        $query = new GetTrendingAlbumsQuery();
        $this->useCase->execute($query);
    }

    #[Test]
    public function returns_empty_array_when_no_trending(): void
    {
        $this->userAlbumRepo->method('getTrendingAlbums')->willReturn([]);

        $result = $this->useCase->execute(new GetTrendingAlbumsQuery());
        $this->assertEmpty($result);
    }
}
