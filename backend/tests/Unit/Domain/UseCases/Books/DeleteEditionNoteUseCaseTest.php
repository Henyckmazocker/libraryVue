<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\DeleteEditionNoteUseCase;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\DTO\Commands\DeleteEditionNoteCommand;
use App\Domain\Model\EditionNote;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class DeleteEditionNoteUseCaseTest extends TestCase
{
    private DeleteEditionNoteUseCase $useCase;
    private EditionNoteRepositoryInterface $editionNoteRepo;

    protected function setUp(): void
    {
        $this->editionNoteRepo = $this->createMock(EditionNoteRepositoryInterface::class);

        $this->useCase = new DeleteEditionNoteUseCase(
            $this->editionNoteRepo,
            new NullLogger()
        );
    }

    #[Test]
    public function successfully_deletes_note(): void
    {
        $note = new EditionNote(userId: 1, userEditionId: 10, pageNumber: 5, id: 1);
        $this->editionNoteRepo->method('findById')->with(1, 1)->willReturn($note);
        $this->editionNoteRepo->method('delete')->with(1, 1)->willReturn(true);

        $command = new DeleteEditionNoteCommand(noteId: 1, userId: 1);
        $result = $this->useCase->execute($command);

        $this->assertTrue($result['success']);
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

        $command = new DeleteEditionNoteCommand(noteId: 99, userId: 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Note not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_delete_fails(): void
    {
        $note = new EditionNote(userId: 1, userEditionId: 10, pageNumber: 5, id: 1);
        $this->editionNoteRepo->method('findById')->willReturn($note);
        $this->editionNoteRepo->method('delete')->willReturn(false);

        $command = new DeleteEditionNoteCommand(noteId: 1, userId: 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to delete note');
        $this->useCase->execute($command);
    }
}
