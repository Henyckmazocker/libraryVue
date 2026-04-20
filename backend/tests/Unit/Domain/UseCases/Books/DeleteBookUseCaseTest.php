<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\DeleteBookUseCase;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\DTO\Commands\DeleteBookCommand;
use App\Domain\Model\User;
use App\Domain\Model\Edition;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\ISBN;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class DeleteBookUseCaseTest extends TestCase
{
    private DeleteBookUseCase $useCase;
    private UserRepositoryInterface $userRepo;
    private UserBookEditionRepositoryInterface $userBookEditionRepo;
    private EditionRepositoryInterface $editionRepo;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userBookEditionRepo = $this->createMock(UserBookEditionRepositoryInterface::class);
        $this->editionRepo = $this->createMock(EditionRepositoryInterface::class);

        $this->useCase = new DeleteBookUseCase(
            $this->userRepo,
            $this->userBookEditionRepo,
            $this->editionRepo,
            new NullLogger()
        );
    }

    private function makeUser(int $id = 1): User
    {
        return new User($id, GoogleId::fromString('1234567890'), Email::fromString('u@test.com'), 'Test');
    }

    private function makeEdition(int $editionId = 5): Edition
    {
        $edition = new Edition(1, null, 'Test Book', $editionId);
        return $edition;
    }

    #[Test]
    public function successfully_deletes_book(): void
    {
        $user = $this->makeUser();
        $edition = $this->makeEdition(5);

        $this->userRepo->method('findById')->willReturn($user);
        $this->editionRepo->method('findByIsbn')->willReturn($edition);
        $this->userBookEditionRepo->method('hasEdition')->willReturn(true);
        $this->userBookEditionRepo->expects($this->once())->method('remove')
            ->with(1, 5)->willReturn(true);

        $command = DeleteBookCommand::fromArray(['isbn' => '9780131103627'], 1);
        $result = $this->useCase->execute($command);

        $this->assertTrue($result);
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Command must be an instance of DeleteBookCommand');

        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_when_user_not_found(): void
    {
        $this->userRepo->method('findById')->willReturn(null);

        $command = DeleteBookCommand::fromArray(['isbn' => '9780131103627'], 999);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');

        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_edition_not_found(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->editionRepo->method('findByIsbn')->willReturn(null);

        $command = DeleteBookCommand::fromArray(['isbn' => '9780131103627'], 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found in database');

        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_user_does_not_have_book(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->editionRepo->method('findByIsbn')->willReturn($this->makeEdition());
        $this->userBookEditionRepo->method('hasEdition')->willReturn(false);

        $command = DeleteBookCommand::fromArray(['isbn' => '9780131103627'], 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Book not found in your library');

        $this->useCase->execute($command);
    }
}
