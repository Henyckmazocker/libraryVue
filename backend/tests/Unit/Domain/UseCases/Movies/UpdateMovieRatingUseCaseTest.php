<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\UpdateMovieRatingUseCase;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\DTO\Commands\UpdateMovieRatingCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class UpdateMovieRatingUseCaseTest extends TestCase
{
    private UpdateMovieRatingUseCase $useCase;
    private UserRepositoryInterface $userRepo;
    private UserMovieRepositoryInterface $userMovieRepo;
    private MovieRepositoryInterface $movieRepo;
    private FeedEventService $feedEventService;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->userMovieRepo = $this->createMock(UserMovieRepositoryInterface::class);
        $this->movieRepo = $this->createMock(MovieRepositoryInterface::class);
        $this->feedEventService = $this->createMock(FeedEventService::class);

        $this->useCase = new UpdateMovieRatingUseCase(
            $this->userRepo,
            $this->userMovieRepo,
            $this->movieRepo,
            $this->feedEventService,
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

        $command = new UpdateMovieRatingCommand(
            userId: 999,
            id: MovieIdentifier::fromString('tt1234567'),
            rating: Rating::fromFloat(4.0)
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

        $command = new UpdateMovieRatingCommand(
            userId: 1,
            id: MovieIdentifier::fromString('tt1234567'),
            rating: Rating::fromFloat(4.0)
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Movie not found in your library');
        $this->useCase->execute($command);
    }

    #[Test]
    public function successfully_updates_rating(): void
    {
        $this->userRepo->method('findById')->willReturn($this->makeUser());
        $this->userMovieRepo->method('hasMovie')->willReturn(true);
        $this->userMovieRepo->expects($this->once())->method('updateRating')
            ->with(1, 'tt1234567', 4.0);

        $command = new UpdateMovieRatingCommand(
            userId: 1,
            id: MovieIdentifier::fromString('tt1234567'),
            rating: Rating::fromFloat(4.0)
        );

        $result = $this->useCase->execute($command);
        $this->assertTrue($result);
    }
}
