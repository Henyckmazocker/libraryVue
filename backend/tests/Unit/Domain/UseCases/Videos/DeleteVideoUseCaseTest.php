<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Videos;

use App\Domain\UseCases\Videos\DeleteVideoUseCase;
use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\DTO\Commands\DeleteVideoCommand;
use App\Domain\Model\Video;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class DeleteVideoUseCaseTest extends TestCase
{
    private DeleteVideoUseCase $useCase;
    private VideoRepositoryInterface $videoRepo;
    private UserVideoRepositoryInterface $userVideoRepo;
    private UserRepositoryInterface $userRepo;

    private const YOUTUBE_ID = 'dQw4w9WgXcQ';

    protected function setUp(): void
    {
        $this->videoRepo     = $this->createMock(VideoRepositoryInterface::class);
        $this->userVideoRepo = $this->createMock(UserVideoRepositoryInterface::class);
        $this->userRepo      = $this->createMock(UserRepositoryInterface::class);

        $this->useCase = new DeleteVideoUseCase(
            $this->videoRepo,
            $this->userVideoRepo,
            $this->userRepo,
            new NullLogger()
        );
    }

    private function makeUser(int $id = 1): User
    {
        return new User($id, GoogleId::fromString('1234567890'), Email::fromString('u@test.com'), 'Test');
    }

    private function makeVideo(int $id = 1): Video
    {
        return Video::fromArray([
            'id'           => $id,
            'youtube_id'   => self::YOUTUBE_ID,
            'title'        => 'Never Gonna Give You Up',
            'userStatuses' => ['watched'],
        ]);
    }

    private function makeCommand(int $userId = 1): DeleteVideoCommand
    {
        return new DeleteVideoCommand($userId, self::YOUTUBE_ID);
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_when_user_not_found(): void
    {
        $this->userRepo->method('findById')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID');
        $this->useCase->execute($this->makeCommand());
    }

    #[Test]
    public function throws_when_video_not_found(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->videoRepo->method('findByYouTubeId')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Video not found');
        $this->useCase->execute($this->makeCommand());
    }

    #[Test]
    public function throws_when_video_not_in_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->videoRepo->method('findByYouTubeId')->willReturn($this->makeVideo());
        $this->userVideoRepo->method('hasVideo')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found in your library');
        $this->useCase->execute($this->makeCommand());
    }

    #[Test]
    public function deletes_video_from_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->videoRepo->method('findByYouTubeId')->willReturn($this->makeVideo(5));
        $this->userVideoRepo->method('hasVideo')->willReturn(true);
        $this->userVideoRepo->expects($this->once())->method('remove')->willReturn(true);

        $result = $this->useCase->execute($this->makeCommand());
        $this->assertTrue($result);
    }
}
