<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\GetBooksUseCase;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\WorkRepositoryInterface;
use App\Domain\DTO\Queries\GetBooksByUserQuery;
use App\Domain\Model\User;
use App\Domain\Model\Edition;
use App\Domain\Model\Work;
use App\Domain\Model\UserBookEdition;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetBooksUseCaseTest extends TestCase
{
    private GetBooksUseCase $useCase;
    private UserRepositoryInterface $userRepo;
    private UserBookEditionRepositoryInterface $userBookEditionRepo;
    private EditionRepositoryInterface $editionRepo;
    private WorkRepositoryInterface $workRepo;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userBookEditionRepo = $this->createMock(UserBookEditionRepositoryInterface::class);
        $this->editionRepo = $this->createMock(EditionRepositoryInterface::class);
        $this->workRepo = $this->createMock(WorkRepositoryInterface::class);

        $this->useCase = new GetBooksUseCase(
            $this->userRepo,
            $this->userBookEditionRepo,
            $this->editionRepo,
            $this->workRepo,
            new NullLogger()
        );
    }

    private function makeUser(int $id = 1): User
    {
        return new User($id, GoogleId::fromString('1234567890'), Email::fromString('u@test.com'), 'Test');
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_when_user_not_found(): void
    {
        $this->userRepo->method('findById')->willReturn(null);
        $query = new GetBooksByUserQuery(userId: 999);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($query);
    }

    #[Test]
    public function returns_empty_array_when_no_books(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userBookEditionRepo->method('findByUser')->willReturn([]);

        $query = new GetBooksByUserQuery(userId: 1);
        $result = $this->useCase->execute($query);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function skips_entries_with_missing_edition(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());

        $userBookEdition = new UserBookEdition(userId: 1, editionId: 5, id: 10);
        $this->userBookEditionRepo->method('findByUser')->willReturn([$userBookEdition]);
        $this->editionRepo->method('findById')->willReturn(null);

        $query = new GetBooksByUserQuery(userId: 1);
        $result = $this->useCase->execute($query);

        $this->assertEmpty($result);
    }

    #[Test]
    public function skips_entries_with_missing_work(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());

        $userBookEdition = new UserBookEdition(userId: 1, editionId: 5, id: 10);
        $edition = new Edition(1, null, 'Test Book', 5);

        $this->userBookEditionRepo->method('findByUser')->willReturn([$userBookEdition]);
        $this->editionRepo->method('findById')->willReturn($edition);
        $this->workRepo->method('findById')->willReturn(null);

        $query = new GetBooksByUserQuery(userId: 1);
        $result = $this->useCase->execute($query);

        $this->assertEmpty($result);
    }

    #[Test]
    public function returns_books_in_legacy_format(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());

        $userBookEdition = new UserBookEdition(userId: 1, editionId: 5, id: 10);
        $edition = new Edition(1, null, 'Test Book', 5);
        $work = Work::fromArray([
            'title' => 'Test Book',
            'authors' => ['Author A'],
            'subjects' => [],
            'first_publish_year' => 2020,
        ]);

        $this->userBookEditionRepo->method('findByUser')->willReturn([$userBookEdition]);
        $this->editionRepo->method('findById')->willReturn($edition);
        $this->workRepo->method('findById')->willReturn($work);
        $this->userBookEditionRepo->method('getStatusesForEdition')
            ->with(1, 5)->willReturn(['reading']);

        $query = new GetBooksByUserQuery(userId: 1);
        $result = $this->useCase->execute($query);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('userStatuses', $result[0]);
        $this->assertSame(['reading'], $result[0]['userStatuses']);
    }
}
