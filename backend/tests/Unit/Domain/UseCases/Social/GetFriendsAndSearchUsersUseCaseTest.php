<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Social;

use App\Domain\DTO\Queries\GetFriendsQuery;
use App\Domain\DTO\Queries\SearchUsersQuery;
use App\Domain\Model\Friendship;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\Social\GetFriendsUseCase;
use App\Domain\UseCases\Social\SearchUsersUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetFriendsAndSearchUsersUseCaseTest extends TestCase
{
    private FriendshipRepositoryInterface $friendshipRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->friendshipRepo = $this->createMock(FriendshipRepositoryInterface::class);
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
    }

    private function makeUser(int $id, string $username = 'alice'): User
    {
        return new User($id, GoogleId::fromString('1000000000'), Email::fromString("{$username}@test.com"), $username, null, null, null, null, null, true, null, $username);
    }

    // ─── GetFriendsUseCase ────────────────────────────────

    #[Test]
    public function get_friends_throws_on_invalid_query(): void
    {
        $useCase = new GetFriendsUseCase($this->friendshipRepo, $this->userRepo, new NullLogger());
        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new \stdClass());
    }

    #[Test]
    public function get_friends_returns_empty_array_when_no_friendships(): void
    {
        $this->friendshipRepo->method('findAcceptedByUser')->willReturn([]);
        $useCase = new GetFriendsUseCase($this->friendshipRepo, $this->userRepo, new NullLogger());

        $result = $useCase->execute(new GetFriendsQuery(1));

        $this->assertSame([], $result);
    }

    #[Test]
    public function get_friends_returns_friend_list_with_correct_ids(): void
    {
        $friendship = new Friendship(10, 1, 2, Friendship::STATUS_ACCEPTED);
        $this->friendshipRepo->method('findAcceptedByUser')->willReturn([$friendship]);
        $this->userRepo->method('findById')->willReturn($this->makeUser(2, 'bob'));

        $useCase = new GetFriendsUseCase($this->friendshipRepo, $this->userRepo, new NullLogger());
        $result  = $useCase->execute(new GetFriendsQuery(1));

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['id']);
        $this->assertSame('bob', $result[0]['username']);
    }

    #[Test]
    public function get_friends_skips_users_that_no_longer_exist(): void
    {
        $friendship = new Friendship(10, 1, 2, Friendship::STATUS_ACCEPTED);
        $this->friendshipRepo->method('findAcceptedByUser')->willReturn([$friendship]);
        $this->userRepo->method('findById')->willReturn(null);

        $useCase = new GetFriendsUseCase($this->friendshipRepo, $this->userRepo, new NullLogger());
        $result  = $useCase->execute(new GetFriendsQuery(1));

        $this->assertSame([], $result);
    }

    // ─── SearchUsersUseCase ───────────────────────────────

    #[Test]
    public function search_users_throws_on_invalid_query(): void
    {
        $useCase = new SearchUsersUseCase($this->userRepo, $this->friendshipRepo, new NullLogger());
        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new \stdClass());
    }

    #[Test]
    public function search_users_returns_empty_when_no_results(): void
    {
        $this->userRepo->method('searchByUsername')->willReturn([]);
        $useCase = new SearchUsersUseCase($this->userRepo, $this->friendshipRepo, new NullLogger());

        $result = $useCase->execute(new SearchUsersQuery('alice', 1));

        $this->assertSame([], $result);
    }

    #[Test]
    public function search_users_returns_friend_status_none_when_no_friendship(): void
    {
        $this->userRepo->method('searchByUsername')->willReturn([$this->makeUser(2, 'alice')]);
        $this->friendshipRepo->method('findByUsers')->willReturn(null);

        $useCase = new SearchUsersUseCase($this->userRepo, $this->friendshipRepo, new NullLogger());
        $result  = $useCase->execute(new SearchUsersQuery('alice', 1));

        $this->assertSame('none', $result[0]['friend_status']);
    }

    #[Test]
    public function search_users_returns_pending_sent_when_requester_is_current_user(): void
    {
        $this->userRepo->method('searchByUsername')->willReturn([$this->makeUser(2, 'alice')]);
        $friendship = new Friendship(5, 1, 2, Friendship::STATUS_PENDING); // requester = 1 (current)
        $this->friendshipRepo->method('findByUsers')->willReturn($friendship);

        $useCase = new SearchUsersUseCase($this->userRepo, $this->friendshipRepo, new NullLogger());
        $result  = $useCase->execute(new SearchUsersQuery('alice', 1));

        $this->assertSame('pending_sent', $result[0]['friend_status']);
    }

    #[Test]
    public function search_query_throws_when_term_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SearchUsersQuery('a', 1);
    }
}
