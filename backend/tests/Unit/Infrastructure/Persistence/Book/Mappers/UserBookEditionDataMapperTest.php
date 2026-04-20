<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Book\Mappers;

use App\Infrastructure\Persistence\Book\Mappers\UserBookEditionDataMapper;
use App\Domain\Model\UserBookEdition;
use App\Domain\Model\ValueObjects\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserBookEditionDataMapperTest extends TestCase
{
    private UserBookEditionDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new UserBookEditionDataMapper();
    }

    private function fullDbRow(): array
    {
        return [
            'id' => 10,
            'user_id' => 1,
            'edition_id' => 5,
            'current_page' => 150,
            'consumed_at' => '2024-06-01 12:00:00',
            'active_reading_session_id' => 42,
            'edition_rating' => 4.5,
            'work_rating' => 3.0,
            'ownership_type' => 'ebook',
            'condition' => 'good',
            'location' => 'Shelf A',
            'is_digital' => 1,
            'personal_notes' => 'Great edition',
        ];
    }

    // ── toDomain ──

    #[Test]
    public function to_domain_maps_all_fields(): void
    {
        $entity = $this->mapper->toDomain($this->fullDbRow());

        $this->assertInstanceOf(UserBookEdition::class, $entity);
        $this->assertSame(10, $entity->getId());
        $this->assertSame(1, $entity->getUserId());
        $this->assertSame(5, $entity->getEditionId());
        $this->assertSame(150, $entity->getCurrentPage());
        $this->assertNotNull($entity->getConsumedAt());
        $this->assertSame(42, $entity->getActiveReadingSessionId());
        $this->assertNotNull($entity->getEditionRating());
        $this->assertSame(4.5, $entity->getEditionRating()->toFloat());
        $this->assertNotNull($entity->getWorkRating());
        $this->assertSame(3.0, $entity->getWorkRating()->toFloat());
        $this->assertEquals('ebook', $entity->getOwnershipType());
        $this->assertEquals('good', $entity->getCondition());
        $this->assertEquals('Shelf A', $entity->getLocation());
        $this->assertTrue($entity->isDigital());
        $this->assertEquals('Great edition', $entity->getPersonalNotes());
    }

    #[Test]
    public function to_domain_minimal_row(): void
    {
        $row = [
            'user_id' => 2,
            'edition_id' => 8,
        ];

        $entity = $this->mapper->toDomain($row);

        $this->assertSame(2, $entity->getUserId());
        $this->assertSame(8, $entity->getEditionId());
        $this->assertNull($entity->getId());
        $this->assertSame(0, $entity->getCurrentPage());
        $this->assertNull($entity->getConsumedAt());
        $this->assertNull($entity->getActiveReadingSessionId());
        $this->assertNull($entity->getEditionRating());
        $this->assertNull($entity->getWorkRating());
        $this->assertEquals('physical', $entity->getOwnershipType());
        $this->assertNull($entity->getCondition());
        $this->assertNull($entity->getLocation());
        $this->assertFalse($entity->isDigital());
        $this->assertNull($entity->getPersonalNotes());
    }

    #[Test]
    public function to_domain_consumed_at_triggers_mark_as_consumed(): void
    {
        $row = [
            'user_id' => 1,
            'edition_id' => 2,
            'consumed_at' => '2024-01-15 09:30:00',
        ];

        $entity = $this->mapper->toDomain($row);
        $this->assertNotNull($entity->getConsumedAt());
    }

    #[Test]
    public function to_domain_consumed_at_empty_string_ignored(): void
    {
        $row = [
            'user_id' => 1,
            'edition_id' => 2,
            'consumed_at' => '',
        ];

        $entity = $this->mapper->toDomain($row);
        $this->assertNull($entity->getConsumedAt());
    }

    #[Test]
    public function to_domain_is_digital_false(): void
    {
        $row = [
            'user_id' => 1,
            'edition_id' => 2,
            'is_digital' => 0,
        ];

        $entity = $this->mapper->toDomain($row);
        $this->assertFalse($entity->isDigital());
    }

    #[Test]
    public function to_domain_null_ratings_remain_null(): void
    {
        $row = [
            'user_id' => 1,
            'edition_id' => 2,
            'edition_rating' => null,
            'work_rating' => null,
        ];

        $entity = $this->mapper->toDomain($row);
        $this->assertNull($entity->getEditionRating());
        $this->assertNull($entity->getWorkRating());
    }

    // ── toDatabase ──

    #[Test]
    public function to_database_maps_all_fields(): void
    {
        $entity = $this->mapper->toDomain($this->fullDbRow());
        $data = $this->mapper->toDatabase($entity);

        $this->assertSame(10, $data['id']);
        $this->assertSame(1, $data['user_id']);
        $this->assertSame(5, $data['edition_id']);
        $this->assertSame(150, $data['current_page']);
        $this->assertIsString($data['added_at']);
        $this->assertSame(42, $data['active_reading_session_id']);
        $this->assertSame(4.5, $data['edition_rating']);
        $this->assertSame(3.0, $data['work_rating']);
        $this->assertEquals('ebook', $data['ownership_type']);
        $this->assertEquals('good', $data['condition']);
        $this->assertEquals('Shelf A', $data['location']);
        $this->assertSame(1, $data['is_digital']);
        $this->assertEquals('Great edition', $data['personal_notes']);
    }

    #[Test]
    public function to_database_null_consumed_at(): void
    {
        $row = [
            'user_id' => 1,
            'edition_id' => 2,
        ];

        $entity = $this->mapper->toDomain($row);
        $data = $this->mapper->toDatabase($entity);

        $this->assertNull($data['consumed_at']);
        $this->assertNull($data['edition_rating']);
        $this->assertNull($data['work_rating']);
    }

    #[Test]
    public function to_database_is_digital_converts_to_int(): void
    {
        $row = [
            'user_id' => 1,
            'edition_id' => 2,
            'is_digital' => 1,
        ];

        $entity = $this->mapper->toDomain($row);
        $data = $this->mapper->toDatabase($entity);

        $this->assertSame(1, $data['is_digital']);
    }

    #[Test]
    public function to_database_added_at_formatted(): void
    {
        $entity = $this->mapper->toDomain($this->fullDbRow());
        $data = $this->mapper->toDatabase($entity);

        // Check format is Y-m-d H:i:s
        $this->assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
            $data['added_at']
        );
    }

    // ── Round-trip ──

    #[Test]
    public function round_trip_preserves_core_data(): void
    {
        $original = $this->fullDbRow();
        $entity = $this->mapper->toDomain($original);
        $data = $this->mapper->toDatabase($entity);

        $this->assertSame((int) $original['id'], $data['id']);
        $this->assertSame((int) $original['user_id'], $data['user_id']);
        $this->assertSame((int) $original['edition_id'], $data['edition_id']);
        $this->assertSame((int) $original['current_page'], $data['current_page']);
        $this->assertSame((float) $original['edition_rating'], $data['edition_rating']);
        $this->assertSame((float) $original['work_rating'], $data['work_rating']);
        $this->assertEquals($original['ownership_type'], $data['ownership_type']);
        $this->assertEquals($original['condition'], $data['condition']);
        $this->assertEquals($original['location'], $data['location']);
        $this->assertEquals($original['personal_notes'], $data['personal_notes']);
    }
}
