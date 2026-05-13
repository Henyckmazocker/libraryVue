<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Social;

use App\Domain\DTO\Commands\AcceptFriendRequestCommand;
use App\Domain\DTO\Commands\RejectFriendRequestCommand;
use App\Domain\Model\Friendship;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\UseCases\Social\AcceptFriendRequestUseCase;
use App\Domain\UseCases\Social\RejectFriendRequestUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;
use RuntimeException;

class AcceptRejectFriendRequestUseCaseTest extends TestCase
{
    private FriendshipRepositoryInterface $friendshipRepo;

    protected function setUp(): void
    {
        $this->friendshipRepo = $this->createMock(FriendshipRepositoryInterface::class);
    }

    // ─── Accept ──────────────────────────────────────────

    #[Test]
    public function accept_throws_on_invalid_command(): void
    {
        $useCase = new AcceptFriendRequestUseCase($this->friendshipRepo, new NullLogger());
        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new \stdClass());
    }

    #[Test]
    public function accept_throws_when_request_not_found(): void
    {
        $this->friendshipRepo->method('findPendingRequestsForUser')->willReturn([]);

        $useCase = new AcceptFriendRequestUseCase($this->friendshipRepo, new NullLogger());
        $command = new AcceptFriendRequestCommand(999, 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Friend request not found');
        $useCase->execute($command);
    }

    #[Test]
    public function accept_updates_friendship_to_accepted(): void
    {
        $friendship = new Friendship(10, 2, 1, Friendship::STATUS_PENDING);
        $this->friendshipRepo->method('findPendingRequestsForUser')->willReturn([$friendship]);
        $this->friendshipRepo->expects($this->once())->method('update');

        $useCase = new AcceptFriendRequestUseCase($this->friendshipRepo, new NullLogger());
        $command = new AcceptFriendRequestCommand(10, 1);

        $result = $useCase->execute($command);

        $this->assertInstanceOf(Friendship::class, $result);
        $this->assertTrue($result->isAccepted());
    }

    // ─── Reject ──────────────────────────────────────────

    #[Test]
    public function reject_throws_on_invalid_command(): void
    {
        $useCase = new RejectFriendRequestUseCase($this->friendshipRepo, new NullLogger());
        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new \stdClass());
    }

    #[Test]
    public function reject_throws_when_request_not_found(): void
    {
        $this->friendshipRepo->method('findPendingRequestsForUser')->willReturn([]);

        $useCase = new RejectFriendRequestUseCase($this->friendshipRepo, new NullLogger());
        $command = new RejectFriendRequestCommand(999, 1);

        $this->expectException(RuntimeException::class);
        $useCase->execute($command);
    }

    #[Test]
    public function reject_sets_friendship_to_rejected(): void
    {
        $friendship = new Friendship(10, 2, 1, Friendship::STATUS_PENDING);
        $this->friendshipRepo->method('findPendingRequestsForUser')->willReturn([$friendship]);
        $this->friendshipRepo->expects($this->once())->method('update');

        $useCase = new RejectFriendRequestUseCase($this->friendshipRepo, new NullLogger());
        $command = new RejectFriendRequestCommand(10, 1);

        $useCase->execute($command);

        $this->assertSame(Friendship::STATUS_REJECTED, $friendship->getStatus());
    }
}
