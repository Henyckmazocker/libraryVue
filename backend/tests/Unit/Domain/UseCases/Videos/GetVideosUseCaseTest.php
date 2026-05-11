<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Videos;

use App\Domain\UseCases\Videos\GetVideosUseCase;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\DTO\Queries\GetVideosByUserQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetVideosUseCaseTest extends TestCase
{
    private GetVideosUseCase $useCase;
    private UserVideoRepositoryInterface $userVideoRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->userVideoRepo = $this->createMock(UserVideoRepositoryInterface::class);
        $this->userRepo      = $this->createMock(UserRepositoryInterface::class);

        $this->useCase = new GetVideosUseCase(
            $this->userVideoRepo,
            $this->userRepo,
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
    public function returns_empty_array_when_no_videos(): void
    {
        $user = new \App\Domain\Model\User(1, \App\Domain\Model\ValueObjects\GoogleId::fromString('1234567890123'), \App\Domain\Model\ValueObjects\Email::fromString('u@test.com'), 'T');
        $this->userRepo->method('findById')->willReturn($user);
        $this->userVideoRepo->method('findByUser')->willReturn([]);

        $result = $this->useCase->execute(['userId' => 1]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function returns_videos_for_user(): void
    {
        $this->userVideoRepo->method('findByUser')->willReturn([
            ['id' => 1, 'youtube_id' => 'dQw4w9WgXcQ', 'title' => 'Test'],
            ['id' => 2, 'youtube_id' => 'oHg5SJYRHA0', 'title' => 'Test 2'],
        ]);
        $this->userRepo->method('findById')->willReturn(
            new \App\Domain\Model\User(1, \App\Domain\Model\ValueObjects\GoogleId::fromString('1234567890123'), \App\Domain\Model\ValueObjects\Email::fromString('u@test.com'), 'T')
        );

        $result = $this->useCase->execute(['userId' => 1]);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }
}
