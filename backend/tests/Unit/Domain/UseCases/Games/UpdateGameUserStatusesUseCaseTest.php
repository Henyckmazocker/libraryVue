<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Games;

use App\Domain\UseCases\Games\UpdateGameUserStatusesUseCase;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\DTO\Commands\UpdateGameStatusesCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class UpdateGameUserStatusesUseCaseTest extends TestCase
{
    private UpdateGameUserStatusesUseCase $useCase;
    private UserGameRepositoryInterface $userGameRepo;
    private UserRepositoryInterface $userRepo;
    private GameRepositoryInterface $gameRepo;

    protected function setUp(): void
    {
        $this->userGameRepo = $this->createMock(UserGameRepositoryInterface::class);
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->gameRepo = $this->createMock(GameRepositoryInterface::class);

        $this->useCase = new UpdateGameUserStatusesUseCase(
            $this->userGameRepo,
            $this->userRepo,
            $this->gameRepo,
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

        $command = new UpdateGameStatusesCommand(userId: 999, gameId: 12345, statuses: ['playing']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_game_not_in_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(false);

        $command = new UpdateGameStatusesCommand(userId: 1, gameId: 12345, statuses: ['playing']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Game not found in your library');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_on_invalid_status(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(true);
        $this->gameRepo->method('fetchAllowedStatuses')->willReturn(['playing', 'completed', 'wishlist']);

        $command = new UpdateGameStatusesCommand(userId: 1, gameId: 12345, statuses: ['invalid_status']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status: invalid_status');
        $this->useCase->execute($command);
    }

    #[Test]
    public function successfully_updates_statuses(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(true);
        $this->gameRepo->method('fetchAllowedStatuses')->willReturn(['playing', 'completed', 'wishlist']);
        $this->userGameRepo->expects($this->once())->method('updateStatuses')
            ->with(1, 12345, ['playing', 'completed']);

        $command = new UpdateGameStatusesCommand(userId: 1, gameId: 12345, statuses: ['playing', 'completed']);
        $this->useCase->execute($command);
    }
}
