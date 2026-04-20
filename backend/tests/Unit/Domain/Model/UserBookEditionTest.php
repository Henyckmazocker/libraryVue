<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\UserBookEdition;
use App\Domain\Model\ValueObjects\Rating;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserBookEditionTest extends TestCase
{
    // ── Constructor defaults ──

    #[Test]
    public function creates_with_defaults(): void
    {
        $ube = new UserBookEdition(1, 10);
        $this->assertSame(1, $ube->getUserId());
        $this->assertSame(10, $ube->getEditionId());
        $this->assertNull($ube->getId());
        $this->assertSame(0, $ube->getCurrentPage());
        $this->assertEquals('physical', $ube->getOwnershipType());
        $this->assertFalse($ube->isDigital());
        $this->assertSame(0, $ube->getTotalSessionsCompleted());
        $this->assertNull($ube->getConsumedAt());
        $this->assertNull($ube->getEditionRating());
        $this->assertNull($ube->getWorkRating());
        $this->assertNull($ube->getCondition());
        $this->assertNull($ube->getLocation());
        $this->assertNull($ube->getPersonalNotes());
        $this->assertNull($ube->getActiveReadingSessionId());
    }

    #[Test]
    public function creates_with_id(): void
    {
        $ube = new UserBookEdition(1, 10, 99);
        $this->assertSame(99, $ube->getId());
    }

    // ── Current page ──

    #[Test]
    public function set_current_page(): void
    {
        $ube = new UserBookEdition(1, 10);
        $ube->setCurrentPage(150);
        $this->assertSame(150, $ube->getCurrentPage());
    }

    #[Test]
    public function set_current_page_allows_zero(): void
    {
        $ube = new UserBookEdition(1, 10);
        $ube->setCurrentPage(100);
        $ube->setCurrentPage(0);
        $this->assertSame(0, $ube->getCurrentPage());
    }

    #[Test]
    public function set_current_page_throws_on_negative(): void
    {
        $ube = new UserBookEdition(1, 10);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-negative');
        $ube->setCurrentPage(-1);
    }

    // ── Consumed at ──

    #[Test]
    public function mark_as_consumed_and_unmark(): void
    {
        $ube = new UserBookEdition(1, 10);
        $this->assertNull($ube->getConsumedAt());

        $ube->markAsConsumed();
        $this->assertNotNull($ube->getConsumedAt());

        $ube->unmarkAsConsumed();
        $this->assertNull($ube->getConsumedAt());
    }

    // ── Ratings ──

    #[Test]
    public function set_edition_and_work_rating(): void
    {
        $ube = new UserBookEdition(1, 10);
        $ube->setEditionRating(Rating::fromFloat(4.0));
        $ube->setWorkRating(Rating::fromFloat(5.0));

        $this->assertSame(4.0, $ube->getEditionRating()->toFloat());
        $this->assertSame(5.0, $ube->getWorkRating()->toFloat());
    }

    #[Test]
    public function set_rating_to_null(): void
    {
        $ube = new UserBookEdition(1, 10);
        $ube->setEditionRating(Rating::fromFloat(3.0));
        $ube->setEditionRating(null);
        $this->assertNull($ube->getEditionRating());
    }

    // ── Ownership type ──

    #[Test]
    public function set_valid_ownership_types(): void
    {
        $ube = new UserBookEdition(1, 10);
        foreach (['physical', 'ebook', 'audiobook', 'borrowed', 'wishlist'] as $type) {
            $ube->setOwnershipType($type);
            $this->assertEquals($type, $ube->getOwnershipType());
        }
    }

    #[Test]
    public function set_invalid_ownership_type_throws(): void
    {
        $ube = new UserBookEdition(1, 10);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ownership type');
        $ube->setOwnershipType('invalid');
    }

    // ── Condition ──

    #[Test]
    public function set_valid_conditions(): void
    {
        $ube = new UserBookEdition(1, 10);
        foreach (['mint', 'like-new', 'very-good', 'good', 'acceptable', 'poor'] as $cond) {
            $ube->setCondition($cond);
            $this->assertEquals($cond, $ube->getCondition());
        }
    }

    #[Test]
    public function set_condition_null(): void
    {
        $ube = new UserBookEdition(1, 10);
        $ube->setCondition('mint');
        $ube->setCondition(null);
        $this->assertNull($ube->getCondition());
    }

    #[Test]
    public function set_invalid_condition_throws(): void
    {
        $ube = new UserBookEdition(1, 10);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid condition');
        $ube->setCondition('broken');
    }

    // ── Sessions ──

    #[Test]
    public function increment_sessions_completed(): void
    {
        $ube = new UserBookEdition(1, 10);
        $this->assertSame(0, $ube->getTotalSessionsCompleted());

        $ube->incrementSessionsCompleted();
        $this->assertSame(1, $ube->getTotalSessionsCompleted());

        $ube->incrementSessionsCompleted();
        $this->assertSame(2, $ube->getTotalSessionsCompleted());
    }

    #[Test]
    public function set_active_reading_session(): void
    {
        $ube = new UserBookEdition(1, 10);
        $ube->setActiveReadingSessionId(42);
        $this->assertSame(42, $ube->getActiveReadingSessionId());

        $ube->setActiveReadingSessionId(null);
        $this->assertNull($ube->getActiveReadingSessionId());
    }

    // ── toArray ──

    #[Test]
    public function to_array_contains_all_fields(): void
    {
        $ube = new UserBookEdition(1, 10, 5);
        $ube->setCurrentPage(50);
        $ube->setEditionRating(Rating::fromFloat(4.0));
        $ube->setOwnershipType('ebook');
        $ube->setCondition('like-new');
        $ube->setLocation('Shelf A');
        $ube->setIsDigital(true);
        $ube->setPersonalNotes('Notes');

        $arr = $ube->toArray();

        $this->assertSame(5, $arr['id']);
        $this->assertSame(1, $arr['user_id']);
        $this->assertSame(10, $arr['edition_id']);
        $this->assertSame(50, $arr['current_page']);
        $this->assertSame(4.0, $arr['edition_rating']);
        $this->assertEquals('ebook', $arr['ownership_type']);
        $this->assertEquals('like-new', $arr['condition']);
        $this->assertEquals('Shelf A', $arr['location']);
        $this->assertTrue($arr['is_digital']);
        $this->assertEquals('Notes', $arr['personal_notes']);
    }

    // ── toLegacyFormat ──

    #[Test]
    public function to_legacy_format_has_compatibility_keys(): void
    {
        $ube = new UserBookEdition(1, 10, 5);
        $ube->setCurrentPage(50);
        $ube->setEditionRating(Rating::fromFloat(4.5));
        $ube->setPersonalNotes('My notes');

        $legacy = $ube->toLegacyFormat();

        $this->assertSame(5, $legacy['user_edition_id']);
        $this->assertSame(50, $legacy['current_page']);
        $this->assertSame(50, $legacy['currentPage']);
        $this->assertSame(4.5, $legacy['personal_rating']);
        $this->assertSame(4.5, $legacy['rating']);
        $this->assertSame(4.5, $legacy['user_rating']);
        $this->assertSame(4.5, $legacy['userRating']);
        $this->assertSame(4.5, $legacy['edition_rating']);
        $this->assertEquals('My notes', $legacy['personal_notes']);
        $this->assertEquals('My notes', $legacy['personalNotes']);
    }

    // ── fromArray ──

    #[Test]
    public function from_array_creates_user_book_edition(): void
    {
        $data = [
            'user_id' => 1,
            'edition_id' => 10,
            'id' => 42,
            'current_page' => 100,
            'edition_rating' => 3.5,
            'work_rating' => 4.0,
            'ownership_type' => 'audiobook',
            'condition' => 'good',
            'location' => 'Shelf B',
            'is_digital' => true,
            'personal_notes' => 'Great read',
        ];

        $ube = UserBookEdition::fromArray($data);

        $this->assertSame(42, $ube->getId());
        $this->assertSame(1, $ube->getUserId());
        $this->assertSame(10, $ube->getEditionId());
        $this->assertSame(100, $ube->getCurrentPage());
        $this->assertSame(3.5, $ube->getEditionRating()->toFloat());
        $this->assertSame(4.0, $ube->getWorkRating()->toFloat());
        $this->assertEquals('audiobook', $ube->getOwnershipType());
        $this->assertEquals('good', $ube->getCondition());
        $this->assertEquals('Shelf B', $ube->getLocation());
        $this->assertTrue($ube->isDigital());
        $this->assertEquals('Great read', $ube->getPersonalNotes());
    }

    #[Test]
    public function from_array_minimal(): void
    {
        $data = ['user_id' => 1, 'edition_id' => 10];
        $ube = UserBookEdition::fromArray($data);

        $this->assertSame(1, $ube->getUserId());
        $this->assertSame(10, $ube->getEditionId());
        $this->assertNull($ube->getId());
        $this->assertSame(0, $ube->getCurrentPage());
    }
}
