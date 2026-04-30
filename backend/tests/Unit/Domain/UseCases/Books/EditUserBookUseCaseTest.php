<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\EditUserBookUseCase;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\Repository\Book\BookTagRepositoryInterface;
use App\Domain\Repository\Book\BookNoteRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\DTO\Commands\EditUserBookCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Model\ValueObjects\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class EditUserBookUseCaseTest extends TestCase
{
    private EditUserBookUseCase $useCase;
    private UserRepositoryInterface $userRepo;
    private UserBookRepositoryInterface $userBookRepo;
    private BookTagRepositoryInterface $bookTagRepo;
    private BookNoteRepositoryInterface $bookNoteRepo;
    private EditionRepositoryInterface $editionRepo;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userBookRepo = $this->createMock(UserBookRepositoryInterface::class);
        $this->bookTagRepo = $this->createMock(BookTagRepositoryInterface::class);
        $this->bookNoteRepo = $this->createMock(BookNoteRepositoryInterface::class);
        $this->editionRepo = $this->createMock(EditionRepositoryInterface::class);

        $this->useCase = new EditUserBookUseCase(
            $this->userRepo,
            $this->userBookRepo,
            $this->bookTagRepo,
            $this->bookNoteRepo,
            $this->editionRepo,
            new NullLogger()
        );
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function updates_rating_and_statuses(): void
    {
        $command = new EditUserBookCommand(
            isbn: ISBN::fromString('9780131103627'),
            userId: 1,
            userRating: Rating::fromFloat(4.0),
            statuses: ['reading', 'owned'],
            tags: []
        );

        $this->userBookRepo->expects($this->once())->method('edit')
            ->with(1, '9780131103627', $this->callback(function (array $data) {
                return isset($data['personal_rating']) && $data['personal_rating'] === 4.0;
            }));

        $this->userBookRepo->expects($this->once())->method('updateStatuses')
            ->with(1, '9780131103627', ['reading', 'owned']);

        $this->bookTagRepo->expects($this->once())->method('removeAll')
            ->with(1, '9780131103627');

        $this->useCase->execute($command);
    }

    #[Test]
    public function skips_edit_when_no_data_to_update(): void
    {
        $command = new EditUserBookCommand(
            isbn: ISBN::fromString('9780131103627'),
            userId: 1,
            tags: []
        );

        // edit() should NOT be called because no data fields are set
        $this->userBookRepo->expects($this->never())->method('edit');
        // removeAll is always called
        $this->bookTagRepo->expects($this->once())->method('removeAll');

        $this->useCase->execute($command);
    }

    #[Test]
    public function adds_numeric_tags(): void
    {
        $command = new EditUserBookCommand(
            isbn: ISBN::fromString('9780131103627'),
            userId: 1,
            tags: [1, 5, 10]
        );

        $this->bookTagRepo->expects($this->once())->method('removeAll');
        $this->bookTagRepo->expects($this->exactly(3))->method('assign');

        $this->useCase->execute($command);
    }

    #[Test]
    public function does_not_update_statuses_when_null(): void
    {
        $command = new EditUserBookCommand(
            isbn: ISBN::fromString('9780131103627'),
            userId: 1,
            statuses: null,
            tags: []
        );

        $this->userBookRepo->expects($this->never())->method('updateStatuses');
        $this->bookTagRepo->expects($this->once())->method('removeAll');

        $this->useCase->execute($command);
    }
}
