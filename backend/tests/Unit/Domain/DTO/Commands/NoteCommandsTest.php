<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Commands;

use App\Domain\DTO\Commands\AddEditionNoteCommand;
use App\Domain\DTO\Commands\AddMovieNoteCommand;
use App\Domain\DTO\Commands\DeleteEditionNoteCommand;
use App\Domain\DTO\Commands\DeleteMovieNoteCommand;
use App\Domain\DTO\Commands\UpdateEditionNoteCommand;
use App\Domain\DTO\Commands\UpdateMovieNoteCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NoteCommandsTest extends TestCase
{
    // ═══════════════════════════════════════
    // AddEditionNoteCommand
    // ═══════════════════════════════════════

    #[Test]
    public function add_edition_note_constructor(): void
    {
        $cmd = new AddEditionNoteCommand(
            userId: 1,
            userEditionId: 10,
            pageNumber: 42,
            noteText: 'Interesting passage',
            noteType: 'highlight',
            isPrivate: false
        );

        $this->assertSame(1, $cmd->userId);
        $this->assertSame(10, $cmd->userEditionId);
        $this->assertSame(42, $cmd->pageNumber);
        $this->assertEquals('Interesting passage', $cmd->noteText);
        $this->assertEquals('highlight', $cmd->noteType);
        $this->assertFalse($cmd->isPrivate);
    }

    #[Test]
    public function add_edition_note_defaults(): void
    {
        $cmd = new AddEditionNoteCommand(userId: 1, userEditionId: 10, pageNumber: 1);

        $this->assertNull($cmd->noteText);
        $this->assertEquals('progress', $cmd->noteType);
        $this->assertTrue($cmd->isPrivate);
    }

    #[Test]
    public function add_edition_note_from_array_camel_case(): void
    {
        $cmd = AddEditionNoteCommand::fromArray([
            'userEditionId' => 10,
            'pageNumber' => 42,
            'noteText' => 'Text',
            'noteType' => 'highlight',
            'isPrivate' => false,
        ], 1);

        $this->assertSame(10, $cmd->userEditionId);
        $this->assertSame(42, $cmd->pageNumber);
        $this->assertEquals('Text', $cmd->noteText);
        $this->assertEquals('highlight', $cmd->noteType);
        $this->assertFalse($cmd->isPrivate);
    }

    #[Test]
    public function add_edition_note_from_array_snake_case(): void
    {
        $cmd = AddEditionNoteCommand::fromArray([
            'user_edition_id' => 20,
            'page_number' => 100,
            'note_text' => 'Snake text',
            'note_type' => 'note',
            'is_private' => true,
        ], 2);

        $this->assertSame(20, $cmd->userEditionId);
        $this->assertSame(100, $cmd->pageNumber);
        $this->assertEquals('Snake text', $cmd->noteText);
        $this->assertEquals('note', $cmd->noteType);
        $this->assertTrue($cmd->isPrivate);
    }

    // ═══════════════════════════════════════
    // DeleteEditionNoteCommand
    // ═══════════════════════════════════════

    #[Test]
    public function delete_edition_note_from_array_note_id(): void
    {
        $cmd = DeleteEditionNoteCommand::fromArray(['noteId' => 42], 1);
        $this->assertSame(42, $cmd->noteId);
        $this->assertSame(1, $cmd->userId);
    }

    #[Test]
    public function delete_edition_note_from_array_snake_case(): void
    {
        $cmd = DeleteEditionNoteCommand::fromArray(['note_id' => 99], 2);
        $this->assertSame(99, $cmd->noteId);
    }

    #[Test]
    public function delete_edition_note_from_array_id_fallback(): void
    {
        $cmd = DeleteEditionNoteCommand::fromArray(['id' => 5], 3);
        $this->assertSame(5, $cmd->noteId);
    }

    // ═══════════════════════════════════════
    // UpdateEditionNoteCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_edition_note_constructor(): void
    {
        $cmd = new UpdateEditionNoteCommand(
            noteId: 10,
            userId: 1,
            pageNumber: 50,
            noteText: 'Updated text',
            noteType: 'quote',
            isPrivate: false
        );

        $this->assertSame(10, $cmd->noteId);
        $this->assertSame(50, $cmd->pageNumber);
        $this->assertEquals('Updated text', $cmd->noteText);
        $this->assertEquals('quote', $cmd->noteType);
        $this->assertFalse($cmd->isPrivate);
    }

    #[Test]
    public function update_edition_note_defaults_to_null(): void
    {
        $cmd = new UpdateEditionNoteCommand(noteId: 10, userId: 1);

        $this->assertNull($cmd->pageNumber);
        $this->assertNull($cmd->noteText);
        $this->assertNull($cmd->noteType);
        $this->assertNull($cmd->isPrivate);
    }

    #[Test]
    public function update_edition_note_from_array_dual_keys(): void
    {
        $cmd = UpdateEditionNoteCommand::fromArray([
            'noteId' => 10,
            'pageNumber' => 50,
            'noteText' => 'Camel',
            'noteType' => 'highlight',
            'isPrivate' => false,
        ], 1);

        $this->assertSame(10, $cmd->noteId);
        $this->assertSame(50, $cmd->pageNumber);
        $this->assertEquals('Camel', $cmd->noteText);

        $cmd2 = UpdateEditionNoteCommand::fromArray([
            'note_id' => 20,
            'page_number' => 75,
            'note_text' => 'Snake',
            'note_type' => 'note',
            'is_private' => true,
        ], 2);

        $this->assertSame(20, $cmd2->noteId);
        $this->assertSame(75, $cmd2->pageNumber);
        $this->assertEquals('Snake', $cmd2->noteText);
    }

    // ═══════════════════════════════════════
    // AddMovieNoteCommand
    // ═══════════════════════════════════════

    #[Test]
    public function add_movie_note_constructor(): void
    {
        $cmd = new AddMovieNoteCommand(
            userId: 1,
            movieIsbn: 'tt1234567',
            noteText: 'Great movie',
            noteType: 'review',
            isPrivate: false
        );

        $this->assertSame(1, $cmd->userId);
        $this->assertEquals('tt1234567', $cmd->movieIsbn);
        $this->assertEquals('Great movie', $cmd->noteText);
        $this->assertEquals('review', $cmd->noteType);
        $this->assertFalse($cmd->isPrivate);
    }

    #[Test]
    public function add_movie_note_from_array_camel_case(): void
    {
        $cmd = AddMovieNoteCommand::fromArray([
            'movieIsbn' => 'tt1234567',
            'noteText' => 'Text',
            'noteType' => 'review',
            'isPrivate' => false,
        ], 1);

        $this->assertEquals('tt1234567', $cmd->movieIsbn);
        $this->assertEquals('Text', $cmd->noteText);
    }

    #[Test]
    public function add_movie_note_from_array_snake_case(): void
    {
        $cmd = AddMovieNoteCommand::fromArray([
            'movie_isbn' => 'tt1234567',
            'note_text' => 'Snake text',
            'note_type' => 'note',
            'is_private' => true,
        ], 2);

        $this->assertEquals('tt1234567', $cmd->movieIsbn);
        $this->assertEquals('Snake text', $cmd->noteText);
    }

    #[Test]
    public function add_movie_note_from_array_isbn_fallback(): void
    {
        $cmd = AddMovieNoteCommand::fromArray([
            'isbn' => 'tt9999999',
            'noteText' => 'Text',
        ], 1);

        $this->assertEquals('tt9999999', $cmd->movieIsbn);
    }

    // ═══════════════════════════════════════
    // DeleteMovieNoteCommand
    // ═══════════════════════════════════════

    #[Test]
    public function delete_movie_note_from_array(): void
    {
        $cmd = DeleteMovieNoteCommand::fromArray(['noteId' => 42], 1);
        $this->assertSame(42, $cmd->noteId);
        $this->assertSame(1, $cmd->userId);
    }

    #[Test]
    public function delete_movie_note_from_array_snake_case(): void
    {
        $cmd = DeleteMovieNoteCommand::fromArray(['note_id' => 99], 2);
        $this->assertSame(99, $cmd->noteId);
    }

    #[Test]
    public function delete_movie_note_from_array_id_fallback(): void
    {
        $cmd = DeleteMovieNoteCommand::fromArray(['id' => 5], 3);
        $this->assertSame(5, $cmd->noteId);
    }

    // ═══════════════════════════════════════
    // UpdateMovieNoteCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_movie_note_constructor(): void
    {
        $cmd = new UpdateMovieNoteCommand(
            noteId: 10,
            userId: 1,
            noteText: 'Updated',
            noteType: 'review',
            isPrivate: false
        );

        $this->assertSame(10, $cmd->noteId);
        $this->assertSame(1, $cmd->userId);
        $this->assertEquals('Updated', $cmd->noteText);
        $this->assertEquals('review', $cmd->noteType);
        $this->assertFalse($cmd->isPrivate);
    }

    #[Test]
    public function update_movie_note_defaults(): void
    {
        $cmd = new UpdateMovieNoteCommand(noteId: 10, userId: 1, noteText: 'Text');

        $this->assertEquals('note', $cmd->noteType);
        $this->assertTrue($cmd->isPrivate);
    }

    #[Test]
    public function update_movie_note_from_array_dual_keys(): void
    {
        $cmd = UpdateMovieNoteCommand::fromArray([
            'noteId' => 10,
            'noteText' => 'Camel',
            'noteType' => 'review',
            'isPrivate' => false,
        ], 1);

        $this->assertEquals('Camel', $cmd->noteText);
        $this->assertEquals('review', $cmd->noteType);

        $cmd2 = UpdateMovieNoteCommand::fromArray([
            'note_id' => 20,
            'note_text' => 'Snake',
            'note_type' => 'note',
            'is_private' => true,
        ], 2);

        $this->assertEquals('Snake', $cmd2->noteText);
    }
}
