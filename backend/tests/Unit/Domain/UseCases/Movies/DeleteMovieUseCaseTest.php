<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\DeleteMovieUseCase;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\DTO\Commands\DeleteMovieCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\MovieIdentifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class DeleteMovieUseCaseTest extends TestCase
{
    private DeleteMovieUseCase $useCase;
    private UserRepositoryInterface $userRepo;
    private UserMovieRepositoryInterface $userMovieRepo;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userMovieRepo = $this->createMock(UserMovieRepositoryInterface::class);

        $this->useCase = new DeleteMovieUseCase(
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
    public function successfully_deletes_movie(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userMovieRepo->method('hasMovie')->willReturn(true);
        $this->userMovieRepo->expects($this->once())->method('remove')
            ->with(1, 'tt1234567')->willReturn(true);

        $command = new DeleteMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            userId: 1
        );
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

        $command = new DeleteMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            userId: 999
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID 999 not found');
        $this->useCase->execute($command);
    }

    #[Test]
    public function throws_when_movie_not_in_library(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userMovieRepo->method('hasMovie')->willReturn(false);

        $command = new DeleteMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            userId: 1
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Movie not found in your library');
        $this->useCase->execute($command);
    }
}
