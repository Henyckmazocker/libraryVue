<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\DTO\Queries\GetMoviesByUserQuery;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetMoviesUseCaseTest extends TestCase
{
    private GetMoviesUseCase $useCase;
    private UserRepositoryInterface $userRepo;
    private UserMovieRepositoryInterface $userMovieRepo;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userMovieRepo = $this->createMock(UserMovieRepositoryInterface::class);

        $this->useCase = new GetMoviesUseCase(
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

        $query = new GetMoviesByUserQuery(userId: 999);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($query);
    }

    #[Test]
    public function successfully_returns_movies(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $movies = [
            ['isbn' => 'tt111', 'title' => 'Movie A'],
            ['isbn' => 'tt222', 'title' => 'Movie B'],
        ];
        $this->userMovieRepo->method('findByUser')->willReturn($movies);

        $query = new GetMoviesByUserQuery(userId: 1);
        $result = $this->useCase->execute($query);

        $this->assertCount(2, $result);
    }

    #[Test]
    public function returns_empty_when_no_movies(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userMovieRepo->method('findByUser')->willReturn([]);

        $query = new GetMoviesByUserQuery(userId: 1);
        $result = $this->useCase->execute($query);

        $this->assertEmpty($result);
    }
}
