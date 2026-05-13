<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Social;

use App\Domain\DTO\Commands\RemoveFriendCommand;
use App\Domain\Model\Friendship;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\UseCases\Social\RemoveFriendUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;
use RuntimeException;

class RemoveFriendUseCaseTest extends TestCase
{
    private RemoveFriendUseCase $useCase;
    private FriendshipRepositoryInterface $friendshipRepo;

    protected function setUp(): void
    {
        $this->friendshipRepo = $this->createMock(FriendshipRepositoryInterface::class);
        $this->useCase = new RemoveFriendUseCase($this->friendshipRepo, new NullLogger());
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_when_friendship_not_found(): void
    {
        $this->friendshipRepo->method('findByUsers')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Friendship not found');
        $this->useCase->execute(new RemoveFriendCommand(1, 2));
    }

    #[Test]
    public function throws_when_friendship_is_not_accepted(): void
    {
        $pending = new Friendship(5, 1, 2, Friendship::STATUS_PENDING);
        $this->friendshipRepo->method('findByUsers')->willReturn($pending);

        $this->expectException(RuntimeException::class);
        $this->useCase->execute(new RemoveFriendCommand(1, 2));
    }

    #[Test]
    public function deletes_accepted_friendship(): void
    {
        $friendship = new Friendship(5, 1, 2, Friendship::STATUS_ACCEPTED);
        $this->friendshipRepo->method('findByUsers')->willReturn($friendship);
        $this->friendshipRepo->expects($this->once())->method('delete')->with(5);

        $result = $this->useCase->execute(new RemoveFriendCommand(1, 2));

        $this->assertTrue($result);
    }

    #[Test]
    public function command_throws_when_user_equals_friend(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RemoveFriendCommand(1, 1);
    }
}
