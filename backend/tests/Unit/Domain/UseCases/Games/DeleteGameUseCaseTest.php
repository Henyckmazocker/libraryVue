<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Games;

use App\Domain\UseCases\Games\DeleteGameUseCase;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\DTO\Commands\DeleteGameCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class DeleteGameUseCaseTest extends TestCase
{
    private DeleteGameUseCase $useCase;
    private UserGameRepositoryInterface $userGameRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->userGameRepo = $this->createMock(UserGameRepositoryInterface::class);
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);

        $this->useCase = new DeleteGameUseCase(
            $this->userGameRepo,
            $this->userRepo,
            new NullLogger()
        );
    }

    private function makeUser(int $id = 1): User
    {
        return new User($id, GoogleId::fromString('1234567890'), Email::fromString('u@test.com'), 'Test');
    }

    #[Test]
    public function successfully_deletes_game(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(true);
        $this->userGameRepo->expects($this->once())->method('remove')
            ->with(1, 12345)->willReturn(true);

        $command = new DeleteGameCommand(userId: 1, gameId: 12345);
        $result = $this->useCase->execute($command);

        $this->assertTrue($result);
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

        $command = new DeleteGameCommand(userId: 999, gameId: 12345);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_game_not_in_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->method('hasGame')->willReturn(false);

        $command = new DeleteGameCommand(userId: 1, gameId: 12345);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Game not found in your library');
        $this->useCase->execute($command);
    }
}
