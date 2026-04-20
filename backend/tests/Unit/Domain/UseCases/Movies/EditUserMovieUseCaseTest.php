<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\EditUserMovieUseCase;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Repository\Movie\MovieTagRepositoryInterface;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\DTO\Commands\EditUserMovieCommand;
use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class EditUserMovieUseCaseTest extends TestCase
{
    private EditUserMovieUseCase $useCase;
    private UserMovieRepositoryInterface $userMovieRepo;
    private MovieTagRepositoryInterface $movieTagRepo;
    private MovieNoteRepositoryInterface $movieNoteRepo;

    protected function setUp(): void
    {
        $this->userMovieRepo = $this->createMock(UserMovieRepositoryInterface::class);
        $this->movieTagRepo = $this->createMock(MovieTagRepositoryInterface::class);
        $this->movieNoteRepo = $this->createMock(MovieNoteRepositoryInterface::class);

        $this->useCase = new EditUserMovieUseCase(
            $this->userMovieRepo,
            $this->movieTagRepo,
            $this->movieNoteRepo,
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
        $command = new EditUserMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            userId: 1,
            userRating: Rating::fromFloat(4.5),
            statuses: ['watched'],
            tags: []
        );

        $this->userMovieRepo->expects($this->once())->method('edit')
            ->with(1, 'tt1234567', $this->callback(function (array $data) {
                return $data['personal_rating'] === 4.5;
            }));
        $this->userMovieRepo->expects($this->once())->method('updateStatuses')
            ->with(1, 'tt1234567', ['watched']);
        $this->movieTagRepo->expects($this->once())->method('removeAll');

        $this->useCase->execute($command);
    }

    #[Test]
    public function skips_rating_when_null(): void
    {
        $command = new EditUserMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            userId: 1,
            tags: []
        );

        $this->userMovieRepo->expects($this->never())->method('edit');
        $this->movieTagRepo->expects($this->once())->method('removeAll');

        $this->useCase->execute($command);
    }

    #[Test]
    public function adds_numeric_tags(): void
    {
        $command = new EditUserMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            userId: 1,
            tags: [1, 3]
        );

        $this->movieTagRepo->expects($this->once())->method('removeAll');
        $this->movieTagRepo->expects($this->exactly(2))->method('assign');

        $this->useCase->execute($command);
    }

    #[Test]
    public function skips_statuses_when_null(): void
    {
        $command = new EditUserMovieCommand(
            id: MovieIdentifier::fromString('tt1234567'),
            userId: 1,
            statuses: null,
            tags: []
        );

        $this->userMovieRepo->expects($this->never())->method('updateStatuses');

        $this->useCase->execute($command);
    }
}
