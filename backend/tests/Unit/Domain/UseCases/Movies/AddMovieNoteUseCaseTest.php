<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\AddMovieNoteUseCase;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\DTO\Commands\AddMovieNoteCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class AddMovieNoteUseCaseTest extends TestCase
{
    private AddMovieNoteUseCase $useCase;
    private MovieNoteRepositoryInterface $movieNoteRepo;
    private UserMovieRepositoryInterface $userMovieRepo;

    protected function setUp(): void
    {
        $this->movieNoteRepo = $this->createMock(MovieNoteRepositoryInterface::class);
        $this->userMovieRepo = $this->createMock(UserMovieRepositoryInterface::class);

        $this->useCase = new AddMovieNoteUseCase(
            $this->movieNoteRepo,
            $this->userMovieRepo,
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
    public function throws_when_movie_not_in_library(): void
    {
        $this->userMovieRepo->method('hasMovie')->willReturn(false);

        $command = new AddMovieNoteCommand(
            userId: 1, movieIsbn: 'tt1234567', noteText: 'Great movie'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Movie not found in your library');
        $this->useCase->execute($command);
    }

    #[Test]
    public function successfully_adds_note(): void
    {
        $this->userMovieRepo->method('hasMovie')->willReturn(true);
        $this->movieNoteRepo->method('add')->willReturn(42);

        $command = new AddMovieNoteCommand(
            userId: 1, movieIsbn: 'tt1234567',
            noteText: 'Loved the ending', noteType: 'note'
        );

        $result = $this->useCase->execute($command);

        $this->assertSame(42, $result['id']);
        $this->assertSame('Loved the ending', $result['note_text']);
        $this->assertSame('note', $result['note_type']);
    }
}
