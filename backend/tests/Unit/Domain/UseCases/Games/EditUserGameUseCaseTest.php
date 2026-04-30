<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Games;

use App\Domain\UseCases\Games\EditUserGameUseCase;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\Game\GameTagRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\DTO\Commands\EditUserGameCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class EditUserGameUseCaseTest extends TestCase
{
    private EditUserGameUseCase $useCase;
    private UserGameRepositoryInterface $userGameRepo;
    private GameTagRepositoryInterface $gameTagRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->userGameRepo = $this->createMock(UserGameRepositoryInterface::class);
        $this->gameTagRepo = $this->createMock(GameTagRepositoryInterface::class);
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);

        $this->useCase = new EditUserGameUseCase(
            $this->userGameRepo,
            $this->gameTagRepo,
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

        $command = new EditUserGameCommand(userId: 999, gameId: 12345);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_game_not_in_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(false);

        $command = new EditUserGameCommand(userId: 1, gameId: 12345);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Game not found in your library');
        $this->useCase->execute($command);
    }

    #[Test]
    public function successfully_edits_game_with_statuses_and_tags(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(true);
        $this->userGameRepo->method('update')->willReturn(true);

        $this->userGameRepo->expects($this->once())->method('updateStatuses')
            ->with(1, 12345, ['playing']);

        $this->gameTagRepo->expects($this->once())->method('removeAllFromGame')
            ->with(1, 12345);
        $this->gameTagRepo->expects($this->exactly(2))->method('assignToGame');

        $command = new EditUserGameCommand(
            userId: 1,
            gameId: 12345,
            userRating: Rating::fromFloat(4.5),
            statuses: ['playing'],
            tags: [1, 2]
        );

        $result = $this->useCase->execute($command);
        $this->assertTrue($result);
    }

    #[Test]
    public function skips_status_update_when_null(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(true);
        $this->userGameRepo->method('update')->willReturn(true);

        $this->userGameRepo->expects($this->never())->method('updateStatuses');
        $this->gameTagRepo->expects($this->once())->method('removeAllFromGame');

        $command = new EditUserGameCommand(
            userId: 1,
            gameId: 12345,
            statuses: null,
            tags: []
        );

        $this->useCase->execute($command);
    }
}
