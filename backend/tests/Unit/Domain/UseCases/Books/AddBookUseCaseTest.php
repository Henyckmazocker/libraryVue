<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\AddBookUseCase;
use App\Domain\Services\BookImportServiceInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\WorkRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Services\CoverService;
use App\Domain\Services\FeedEventService;
use App\Domain\DTO\Commands\AddBookCommand;
use App\Domain\Model\User;
use App\Domain\Model\Edition;
use App\Domain\Model\Work;
use App\Domain\Model\UserBookEdition;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\ISBN;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class AddBookUseCaseTest extends TestCase
{
    private AddBookUseCase $useCase;
    private BookImportServiceInterface $importService;
    private EditionRepositoryInterface $editionRepo;
    private WorkRepositoryInterface $workRepo;
    private UserRepositoryInterface $userRepo;
    private UserBookEditionRepositoryInterface $userBookEditionRepo;
    private FeedEventService $feedEventService;
    private CoverService $coverService;

    protected function setUp(): void
    {
        $this->importService = $this->createMock(BookImportServiceInterface::class);
        $this->editionRepo = $this->createMock(EditionRepositoryInterface::class);
        $this->workRepo = $this->createMock(WorkRepositoryInterface::class);
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userBookEditionRepo = $this->createMock(UserBookEditionRepositoryInterface::class);
        $this->feedEventService = $this->createMock(FeedEventService::class);
        $this->coverService = $this->createMock(CoverService::class);

        $this->useCase = new AddBookUseCase(
            $this->importService,
            $this->editionRepo,
            $this->workRepo,
            $this->userRepo,
            $this->userBookEditionRepo,
            $this->feedEventService,
            $this->coverService,
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

        $command = new AddBookCommand(
            isbn: ISBN::fromString('9780131103627'),
            title: 'Test Book',
            userId: 999
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_user_already_has_edition(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());

        $edition = new Edition(1, null, 'Test Book', 5);
        $this->editionRepo->method('findByIsbn')->willReturn($edition);
        $this->userBookEditionRepo->method('hasEdition')->with(1, 5)->willReturn(true);

        $command = new AddBookCommand(
            isbn: ISBN::fromString('9780131103627'),
            title: 'Test Book',
            userId: 1
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already have this book');
        $this->useCase->execute($command);
    }

    #[Test]
    public function adds_existing_edition_to_user_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());

        $edition = new Edition(1, null, 'Test Book', 5);
        $work = Work::fromArray([
            'title' => 'Test Book',
            'authors' => ['Author'],
            'subjects' => [],
            'first_publish_year' => 2020,
        ]);

        $this->editionRepo->method('findByIsbn')->willReturn($edition);
        $this->userBookEditionRepo->method('hasEdition')->willReturn(false);
        $this->workRepo->method('findById')->willReturn($work);

        $userBookEdition = new UserBookEdition(userId: 1, editionId: 5, id: 1);
        $this->userBookEditionRepo->method('add')->willReturn($userBookEdition);

        $command = new AddBookCommand(
            isbn: ISBN::fromString('9780131103627'),
            title: 'Test Book',
            userId: 1,
            statuses: ['owned']
        );

        $result = $this->useCase->execute($command);
        $this->assertIsArray($result);
    }

    #[Test]
    public function imports_new_book_via_import_service(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->editionRepo->method('findByIsbn')->willReturn(null);

        $edition = new Edition(1, null, 'New Book', 5);
        $work = Work::fromArray([
            'title' => 'New Book',
            'authors' => ['Author'],
            'subjects' => [],
            'first_publish_year' => 2023,
        ]);

        $this->importService->method('importFromOpenLibrary')
            ->willReturn(['work' => $work, 'edition' => $edition]);

        $userBookEdition = new UserBookEdition(userId: 1, editionId: 5, id: 1);
        $this->userBookEditionRepo->method('add')->willReturn($userBookEdition);

        $command = new AddBookCommand(
            isbn: ISBN::fromString('9780131103627'),
            title: 'New Book',
            userId: 1
        );

        $result = $this->useCase->execute($command);
        $this->assertIsArray($result);
    }
}
