<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Albums;

use App\Domain\UseCases\Albums\UpdateAlbumUserStatusesUseCase;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\DTO\Commands\UpdateAlbumStatusesCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class UpdateAlbumUserStatusesUseCaseTest extends TestCase
{
    private UpdateAlbumUserStatusesUseCase $useCase;
    private UserAlbumRepositoryInterface $userAlbumRepo;
    private AlbumRepositoryInterface $albumRepo;
    private UserRepositoryInterface $userRepo;
    private FeedEventService $feedEventService;

    protected function setUp(): void
    {
        $this->userAlbumRepo = $this->createMock(UserAlbumRepositoryInterface::class);
        $this->albumRepo     = $this->createMock(AlbumRepositoryInterface::class);
        $this->userRepo      = $this->createMock(UserRepositoryInterface::class);
        $this->feedEventService = $this->createMock(FeedEventService::class);

        $this->useCase = new UpdateAlbumUserStatusesUseCase(
            $this->userAlbumRepo,
            $this->albumRepo,
            $this->userRepo,
            $this->feedEventService,
            new NullLogger()
        );
    }

    private function makeUser(int $id = 1): User
    {
        return new User($id, GoogleId::fromString('1234567890'), Email::fromString('u@test.com'), 'Test');
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
        $this->useCase->execute(new UpdateAlbumStatusesCommand(userId: 999, albumId: 1, statuses: ['listened']));
    }

    #[Test]
    public function throws_when_album_not_in_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Album not found in your library');
        $this->useCase->execute(new UpdateAlbumStatusesCommand(userId: 1, albumId: 99, statuses: ['listened']));
    }

    #[Test]
    public function throws_when_invalid_status_provided(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(true);
        $this->albumRepo->method('fetchAllowedStatuses')->willReturn(['listened', 'in-wishlist', 'favorite']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status: unknown-status');
        $this->useCase->execute(new UpdateAlbumStatusesCommand(userId: 1, albumId: 10, statuses: ['unknown-status']));
    }

    #[Test]
    public function successfully_updates_statuses(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(true);
        $this->albumRepo->method('fetchAllowedStatuses')->willReturn(['listened', 'in-wishlist', 'favorite']);

        $this->userAlbumRepo->expects($this->once())
            ->method('updateStatuses')
            ->with(1, 10, ['listened', 'favorite']);

        $this->useCase->execute(new UpdateAlbumStatusesCommand(userId: 1, albumId: 10, statuses: ['listened', 'favorite']));
    }

    #[Test]
    public function successfully_updates_with_empty_statuses(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(true);
        $this->albumRepo->method('fetchAllowedStatuses')->willReturn(['listened', 'in-wishlist']);

        $this->userAlbumRepo->expects($this->once())
            ->method('updateStatuses')
            ->with(1, 10, []);

        $this->useCase->execute(new UpdateAlbumStatusesCommand(userId: 1, albumId: 10, statuses: []));
    }
}
