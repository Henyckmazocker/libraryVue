<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Albums;

use App\Domain\UseCases\Albums\UpdateAlbumRatingUseCase;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\DTO\Commands\UpdateAlbumRatingCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class UpdateAlbumRatingUseCaseTest extends TestCase
{
    private UpdateAlbumRatingUseCase $useCase;
    private UserAlbumRepositoryInterface $userAlbumRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->userAlbumRepo = $this->createMock(UserAlbumRepositoryInterface::class);
        $this->userRepo      = $this->createMock(UserRepositoryInterface::class);

        $this->useCase = new UpdateAlbumRatingUseCase(
            $this->userAlbumRepo,
            $this->userRepo,
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
        $this->useCase->execute(new UpdateAlbumRatingCommand(
            userId: 999,
            albumId: 1,
            rating: Rating::fromFloat(4.0)
        ));
    }

    #[Test]
    public function throws_when_album_not_in_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Album not found in your library');
        $this->useCase->execute(new UpdateAlbumRatingCommand(
            userId: 1,
            albumId: 99,
            rating: Rating::fromFloat(3.0)
        ));
    }

    #[Test]
    public function successfully_updates_rating(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(true);

        $this->userAlbumRepo->expects($this->once())
            ->method('updateRating')
            ->with(1, 10, 4.5);

        $this->useCase->execute(new UpdateAlbumRatingCommand(
            userId: 1,
            albumId: 10,
            rating: Rating::fromFloat(4.5)
        ));
    }
}
