<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\EditionNote;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EditionNoteTest extends TestCase
{
    // ── Constructor ──

    #[Test]
    public function creates_note_with_defaults(): void
    {
        $note = new EditionNote(1, 10, 42);
        $this->assertSame(1, $note->getUserId());
        $this->assertSame(10, $note->getUserEditionId());
        $this->assertSame(42, $note->getPageNumber());
        $this->assertNull($note->getNoteText());
        $this->assertEquals('progress', $note->getNoteType());
        $this->assertTrue($note->isPrivate());
        $this->assertNull($note->getId());
    }

    #[Test]
    public function creates_note_with_all_fields(): void
    {
        $note = new EditionNote(1, 10, 50, 'A quote from the book', 'quote', false, 99);
        $this->assertSame(99, $note->getId());
        $this->assertEquals('A quote from the book', $note->getNoteText());
        $this->assertEquals('quote', $note->getNoteType());
        $this->assertFalse($note->isPrivate());
    }

    #[Test]
    public function throws_on_zero_page(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page number must be positive');
        new EditionNote(1, 10, 0);
    }

    #[Test]
    public function throws_on_negative_page(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EditionNote(1, 10, -1);
    }

    #[Test]
    public function throws_on_invalid_note_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid note type');
        new EditionNote(1, 10, 1, 'text', 'invalid-type');
    }

    #[Test]
    public function throws_on_empty_string_note_text(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Note text cannot be empty');
        new EditionNote(1, 10, 1, '   ');
    }

    #[Test]
    public function allows_null_note_text(): void
    {
        $note = new EditionNote(1, 10, 1, null);
        $this->assertNull($note->getNoteText());
    }

    // ── Valid note types ──

    #[Test]
    public function all_valid_note_types_accepted(): void
    {
        $types = EditionNote::getValidNoteTypes();
        $this->assertCount(7, $types);

        foreach ($types as $type) {
            $note = new EditionNote(1, 10, 1, 'text', $type);
            $this->assertEquals($type, $note->getNoteType());
        }
    }

    // ── Setters ──

    #[Test]
    public function set_page_number_validates(): void
    {
        $note = new EditionNote(1, 10, 1);
        $note->setPageNumber(100);
        $this->assertSame(100, $note->getPageNumber());
    }

    #[Test]
    public function set_page_number_throws_on_zero(): void
    {
        $note = new EditionNote(1, 10, 1);
        $this->expectException(InvalidArgumentException::class);
        $note->setPageNumber(0);
    }

    #[Test]
    public function set_note_text_throws_on_empty(): void
    {
        $note = new EditionNote(1, 10, 1);
        $this->expectException(InvalidArgumentException::class);
        $note->setNoteText('');
    }

    #[Test]
    public function set_note_type_validates(): void
    {
        $note = new EditionNote(1, 10, 1);
        $note->setNoteType('quote');
        $this->assertEquals('quote', $note->getNoteType());
    }

    #[Test]
    public function set_note_type_throws_on_invalid(): void
    {
        $note = new EditionNote(1, 10, 1);
        $this->expectException(InvalidArgumentException::class);
        $note->setNoteType('invalid');
    }

    #[Test]
    public function set_id(): void
    {
        $note = new EditionNote(1, 10, 1);
        $this->assertNull($note->getId());
        $note->setId(42);
        $this->assertSame(42, $note->getId());
    }

    // ── toArray ──

    #[Test]
    public function to_array_contains_dual_format_keys(): void
    {
        $note = new EditionNote(1, 10, 50, 'Some text', 'note', false, 7);
        $arr = $note->toArray();

        // snake_case
        $this->assertSame(7, $arr['id']);
        $this->assertSame(1, $arr['user_id']);
        $this->assertSame(10, $arr['user_edition_id']);
        $this->assertSame(50, $arr['page_number']);
        $this->assertEquals('Some text', $arr['note_text']);
        $this->assertEquals('note', $arr['note_type']);
        $this->assertFalse($arr['is_private']);
        $this->assertNotEmpty($arr['created_at']);
        $this->assertNotEmpty($arr['updated_at']);

        // camelCase aliases
        $this->assertSame(1, $arr['userId']);
        $this->assertSame(10, $arr['userEditionId']);
        $this->assertSame(50, $arr['pageNumber']);
        $this->assertEquals('Some text', $arr['noteText']);
        $this->assertEquals('note', $arr['noteType']);
        $this->assertFalse($arr['isPrivate']);
    }
}
