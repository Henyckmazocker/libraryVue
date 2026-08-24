<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Albums;

use App\Domain\UseCases\Albums\AddAlbumUseCase;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Catalog\AlbumCatalogInterface;
use App\Domain\Services\CoverService;
use App\Domain\Services\FeedEventService;
use App\Domain\DTO\Commands\AddAlbumCommand;
use App\Domain\Model\Album;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\SpotifyId;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class AddAlbumUseCaseTest extends TestCase
{
    private AddAlbumUseCase $useCase;
    private AlbumRepositoryInterface $albumRepo;
    private UserAlbumRepositoryInterface $userAlbumRepo;
    private UserRepositoryInterface $userRepo;
    private FeedEventService $feedEventService;
    private CoverService $coverService;
    private AlbumCatalogInterface $albumCatalog;

    private const SPOTIFY_ID = '4aawyAB9vmqN3uQ7FjRGTy';

    protected function setUp(): void
    {
        $this->albumRepo     = $this->createMock(AlbumRepositoryInterface::class);
        $this->userAlbumRepo = $this->createMock(UserAlbumRepositoryInterface::class);
        $this->userRepo      = $this->createMock(UserRepositoryInterface::class);
        $this->feedEventService = $this->createMock(FeedEventService::class);
        $this->coverService = $this->createMock(CoverService::class);
        // Por defecto no resuelve nada por código de barras: los tests de
        // este fichero son sobre el alta, no sobre la reconciliación.
        $this->albumCatalog = $this->createMock(AlbumCatalogInterface::class);

        $this->useCase = new AddAlbumUseCase(
            $this->albumRepo,
            $this->userAlbumRepo,
            $this->userRepo,
            $this->feedEventService,
            $this->coverService,
            $this->albumCatalog,
            new NullLogger()
        );
    }

    private function makeUser(int $id = 1): User
    {
        return new User($id, GoogleId::fromString('1234567890'), Email::fromString('u@test.com'), 'Test');
    }

    private function makeAlbum(int $id = 1): Album
    {
        return Album::fromArray([
            'id' => $id,
            'spotify_id' => self::SPOTIFY_ID,
            'title' => 'OK Computer',
            'artist' => 'Radiohead',
            'userStatuses' => ['listened'],
        ]);
    }

    private function makeCommand(int $userId = 1): AddAlbumCommand
    {
        return AddAlbumCommand::fromArray([
            'spotify_id' => self::SPOTIFY_ID,
            'title' => 'OK Computer',
            'artist' => 'Radiohead',
            'statuses' => ['listened'],
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
    public function throws_when_user_already_has_album(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->albumRepo->method('findBySpotifyId')->willReturn($this->makeAlbum());
        $this->userAlbumRepo->method('hasAlbum')->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already have this album');
        $this->useCase->execute($this->makeCommand());
    }

    #[Test]
    public function adds_existing_album_to_user_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $existingAlbum = $this->makeAlbum(42);
        $this->albumRepo->method('findBySpotifyId')->willReturn($existingAlbum);
        $this->userAlbumRepo->method('hasAlbum')->willReturn(false);
        $this->userAlbumRepo->expects($this->once())->method('add');

        $result = $this->useCase->execute($this->makeCommand());
        $this->assertInstanceOf(Album::class, $result);
        $this->assertSame(42, $result->getId());
    }

    #[Test]
    public function creates_new_album_when_not_found_in_catalogue(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->albumRepo->method('findBySpotifyId')->willReturn(null);

        $savedAlbum = $this->makeAlbum(10);
        $this->albumRepo->expects($this->once())->method('save')->willReturn($savedAlbum);
        $this->userAlbumRepo->method('hasAlbum')->willReturn(false);
        $this->userAlbumRepo->expects($this->once())->method('add');

        $result = $this->useCase->execute($this->makeCommand());
        $this->assertInstanceOf(Album::class, $result);
    }
}
