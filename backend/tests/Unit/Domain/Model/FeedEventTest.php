<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\FeedEvent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FeedEventTest extends TestCase
{
    private const YOUTUBE_ID = 'dQw4w9WgXcQ';

    private function makeEvent(array $overrides = []): FeedEvent
    {
        $defaults = [
            'id'           => 1,
            'userId'       => 42,
            'eventType'    => FeedEvent::TYPE_ITEM_ADDED,
            'entityType'   => FeedEvent::ENTITY_BOOK,
            'entityId'     => '9780141036144',
            'entityTitle'  => '1984',
            'entityCover'  => null,
            'metadata'     => null,
            'createdAt'    => null,
        ];

        $args = array_merge($defaults, $overrides);

        return new FeedEvent(
            $args['id'],
            $args['userId'],
            $args['eventType'],
            $args['entityType'],
            $args['entityId'],
            $args['entityTitle'],
            $args['entityCover'],
            $args['metadata'],
            $args['createdAt']
        );
    }

    // ── Entity types ──

    #[Test]
    public function accepts_video_as_entity_type(): void
    {
        $event = $this->makeEvent([
            'entityType' => FeedEvent::ENTITY_VIDEO,
            'entityId'   => self::YOUTUBE_ID,
        ]);

        $this->assertEquals('video', $event->getEntityType());
    }

    #[Test]
    #[DataProvider('validEntityTypes')]
    public function accepts_every_entity_type_of_the_library(string $entityType): void
    {
        $event = $this->makeEvent(['entityType' => $entityType]);

        $this->assertEquals($entityType, $event->getEntityType());
    }

    public static function validEntityTypes(): array
    {
        return [
            'book'  => [FeedEvent::ENTITY_BOOK],
            'movie' => [FeedEvent::ENTITY_MOVIE],
            'game'  => [FeedEvent::ENTITY_GAME],
            'album' => [FeedEvent::ENTITY_ALBUM],
            'video' => [FeedEvent::ENTITY_VIDEO],
        ];
    }

    #[Test]
    public function rejects_an_unknown_entity_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid entity type: gramophone');

        $this->makeEvent(['entityType' => 'gramophone']);
    }

    #[Test]
    public function accepts_a_null_entity_type(): void
    {
        $event = $this->makeEvent(['entityType' => null, 'entityId' => null]);

        $this->assertNull($event->getEntityType());
    }

    #[Test]
    public function rejects_an_unknown_event_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid event type: item_teleported');

        $this->makeEvent(['eventType' => 'item_teleported']);
    }

    // ── Serialization ──

    #[Test]
    public function serializes_a_video_event_keeping_the_youtube_id_as_entity_id(): void
    {
        $event = $this->makeEvent([
            'entityType'  => FeedEvent::ENTITY_VIDEO,
            'entityId'    => self::YOUTUBE_ID,
            'entityTitle' => 'Never Gonna Give You Up',
            'entityCover' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ]);

        $this->assertEquals([
            'id'           => 1,
            'user_id'      => 42,
            'event_type'   => FeedEvent::TYPE_ITEM_ADDED,
            'entity_type'  => 'video',
            'entity_id'    => self::YOUTUBE_ID,
            'entity_title' => 'Never Gonna Give You Up',
            'entity_cover' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            'metadata'     => null,
            'created_at'   => null,
        ], $event->toArray());
    }

    // ── Metadata ──

    #[Test]
    public function reads_metadata_values_with_a_default(): void
    {
        $event = $this->makeEvent([
            'eventType'  => FeedEvent::TYPE_ITEM_RATED,
            'entityType' => FeedEvent::ENTITY_VIDEO,
            'entityId'   => self::YOUTUBE_ID,
            'metadata'   => ['rating' => 4.5],
        ]);

        $this->assertEquals(4.5, $event->getMetadataValue('rating'));
        $this->assertNull($event->getMetadataValue('old_status'));
        $this->assertEquals('none', $event->getMetadataValue('old_status', 'none'));
    }
}
