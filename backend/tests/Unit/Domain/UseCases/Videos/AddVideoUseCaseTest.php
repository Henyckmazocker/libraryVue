<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Videos;

use App\Domain\UseCases\Videos\AddVideoUseCase;
use App\Domain\Repository\Video\VideoRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\CoverService;
use App\Domain\Services\FeedEventService;
use App\Domain\DTO\Commands\AddVideoCommand;
use App\Domain\Model\Video;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\YouTubeId;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class AddVideoUseCaseTest extends TestCase
{
    private AddVideoUseCase $useCase;
    private VideoRepositoryInterface $videoRepo;
    private UserVideoRepositoryInterface $userVideoRepo;
    private UserRepositoryInterface $userRepo;
    private FeedEventService $feedEventService;
    private CoverService $coverService;

    private const YOUTUBE_ID = 'dQw4w9WgXcQ';

    protected function setUp(): void
    {
        $this->videoRepo     = $this->createMock(VideoRepositoryInterface::class);
        $this->userVideoRepo = $this->createMock(UserVideoRepositoryInterface::class);
        $this->userRepo      = $this->createMock(UserRepositoryInterface::class);
        $this->feedEventService = $this->createMock(FeedEventService::class);
        $this->coverService = $this->createMock(CoverService::class);

        $this->useCase = new AddVideoUseCase(
            $this->videoRepo,
            $this->userVideoRepo,
            $this->userRepo,
            $this->feedEventService,
            $this->coverService,
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
            'userStatuses' => ['watch_later'],
        ]);
    }

    private function makeCommand(int $userId = 1): AddVideoCommand
    {
        return AddVideoCommand::fromArray([
            'youtubeId' => self::YOUTUBE_ID,
            'title'     => 'Never Gonna Give You Up',
            'statuses'  => ['watch_later'],
        ], $userId);
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
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($this->makeCommand(999));
    }

    #[Test]
    public function throws_when_user_already_has_video(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->videoRepo->method('findByYouTubeId')->willReturn($this->makeVideo());
        $this->userVideoRepo->method('hasVideo')->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already have this video');
        $this->useCase->execute($this->makeCommand());
    }

    #[Test]
    public function adds_existing_video_to_user_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $existingVideo = $this->makeVideo(42);
        $this->videoRepo->method('findByYouTubeId')->willReturn($existingVideo);
        $this->userVideoRepo->method('hasVideo')->willReturn(false);
        $this->userVideoRepo->expects($this->once())->method('add');

        $result = $this->useCase->execute($this->makeCommand());
        $this->assertInstanceOf(Video::class, $result);
        $this->assertSame(42, $result->getId());
    }

    #[Test]
    public function creates_new_video_when_not_found_in_catalogue(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->videoRepo->method('findByYouTubeId')->willReturn(null);

        $savedVideo = $this->makeVideo(10);
        $this->videoRepo->expects($this->once())->method('save')->willReturn($savedVideo);
        $this->userVideoRepo->method('hasVideo')->willReturn(false);
        $this->userVideoRepo->expects($this->once())->method('add');

        $result = $this->useCase->execute($this->makeCommand());
        $this->assertInstanceOf(Video::class, $result);
    }

    #[Test]
    public function records_the_feed_event_with_the_youtube_id_not_the_numeric_id(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->videoRepo->method('findByYouTubeId')->willReturn($this->makeVideo(42));
        $this->userVideoRepo->method('hasVideo')->willReturn(false);

        $this->feedEventService->expects($this->once())
            ->method('recordItemAdded')
            ->with(1, 'video', self::YOUTUBE_ID, 'Never Gonna Give You Up', null);

        $this->useCase->execute($this->makeCommand());
    }
}
