<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Albums;

use App\Domain\UseCases\Albums\GetAlbumsUseCase;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetAlbumsUseCaseTest extends TestCase
{
    private GetAlbumsUseCase $useCase;
    private UserAlbumRepositoryInterface $userAlbumRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->userAlbumRepo = $this->createMock(UserAlbumRepositoryInterface::class);
        $this->userRepo      = $this->createMock(UserRepositoryInterface::class);

        $this->useCase = new GetAlbumsUseCase(
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
    public function throws_on_invalid_command_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_on_array_without_user_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Command must be an array with userId');
        $this->useCase->execute(['filters' => []]);
    }

    #[Test]
    public function throws_when_user_not_found(): void
    {
        $this->userRepo->method('findById')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 42 not found');
        $this->useCase->execute(['userId' => 42]);
    }

    #[Test]
    public function returns_albums_for_valid_user(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userAlbumRepo->method('findByUser')->willReturn(['album1', 'album2']);

        $result = $this->useCase->execute(['userId' => 1]);
        $this->assertEquals(['album1', 'album2'], $result);
    }

    #[Test]
    public function passes_filters_to_repository(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $filters = ['status' => 'listened'];

        $this->userAlbumRepo->expects($this->once())
            ->method('findByUser')
            ->with(1, $filters)
            ->willReturn([]);

        $this->useCase->execute(['userId' => 1, 'filters' => $filters]);
    }

    #[Test]
    public function passes_empty_filters_by_default(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());

        $this->userAlbumRepo->expects($this->once())
            ->method('findByUser')
            ->with(1, [])
            ->willReturn([]);

        $this->useCase->execute(['userId' => 1]);
    }
}
