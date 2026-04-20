<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Queries;

use App\Domain\DTO\Queries\GetEditionNoteQuery;
use App\Domain\DTO\Queries\GetEditionNotesQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EditionQueriesTest extends TestCase
{
    // ═══════════════════════════════════════
    // GetEditionNoteQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_edition_note_constructor(): void
    {
        $q = new GetEditionNoteQuery(noteId: 42, userId: 1);

        $this->assertSame(42, $q->noteId);
        $this->assertSame(1, $q->userId);
    }

    #[Test]
    public function get_edition_note_from_array_note_id(): void
    {
        $q = GetEditionNoteQuery::fromArray(['noteId' => 42], 1);

        $this->assertSame(42, $q->noteId);
        $this->assertSame(1, $q->userId);
    }

    #[Test]
    public function get_edition_note_from_array_snake_case(): void
    {
        $q = GetEditionNoteQuery::fromArray(['note_id' => 99], 2);
        $this->assertSame(99, $q->noteId);
    }

    #[Test]
    public function get_edition_note_from_array_id_fallback(): void
    {
        $q = GetEditionNoteQuery::fromArray(['id' => 5], 3);
        $this->assertSame(5, $q->noteId);
    }

    #[Test]
    public function get_edition_note_from_array_defaults(): void
    {
        $q = GetEditionNoteQuery::fromArray([], 1);
        $this->assertSame(0, $q->noteId);
    }

    // ═══════════════════════════════════════
    // GetEditionNotesQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_edition_notes_constructor(): void
    {
        $q = new GetEditionNotesQuery(userId: 1, userEditionId: 10, noteType: 'highlight', pageNumber: 42);

        $this->assertSame(1, $q->userId);
        $this->assertSame(10, $q->userEditionId);
        $this->assertEquals('highlight', $q->noteType);
        $this->assertSame(42, $q->pageNumber);
    }

    #[Test]
    public function get_edition_notes_defaults(): void
    {
        $q = new GetEditionNotesQuery(userId: 1, userEditionId: 10);

        $this->assertNull($q->noteType);
        $this->assertNull($q->pageNumber);
    }

    #[Test]
    public function get_edition_notes_from_array_camel_case(): void
    {
        $q = GetEditionNotesQuery::fromArray([
            'userEditionId' => 10,
            'noteType' => 'highlight',
            'pageNumber' => 42,
        ], 1);

        $this->assertSame(10, $q->userEditionId);
        $this->assertEquals('highlight', $q->noteType);
        $this->assertSame(42, $q->pageNumber);
    }

    #[Test]
    public function get_edition_notes_from_array_snake_case(): void
    {
        $q = GetEditionNotesQuery::fromArray([
            'user_edition_id' => 20,
            'note_type' => 'note',
            'page_number' => 100,
        ], 2);

        $this->assertSame(20, $q->userEditionId);
        $this->assertEquals('note', $q->noteType);
        $this->assertSame(100, $q->pageNumber);
    }

    #[Test]
    public function get_edition_notes_from_array_defaults(): void
    {
        $q = GetEditionNotesQuery::fromArray([], 1);

        $this->assertSame(0, $q->userEditionId);
        $this->assertNull($q->noteType);
        $this->assertNull($q->pageNumber);
    }
}
