<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\AddMovieUseCase;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\DTO\Commands\AddMovieCommand;
use App\Domain\Model\User;
use App\Domain\Model\Movie;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\MovieIdentifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class AddMovieUseCaseTest extends TestCase
{
    private AddMovieUseCase $useCase;
    private MovieRepositoryInterface $movieRepo;
    private UserRepositoryInterface $userRepo;
    private UserMovieRepositoryInterface $userMovieRepo;

    protected function setUp(): void
    {
        $this->movieRepo = $this->createMock(MovieRepositoryInterface::class);
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userMovieRepo = $this->createMock(UserMovieRepositoryInterface::class);

        $this->useCase = new AddMovieUseCase(
            $this->movieRepo,
            $this->userRepo,
            $this->userMovieRepo,
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

        $command = new AddMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            title: 'Test Movie',
            userId: 999
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_user_already_has_movie(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userMovieRepo->method('hasMovie')->willReturn(true);

        $command = new AddMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            title: 'Test Movie',
            userId: 1
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already have this movie');
        $this->useCase->execute($command);
    }

    #[Test]
    public function adds_existing_movie_to_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userMovieRepo->method('hasMovie')->willReturn(false);

        $movie = Movie::fromArray(['isbn' => 'tt1234567', 'title' => 'Existing Movie', 'userStatuses' => ['owned']]);
        $this->movieRepo->method('findById')->willReturn($movie);
        $this->userMovieRepo->expects($this->once())->method('add');

        $command = new AddMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            title: 'Existing Movie',
            userId: 1,
            statuses: ['owned']
        );

        $result = $this->useCase->execute($command);
        $this->assertInstanceOf(Movie::class, $result);
    }

    #[Test]
    public function creates_new_movie_when_not_found(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userMovieRepo->method('hasMovie')->willReturn(false);
        $this->movieRepo->method('findById')->willReturn(null);
        $this->movieRepo->expects($this->once())->method('save');

        $command = new AddMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            title: 'New Movie',
            userId: 1,
            statuses: ['watchlist']
        );

        $result = $this->useCase->execute($command);
        $this->assertInstanceOf(Movie::class, $result);
    }
}
