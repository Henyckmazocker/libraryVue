<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Social;

use App\Domain\DTO\Queries\GetFeedQuery;
use App\Domain\Model\Friendship;
use App\Domain\Model\PrivacySettings;
use App\Domain\Repository\Social\FeedEventRepositoryInterface;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\Social\PrivacySettingsRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\Social\GetFeedUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetFeedUseCaseTest extends TestCase
{
    private GetFeedUseCase $useCase;
    private FriendshipRepositoryInterface $friendshipRepo;
    private FeedEventRepositoryInterface $feedEventRepo;
    private PrivacySettingsRepositoryInterface $privacyRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->friendshipRepo = $this->createMock(FriendshipRepositoryInterface::class);
        $this->feedEventRepo  = $this->createMock(FeedEventRepositoryInterface::class);
        $this->privacyRepo    = $this->createMock(PrivacySettingsRepositoryInterface::class);
        $this->userRepo       = $this->createMock(UserRepositoryInterface::class);

        $this->useCase = new GetFeedUseCase(
            $this->friendshipRepo,
            $this->feedEventRepo,
            $this->privacyRepo,
            $this->userRepo,
            new NullLogger()
        );
    }

    #[Test]
    public function throws_on_invalid_query(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function returns_empty_feed_when_user_has_no_friends(): void
    {
        $this->friendshipRepo->method('findAcceptedByUser')->willReturn([]);

        $result = $this->useCase->execute(new GetFeedQuery(1));

        $this->assertSame([], $result['events']);
        $this->assertFalse($result['hasMore']);
    }

    #[Test]
    public function returns_empty_feed_when_all_friends_have_private_settings(): void
    {
        $friendship = new Friendship(10, 1, 2, Friendship::STATUS_ACCEPTED);
        $this->friendshipRepo->method('findAcceptedByUser')->willReturn([$friendship]);

        // All events hidden
        $privateSettings = new PrivacySettings(
            userId: 2,
            showAdditions: false,
            showStatusChanges: false,
            showRatings: false,
            showNotes: false,
            showReadingSessions: false,
            showAchievements: false
        );
        $this->privacyRepo->method('findByUserId')->willReturn($privateSettings);

        $result = $this->useCase->execute(new GetFeedQuery(1));

        $this->assertSame([], $result['events']);
        $this->assertFalse($result['hasMore']);
    }

    #[Test]
    public function returns_events_and_hasMore_correctly(): void
    {
        $friendship = new Friendship(10, 1, 2, Friendship::STATUS_ACCEPTED);
        $this->friendshipRepo->method('findAcceptedByUser')->willReturn([$friendship]);

        $settings = new PrivacySettings(userId: 2); // defaults: additions + statuses + ratings visible
        $this->privacyRepo->method('findByUserId')->willReturn($settings);

        $fakeEvents = [['id' => 1, 'event_type' => 'item_added']];
        $this->feedEventRepo->method('countFeedEvents')->willReturn(25);
        $this->feedEventRepo->method('findFeedEvents')->willReturn($fakeEvents);

        $result = $this->useCase->execute(new GetFeedQuery(userId: 1, limit: 20, offset: 0));

        $this->assertCount(1, $result['events']);
        $this->assertTrue($result['hasMore']);   // 0 + 20 < 25
        $this->assertSame(25, $result['total']);
    }

    #[Test]
    public function hasMore_is_false_when_all_events_fetched(): void
    {
        $friendship = new Friendship(10, 1, 2, Friendship::STATUS_ACCEPTED);
        $this->friendshipRepo->method('findAcceptedByUser')->willReturn([$friendship]);
        $this->privacyRepo->method('findByUserId')->willReturn(new PrivacySettings(userId: 2));

        $this->feedEventRepo->method('countFeedEvents')->willReturn(5);
        $this->feedEventRepo->method('findFeedEvents')->willReturn([]);

        $result = $this->useCase->execute(new GetFeedQuery(userId: 1, limit: 20, offset: 0));

        $this->assertFalse($result['hasMore']); // 0 + 20 >= 5
    }

    #[Test]
    public function returns_video_events_alongside_the_other_entities(): void
    {
        $friendship = new Friendship(10, 1, 2, Friendship::STATUS_ACCEPTED);
        $this->friendshipRepo->method('findAcceptedByUser')->willReturn([$friendship]);
        $this->privacyRepo->method('findByUserId')->willReturn(new PrivacySettings(userId: 2));

        $mixedFeed = [
            ['id' => 1, 'event_type' => 'item_added', 'entity_type' => 'book',  'entity_id' => '9780141036144'],
            ['id' => 2, 'event_type' => 'item_added', 'entity_type' => 'movie', 'entity_id' => 'tt0133093'],
            ['id' => 3, 'event_type' => 'item_added', 'entity_type' => 'game',  'entity_id' => '1020'],
            ['id' => 4, 'event_type' => 'item_added', 'entity_type' => 'album', 'entity_id' => '4aawyAB9vmqN3uQ7FjRGTy'],
            ['id' => 5, 'event_type' => 'item_added', 'entity_type' => 'video', 'entity_id' => 'dQw4w9WgXcQ'],
        ];
        $this->feedEventRepo->method('countFeedEvents')->willReturn(5);
        $this->feedEventRepo->method('findFeedEvents')->willReturn($mixedFeed);

        $result = $this->useCase->execute(new GetFeedQuery(userId: 1, limit: 20, offset: 0));

        $this->assertCount(5, $result['events']);

        $entityTypes = array_column($result['events'], 'entity_type');
        $this->assertContains('video', $entityTypes);
        $this->assertSame(['book', 'movie', 'game', 'album', 'video'], $entityTypes);

        // El entity_id de un vídeo es su youtube_id, no el autoincremental
        $videoEvent = end($result['events']);
        $this->assertSame('dQw4w9WgXcQ', $videoEvent['entity_id']);
    }

    #[Test]
    public function query_fromArray_uses_defaults(): void
    {
        $query = GetFeedQuery::fromArray([], 7);

        $this->assertSame(7, $query->userId);
        $this->assertSame(20, $query->limit);
        $this->assertSame(0, $query->offset);
    }
}
