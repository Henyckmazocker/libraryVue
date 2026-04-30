<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\UpdateEditionNoteUseCase;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\DTO\Commands\UpdateEditionNoteCommand;
use App\Domain\Model\EditionNote;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class UpdateEditionNoteUseCaseTest extends TestCase
{
    private UpdateEditionNoteUseCase $useCase;
    private EditionNoteRepositoryInterface $editionNoteRepo;

    protected function setUp(): void
    {
        $this->editionNoteRepo = $this->createMock(EditionNoteRepositoryInterface::class);

        $this->useCase = new UpdateEditionNoteUseCase(
            $this->editionNoteRepo,
            new NullLogger()
        );
    }

    #[Test]
    public function successfully_updates_note(): void
    {
        $note = new EditionNote(
            userId: 1, userEditionId: 10, pageNumber: 5,
            noteText: 'Original', noteType: 'note', isPrivate: true, id: 1
        );
        $this->editionNoteRepo->method('findById')->with(1, 1)->willReturn($note);
        $this->editionNoteRepo->method('update')->willReturnCallback(fn($n) => $n);

        $command = new UpdateEditionNoteCommand(
            noteId: 1, userId: 1,
            noteText: 'Updated text', pageNumber: 10
        );

        $result = $this->useCase->execute($command);

        $this->assertIsArray($result);
        $this->assertSame('Updated text', $result['noteText'] ?? $result['note_text']);
        $this->assertSame(10, $result['pageNumber'] ?? $result['page_number']);
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_when_note_not_found(): void
    {
        $this->editionNoteRepo->method('findById')->willReturn(null);

        $command = new UpdateEditionNoteCommand(noteId: 99, userId: 1, noteText: 'text');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Note not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function updates_only_provided_fields(): void
    {
        $note = new EditionNote(
            userId: 1, userEditionId: 10, pageNumber: 5,
            noteText: 'Original', noteType: 'note', isPrivate: true, id: 1
        );
        $this->editionNoteRepo->method('findById')->willReturn($note);
        $this->editionNoteRepo->method('update')->willReturnCallback(fn($n) => $n);

        // Only update noteType, leave others null
        $command = new UpdateEditionNoteCommand(noteId: 1, userId: 1, noteType: 'quote');
        $result = $this->useCase->execute($command);

        $this->assertSame('quote', $result['noteType'] ?? $result['note_type']);
        // Original text should remain
        $this->assertSame('Original', $result['noteText'] ?? $result['note_text']);
    }
}
