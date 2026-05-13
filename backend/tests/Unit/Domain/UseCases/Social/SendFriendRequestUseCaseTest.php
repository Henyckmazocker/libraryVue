<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Social;

use App\Domain\DTO\Commands\SendFriendRequestCommand;
use App\Domain\Model\Friendship;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\UseCases\Social\SendFriendRequestUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;
use RuntimeException;

class SendFriendRequestUseCaseTest extends TestCase
{
    private SendFriendRequestUseCase $useCase;
    private FriendshipRepositoryInterface $friendshipRepo;

    protected function setUp(): void
    {
        $this->friendshipRepo = $this->createMock(FriendshipRepositoryInterface::class);
        $this->useCase = new SendFriendRequestUseCase($this->friendshipRepo, new NullLogger());
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_when_friendship_already_exists(): void
    {
        $existing = new Friendship(1, 1, 2, Friendship::STATUS_PENDING);
        $this->friendshipRepo->method('findByUsers')->willReturn($existing);

        $command = new SendFriendRequestCommand(1, 2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $this->useCase->execute($command);
    }

    #[Test]
    public function creates_pending_friendship_successfully(): void
    {
        $this->friendshipRepo->method('findByUsers')->willReturn(null);

        $saved = new Friendship(5, 1, 2, Friendship::STATUS_PENDING);
        $this->friendshipRepo->method('save')->willReturn($saved);

        $command = new SendFriendRequestCommand(1, 2);
        $result = $this->useCase->execute($command);

        $this->assertInstanceOf(Friendship::class, $result);
        $this->assertTrue($result->isPending());
        $this->assertSame(1, $result->getRequesterId());
        $this->assertSame(2, $result->getAddresseeId());
    }

    #[Test]
    public function command_throws_when_requester_equals_addressee(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SendFriendRequestCommand(1, 1);
    }
}
