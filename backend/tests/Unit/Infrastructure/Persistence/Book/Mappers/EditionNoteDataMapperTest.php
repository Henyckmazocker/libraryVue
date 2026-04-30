<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Book\Mappers;

use App\Infrastructure\Persistence\Book\Mappers\EditionNoteDataMapper;
use App\Domain\Model\EditionNote;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EditionNoteDataMapperTest extends TestCase
{
    private EditionNoteDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new EditionNoteDataMapper();
    }

    private function fullDbRow(): array
    {
        return [
            'id' => 42,
            'user_id' => 1,
            'user_edition_id' => 10,
            'page_number' => 150,
            'note_text' => 'Important passage',
            'note_type' => 'note',
            'is_private' => 1,
            'created_at' => '2024-06-15 10:30:00',
            'updated_at' => '2024-06-15 12:00:00',
        ];
    }

    // ── toDomain ──

    #[Test]
    public function to_domain_maps_all_fields(): void
    {
        $note = $this->mapper->toDomain($this->fullDbRow());

        $this->assertInstanceOf(EditionNote::class, $note);
        $this->assertSame(42, $note->getId());
        $this->assertSame(1, $note->getUserId());
        $this->assertSame(10, $note->getUserEditionId());
        $this->assertSame(150, $note->getPageNumber());
        $this->assertEquals('Important passage', $note->getNoteText());
        $this->assertEquals('note', $note->getNoteType());
        $this->assertTrue($note->isPrivate());
    }

    #[Test]
    public function to_domain_minimal_row(): void
    {
        $row = [
            'user_id' => 2,
            'user_edition_id' => 5,
            'page_number' => 1,
        ];

        $note = $this->mapper->toDomain($row);

        $this->assertSame(2, $note->getUserId());
        $this->assertSame(5, $note->getUserEditionId());
        $this->assertSame(1, $note->getPageNumber());
        $this->assertNull($note->getNoteText());
        $this->assertEquals('progress', $note->getNoteType());
        $this->assertTrue($note->isPrivate());
        $this->assertNull($note->getId());
    }

    #[Test]
    public function to_domain_is_private_false(): void
    {
        $row = $this->fullDbRow();
        $row['is_private'] = 0;

        $note = $this->mapper->toDomain($row);
        $this->assertFalse($note->isPrivate());
    }

    #[Test]
    public function to_domain_sets_timestamps(): void
    {
        $note = $this->mapper->toDomain($this->fullDbRow());
        $arr = $note->toArray();

        $this->assertNotNull($arr['created_at']);
        $this->assertNotNull($arr['updated_at']);
    }

    // ── toDomainCollection ──

    #[Test]
    public function to_domain_collection_maps_multiple_rows(): void
    {
        $rows = [
            $this->fullDbRow(),
            array_merge($this->fullDbRow(), ['id' => 43, 'page_number' => 200]),
        ];

        $notes = $this->mapper->toDomainCollection($rows);

        $this->assertCount(2, $notes);
        $this->assertSame(42, $notes[0]->getId());
        $this->assertSame(43, $notes[1]->getId());
        $this->assertSame(200, $notes[1]->getPageNumber());
    }

    #[Test]
    public function to_domain_collection_empty(): void
    {
        $notes = $this->mapper->toDomainCollection([]);
        $this->assertEmpty($notes);
    }

    // ── toDatabase ──

    #[Test]
    public function to_database_maps_all_fields(): void
    {
        $note = $this->mapper->toDomain($this->fullDbRow());
        $data = $this->mapper->toDatabase($note);

        $this->assertSame(42, $data['id']);
        $this->assertSame(1, $data['user_id']);
        $this->assertSame(10, $data['user_edition_id']);
        $this->assertSame(150, $data['page_number']);
        $this->assertEquals('Important passage', $data['note_text']);
        $this->assertEquals('note', $data['note_type']);
        $this->assertSame(1, $data['is_private']);
    }

    #[Test]
    public function to_database_without_id(): void
    {
        $row = $this->fullDbRow();
        unset($row['id']);

        $note = $this->mapper->toDomain($row);
        $data = $this->mapper->toDatabase($note);

        $this->assertArrayNotHasKey('id', $data);
    }

    #[Test]
    public function to_database_is_private_as_int(): void
    {
        $row = $this->fullDbRow();
        $row['is_private'] = 0;

        $note = $this->mapper->toDomain($row);
        $data = $this->mapper->toDatabase($note);

        $this->assertSame(0, $data['is_private']);
    }

    // ── Round-trip ──

    #[Test]
    public function round_trip_preserves_data(): void
    {
        $original = $this->fullDbRow();
        $note = $this->mapper->toDomain($original);
        $data = $this->mapper->toDatabase($note);

        $this->assertSame((int) $original['id'], $data['id']);
        $this->assertSame((int) $original['user_id'], $data['user_id']);
        $this->assertSame((int) $original['user_edition_id'], $data['user_edition_id']);
        $this->assertSame((int) $original['page_number'], $data['page_number']);
        $this->assertEquals($original['note_text'], $data['note_text']);
        $this->assertEquals($original['note_type'], $data['note_type']);
    }
}
