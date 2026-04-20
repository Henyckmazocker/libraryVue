<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\UpdateReadingProgressUseCase;
use App\Domain\Repository\Book\ReadingProgressRepositoryInterface;
use App\Domain\Repository\Book\ReadingSessionRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\DTO\Commands\UpdateReadingProgressCommand;
use App\Domain\Model\Edition;
use App\Domain\Model\UserBookEdition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;
use RuntimeException;

class UpdateReadingProgressUseCaseTest extends TestCase
{
    private UpdateReadingProgressUseCase $useCase;
    private ReadingProgressRepositoryInterface $progressRepo;
    private ReadingSessionRepositoryInterface $sessionRepo;
    private UserBookEditionRepositoryInterface $userBookEditionRepo;
    private EditionRepositoryInterface $editionRepo;

    protected function setUp(): void
    {
        $this->progressRepo = $this->createMock(ReadingProgressRepositoryInterface::class);
        $this->sessionRepo = $this->createMock(ReadingSessionRepositoryInterface::class);
        $this->userBookEditionRepo = $this->createMock(UserBookEditionRepositoryInterface::class);
        $this->editionRepo = $this->createMock(EditionRepositoryInterface::class);

        $this->useCase = new UpdateReadingProgressUseCase(
            $this->progressRepo,
            $this->sessionRepo,
            $this->userBookEditionRepo,
            $this->editionRepo,
            new NullLogger()
        );
    }

    private function makeEditionWithPages(int $pages = 300): Edition
    {
        $edition = new Edition(1, null, 'Test Book', 5);
        $edition->setPages($pages);
        return $edition;
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
        $this->editionRepo->method('findByIsbn')->willReturn(null);

        $command = new UpdateReadingProgressCommand(userId: 1, isbn: '9780131103627', currentPage: 50);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Edition not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_user_book_edition_not_found(): void
    {
        $edition = $this->makeEditionWithPages(300);
        $this->editionRepo->method('findByIsbn')->willReturn($edition);
        $this->userBookEditionRepo->method('findByUserAndEdition')->willReturn(null);

        $command = new UpdateReadingProgressCommand(userId: 1, isbn: '9780131103627', currentPage: 50);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User book edition not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_book_has_no_pages(): void
    {
        // Edition with 0 pages
        $edition = new Edition(1, null, 'Test Book', 5);
        $userBookEdition = new UserBookEdition(userId: 1, editionId: 5, id: 10);

        $this->editionRepo->method('findByIsbn')->willReturn($edition);
        $this->userBookEditionRepo->method('findByUserAndEdition')->willReturn($userBookEdition);

        $command = new UpdateReadingProgressCommand(userId: 1, isbn: '9780131103627', currentPage: 50);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no page count');
        $this->useCase->execute($command);
    }

    #[Test]
    public function creates_session_when_none_active(): void
    {
        $edition = $this->makeEditionWithPages(300);
        $userBookEdition = new UserBookEdition(userId: 1, editionId: 5, id: 10);

        $this->editionRepo->method('findByIsbn')->willReturn($edition);
        $this->userBookEditionRepo->method('findByUserAndEdition')->willReturn($userBookEdition);
        $this->sessionRepo->method('getActive')->willReturnOnConsecutiveCalls(
            null,  // first call: no active session
            ['id' => 1, 'start_page' => 50]  // second call: after creation
        );
        $this->sessionRepo->method('create')->willReturn(1);

        $this->userBookEditionRepo->method('getStatusesForEdition')->willReturn(['reading']);

        $command = new UpdateReadingProgressCommand(userId: 1, isbn: '9780131103627', currentPage: 50);
        $result = $this->useCase->execute($command);

        $this->assertSame(50, $result['currentPage']);
        $this->assertSame(300, $result['totalPages']);
        $this->assertFalse($result['isComplete']);
    }

    #[Test]
    public function marks_complete_at_100_percent(): void
    {
        $edition = $this->makeEditionWithPages(300);
        $userBookEdition = new UserBookEdition(userId: 1, editionId: 5, id: 10);

        $this->editionRepo->method('findByIsbn')->willReturn($edition);
        $this->userBookEditionRepo->method('findByUserAndEdition')->willReturn($userBookEdition);
        $this->sessionRepo->method('getActive')->willReturn(['id' => 1, 'start_page' => 1]);

        $this->sessionRepo->expects($this->once())->method('complete')->with(1, 300);
        $this->userBookEditionRepo->method('getStatusesForEdition')->willReturn(['read']);

        $command = new UpdateReadingProgressCommand(userId: 1, isbn: '9780131103627', currentPage: 300);
        $result = $this->useCase->execute($command);

        $this->assertTrue($result['isComplete']);
        $this->assertSame(100.0, $result['percentage']);
    }
}
