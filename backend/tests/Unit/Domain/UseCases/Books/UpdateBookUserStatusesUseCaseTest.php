<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\UpdateBookUserStatusesUseCase;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\DTO\Commands\UpdateBookStatusesCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class UpdateBookUserStatusesUseCaseTest extends TestCase
{
    private UpdateBookUserStatusesUseCase $useCase;
    private UserRepositoryInterface $userRepo;
    private UserBookRepositoryInterface $userBookRepo;
    private EditionRepositoryInterface $editionRepo;
    private FeedEventService $feedEventService;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userBookRepo = $this->createMock(UserBookRepositoryInterface::class);
        $this->editionRepo = $this->createMock(EditionRepositoryInterface::class);
        $this->feedEventService = $this->createMock(FeedEventService::class);

        $this->useCase = new UpdateBookUserStatusesUseCase(
            $this->userRepo,
            $this->userBookRepo,
            $this->editionRepo,
            $this->feedEventService,
            new NullLogger()
        );
    }

    private function makeUser(int $id = 1): User
    {
        return new User($id, GoogleId::fromString('1234567890'), Email::fromString('u@test.com'), 'Test');
    }

    #[Test]
    public function successfully_updates_statuses(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userBookRepo->method('hasBook')->willReturn(true);
        $this->userBookRepo->expects($this->once())->method('updateStatuses')
            ->with(1, '9780131103627', ['reading', 'owned']);

        $command = UpdateBookStatusesCommand::fromArray([
            'isbn' => '9780131103627',
            'statuses' => ['reading', 'owned'],
        ], 1);

        $result = $this->useCase->execute($command);
        $this->assertTrue($result);
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

        $command = UpdateBookStatusesCommand::fromArray([
            'isbn' => '9780131103627', 'statuses' => ['reading'],
        ], 999);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_book_not_in_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userBookRepo->method('hasBook')->willReturn(false);

        $command = UpdateBookStatusesCommand::fromArray([
            'isbn' => '9780131103627', 'statuses' => ['reading'],
        ], 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Book not found in your library');
        $this->useCase->execute($command);
    }
}
