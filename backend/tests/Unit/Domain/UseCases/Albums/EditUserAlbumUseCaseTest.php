<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Albums;

use App\Domain\UseCases\Albums\EditUserAlbumUseCase;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\Album\AlbumTagRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\DTO\Commands\EditUserAlbumCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class EditUserAlbumUseCaseTest extends TestCase
{
    private EditUserAlbumUseCase $useCase;
    private UserAlbumRepositoryInterface $userAlbumRepo;
    private AlbumTagRepositoryInterface $albumTagRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->userAlbumRepo = $this->createMock(UserAlbumRepositoryInterface::class);
        $this->albumTagRepo  = $this->createMock(AlbumTagRepositoryInterface::class);
        $this->userRepo      = $this->createMock(UserRepositoryInterface::class);

        $this->useCase = new EditUserAlbumUseCase(
            $this->userAlbumRepo,
            $this->albumTagRepo,
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
        $this->useCase->execute(new EditUserAlbumCommand(userId: 999, albumId: 1));
    }

    #[Test]
    public function throws_when_album_not_in_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Album not found in your library');
        $this->useCase->execute(new EditUserAlbumCommand(userId: 1, albumId: 99));
    }

    #[Test]
    public function successfully_edits_album_with_statuses_and_tags(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(true);
        $this->userAlbumRepo->method('update')->willReturn(true);

        $this->userAlbumRepo->expects($this->once())
            ->method('updateStatuses')
            ->with(1, 10, ['listened']);

        $this->albumTagRepo->expects($this->once())
            ->method('removeAllFromAlbum')
            ->with(1, 10);

        $this->albumTagRepo->expects($this->exactly(2))
            ->method('assignToAlbum');

        $command = new EditUserAlbumCommand(
            userId: 1,
            albumId: 10,
            userRating: Rating::fromFloat(4.5),
            statuses: ['listened'],
            tags: [1, 2]
        );

        $result = $this->useCase->execute($command);
        $this->assertTrue($result);
    }

    #[Test]
    public function skips_status_update_when_statuses_null(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(true);
        $this->userAlbumRepo->method('update')->willReturn(true);

        $this->userAlbumRepo->expects($this->never())->method('updateStatuses');
        $this->albumTagRepo->expects($this->once())->method('removeAllFromAlbum');

        $command = new EditUserAlbumCommand(
            userId: 1,
            albumId: 10,
            statuses: null,
            tags: []
        );

        $this->useCase->execute($command);
    }

    #[Test]
    public function updates_statuses_with_empty_array(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(true);
        $this->userAlbumRepo->method('update')->willReturn(true);

        $this->userAlbumRepo->expects($this->once())
            ->method('updateStatuses')
            ->with(1, 10, []);

        $command = new EditUserAlbumCommand(
            userId: 1,
            albumId: 10,
            statuses: [],
            tags: []
        );

        $this->useCase->execute($command);
    }

    #[Test]
    public function assigns_tags_to_album(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(true);
        $this->userAlbumRepo->method('update')->willReturn(true);

        $this->albumTagRepo->expects($this->once())->method('removeAllFromAlbum');
        $this->albumTagRepo->expects($this->exactly(3))->method('assignToAlbum');

        $command = new EditUserAlbumCommand(
            userId: 1,
            albumId: 10,
            tags: [5, 6, 7]
        );

        $this->useCase->execute($command);
    }
}
