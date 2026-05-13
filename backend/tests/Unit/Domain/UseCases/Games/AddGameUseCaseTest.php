<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Games;

use App\Domain\UseCases\Games\AddGameUseCase;
use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\DTO\Commands\AddGameCommand;
use App\Domain\Model\User;
use App\Domain\Model\Game;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class AddGameUseCaseTest extends TestCase
{
    private AddGameUseCase $useCase;
    private GameRepositoryInterface $gameRepo;
    private UserRepositoryInterface $userRepo;
    private UserGameRepositoryInterface $userGameRepo;
    private FeedEventService $feedEventService;

    protected function setUp(): void
    {
        $this->gameRepo = $this->createMock(GameRepositoryInterface::class);
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userGameRepo = $this->createMock(UserGameRepositoryInterface::class);
        $this->feedEventService = $this->createMock(FeedEventService::class);

        $this->useCase = new AddGameUseCase(
            $this->gameRepo,
            $this->userRepo,
            $this->userGameRepo,
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

        $command = AddGameCommand::fromArray([
            'id' => 12345,
            'title' => 'Test Game',
            'userId' => 999,
            'slug' => 'test-game',
        ], 999);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_user_already_has_game(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(true);

        $command = AddGameCommand::fromArray([
            'id' => 12345,
            'title' => 'Test Game',
            'userId' => 1,
            'slug' => 'test-game',
        ], 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already have this game');
        $this->useCase->execute($command);
    }

    #[Test]
    public function adds_existing_game_to_user_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(false);

        $existingGame = Game::fromArray([
            'id' => 12345,
            'title' => 'Existing Game',
            'slug' => 'existing-game',
            'userStatuses' => ['owned'],
        ]);
        $this->gameRepo->method('findById')->willReturn($existingGame);
        $this->userGameRepo->expects($this->once())->method('add');

        $command = AddGameCommand::fromArray([
            'id' => 12345,
            'title' => 'Existing Game',
            'userId' => 1,
            'slug' => 'existing-game',
        ], 1);

        $result = $this->useCase->execute($command);
        $this->assertInstanceOf(Game::class, $result);
    }

    #[Test]
    public function creates_new_game_when_not_found(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(false);
        $this->gameRepo->method('findById')->willReturn(null);
        $this->gameRepo->expects($this->once())->method('save');
        $this->userGameRepo->expects($this->once())->method('add');

        $command = AddGameCommand::fromArray([
            'id' => 99999,
            'title' => 'Brand New Game',
            'userId' => 1,
            'slug' => 'brand-new-game',
            'userStatuses' => ['owned'],
        ], 1);

        $result = $this->useCase->execute($command);
        $this->assertInstanceOf(Game::class, $result);
    }
}
