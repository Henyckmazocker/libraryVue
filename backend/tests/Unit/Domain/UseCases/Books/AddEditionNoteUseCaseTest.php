<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\AddEditionNoteUseCase;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\DTO\Commands\AddEditionNoteCommand;
use App\Domain\Model\EditionNote;
use App\Domain\Model\UserBookEdition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class AddEditionNoteUseCaseTest extends TestCase
{
    private AddEditionNoteUseCase $useCase;
    private EditionNoteRepositoryInterface $editionNoteRepo;
    private UserBookEditionRepositoryInterface $userBookEditionRepo;

    protected function setUp(): void
    {
        $this->editionNoteRepo = $this->createMock(EditionNoteRepositoryInterface::class);
        $this->userBookEditionRepo = $this->createMock(UserBookEditionRepositoryInterface::class);

        $this->useCase = new AddEditionNoteUseCase(
            $this->editionNoteRepo,
            $this->userBookEditionRepo,
            new NullLogger()
        );
    }

    #[Test]
    public function successfully_adds_edition_note(): void
    {
        $userEdition = new UserBookEdition(userId: 1, editionId: 5, id: 10);

        $this->userBookEditionRepo->method('findById')->with(10)->willReturn($userEdition);

        $savedNote = new EditionNote(
            userId: 1, userEditionId: 10, pageNumber: 42,
            noteText: 'Great passage', noteType: 'note', isPrivate: true, id: 1
        );
        $this->editionNoteRepo->method('add')->willReturn($savedNote);

        $command = new AddEditionNoteCommand(
            userId: 1, userEditionId: 10, pageNumber: 42,
            noteText: 'Great passage', noteType: 'note'
        );

        $result = $this->useCase->execute($command);

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
    public function throws_when_edition_not_found(): void
    {
        $this->userBookEditionRepo->method('findById')->willReturn(null);

        $command = new AddEditionNoteCommand(userId: 1, userEditionId: 99, pageNumber: 10);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Edition not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_user_does_not_own_edition(): void
    {
        $userEdition = new UserBookEdition(userId: 2, editionId: 5, id: 10);
        $this->userBookEditionRepo->method('findById')->willReturn($userEdition);

        $command = new AddEditionNoteCommand(userId: 1, userEditionId: 10, pageNumber: 10);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('permission');
        $this->useCase->execute($command);
    }
}
