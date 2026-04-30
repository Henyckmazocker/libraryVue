<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\GetEditionNoteUseCase;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\DTO\Queries\GetEditionNoteQuery;
use App\Domain\Model\EditionNote;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetEditionNoteUseCaseTest extends TestCase
{
    private GetEditionNoteUseCase $useCase;
    private EditionNoteRepositoryInterface $editionNoteRepo;

    protected function setUp(): void
    {
        $this->editionNoteRepo = $this->createMock(EditionNoteRepositoryInterface::class);
        $this->useCase = new GetEditionNoteUseCase($this->editionNoteRepo, new NullLogger());
    }

    #[Test]
    public function successfully_retrieves_note(): void
    {
        $note = new EditionNote(
            userId: 1, userEditionId: 10, pageNumber: 42,
            noteText: 'Found it', noteType: 'note', isPrivate: true, id: 1
        );
        $this->editionNoteRepo->method('findById')->with(1, 1)->willReturn($note);

        $query = new GetEditionNoteQuery(noteId: 1, userId: 1);
        $result = $this->useCase->execute($query);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
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

        $query = new GetEditionNoteQuery(noteId: 99, userId: 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Note not found');
        $this->useCase->execute($query);
    }
}
