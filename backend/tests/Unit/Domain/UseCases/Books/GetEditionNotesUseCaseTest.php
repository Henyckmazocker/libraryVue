<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\GetEditionNotesUseCase;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\DTO\Queries\GetEditionNotesQuery;
use App\Domain\Model\EditionNote;
use App\Domain\Model\UserBookEdition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetEditionNotesUseCaseTest extends TestCase
{
    private GetEditionNotesUseCase $useCase;
    private EditionNoteRepositoryInterface $editionNoteRepo;
    private UserBookEditionRepositoryInterface $userBookEditionRepo;

    protected function setUp(): void
    {
        $this->editionNoteRepo = $this->createMock(EditionNoteRepositoryInterface::class);
        $this->userBookEditionRepo = $this->createMock(UserBookEditionRepositoryInterface::class);

        $this->useCase = new GetEditionNotesUseCase(
            $this->editionNoteRepo,
            $this->userBookEditionRepo,
            new NullLogger()
        );
    }

    #[Test]
    public function successfully_retrieves_notes(): void
    {
        $userEdition = new UserBookEdition(userId: 1, editionId: 5, id: 10);
        $this->userBookEditionRepo->method('findById')->willReturn($userEdition);

        $notes = [
            new EditionNote(userId: 1, userEditionId: 10, pageNumber: 1, noteText: 'A', id: 1),
            new EditionNote(userId: 1, userEditionId: 10, pageNumber: 2, noteText: 'B', id: 2),
        ];
        $this->editionNoteRepo->method('findByUserEdition')->willReturn($notes);

        $query = new GetEditionNotesQuery(userId: 1, userEditionId: 10);
        $result = $this->useCase->execute($query);

        $this->assertCount(2, $result);
        $this->assertIsArray($result[0]);
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

        $query = new GetEditionNotesQuery(userId: 1, userEditionId: 99);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Edition not found');
        $this->useCase->execute($query);
    }

    #[Test]
    public function throws_when_user_does_not_own_edition(): void
    {
        $userEdition = new UserBookEdition(userId: 2, editionId: 5, id: 10);
        $this->userBookEditionRepo->method('findById')->willReturn($userEdition);

        $query = new GetEditionNotesQuery(userId: 1, userEditionId: 10);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('permission');
        $this->useCase->execute($query);
    }
}
