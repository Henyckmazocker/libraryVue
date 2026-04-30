<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Games;

use App\Domain\UseCases\Games\GetGamesUseCase;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetGamesUseCaseTest extends TestCase
{
    private GetGamesUseCase $useCase;
    private UserGameRepositoryInterface $userGameRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->userGameRepo = $this->createMock(UserGameRepositoryInterface::class);
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);

        $this->useCase = new GetGamesUseCase(
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
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute('not an array');
    }

    #[Test]
    public function throws_when_missing_user_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(['filters' => []]);
    }

    #[Test]
    public function throws_when_user_not_found(): void
    {
        $this->userRepo->method('findById')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute(['userId' => 999]);
    }

    #[Test]
    public function successfully_returns_games(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $games = [
            ['id' => 1, 'name' => 'Game A'],
            ['id' => 2, 'name' => 'Game B'],
        ];
        $this->userGameRepo->method('findByUser')->with(1, [])->willReturn($games);

        $result = $this->useCase->execute(['userId' => 1]);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function passes_filters_to_repository(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userGameRepo->expects($this->once())->method('findByUser')
            ->with(1, ['status' => 'playing'])
            ->willReturn([]);

        $this->useCase->execute(['userId' => 1, 'filters' => ['status' => 'playing']]);
    }
}
