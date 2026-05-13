<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\UpdateBookRatingUseCase;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\DTO\Commands\UpdateBookRatingCommand;
use App\Domain\Model\User;
use App\Domain\Model\Edition;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class UpdateBookRatingUseCaseTest extends TestCase
{
    private UpdateBookRatingUseCase $useCase;
    private UserRepositoryInterface $userRepo;
    private UserBookEditionRepositoryInterface $userBookEditionRepo;
    private EditionRepositoryInterface $editionRepo;
    private FeedEventService $feedEventService;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userBookEditionRepo = $this->createMock(UserBookEditionRepositoryInterface::class);
        $this->editionRepo = $this->createMock(EditionRepositoryInterface::class);
        $this->feedEventService = $this->createMock(FeedEventService::class);

        $this->useCase = new UpdateBookRatingUseCase(
            $this->userRepo,
            $this->userBookEditionRepo,
            $this->editionRepo,
            $this->feedEventService,
            new NullLogger()
        );
    }

    private function makeUser(int $id = 1): User
    {
        return new User($id, GoogleId::fromString('1234567890'), Email::fromString('u@test.com'), 'Test');
    }

    private function makeEdition(int $editionId = 5): Edition
    {
        return new Edition(1, null, 'Test Book', $editionId);
    }

    #[Test]
    public function successfully_updates_rating(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->editionRepo->method('findByIsbn')->willReturn($this->makeEdition(5));
        $this->userBookEditionRepo->method('hasEdition')->willReturn(true);
        $this->userBookEditionRepo->expects($this->once())->method('updateRating')
            ->with(1, 5, 4.5, null);

        $command = UpdateBookRatingCommand::fromArray([
            'isbn' => '9780131103627',
            'rating' => 4.5,
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

        $command = UpdateBookRatingCommand::fromArray([
            'isbn' => '9780131103627', 'rating' => 4.0,
        ], 999);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_book_not_in_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->editionRepo->method('findByIsbn')->willReturn($this->makeEdition());
        $this->userBookEditionRepo->method('hasEdition')->willReturn(false);

        $command = UpdateBookRatingCommand::fromArray([
            'isbn' => '9780131103627', 'rating' => 4.0,
        ], 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Book not found in your library');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_edition_not_found(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->editionRepo->method('findByIsbn')->willReturn(null);

        $command = UpdateBookRatingCommand::fromArray([
            'isbn' => '9780131103627', 'rating' => 4.0,
        ], 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found in database');
        $this->useCase->execute($command);
    }
}
