<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\GetMovieNotesUseCase;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\DTO\Queries\GetMovieNotesQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetMovieNotesUseCaseTest extends TestCase
{
    private GetMovieNotesUseCase $useCase;
    private MovieNoteRepositoryInterface $movieNoteRepo;
    private UserMovieRepositoryInterface $userMovieRepo;

    protected function setUp(): void
    {
        $this->movieNoteRepo = $this->createMock(MovieNoteRepositoryInterface::class);
        $this->userMovieRepo = $this->createMock(UserMovieRepositoryInterface::class);

        $this->useCase = new GetMovieNotesUseCase(
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

        $query = new GetMovieNotesQuery(userId: 1, movieIsbn: 'tt1234567');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Movie not found in your library');
        $this->useCase->execute($query);
    }

    #[Test]
    public function successfully_returns_notes(): void
    {
        $this->userMovieRepo->method('hasMovie')->willReturn(true);
        $notes = [
            ['note_text' => 'Note 1', 'note_type' => 'note'],
            ['note_text' => 'Note 2', 'note_type' => 'quote'],
        ];
        $this->movieNoteRepo->method('getByPage')->willReturn($notes);

        $query = new GetMovieNotesQuery(userId: 1, movieIsbn: 'tt1234567');
        $result = $this->useCase->execute($query);

        $this->assertCount(2, $result);
    }

    #[Test]
    public function filters_by_note_type(): void
    {
        $this->userMovieRepo->method('hasMovie')->willReturn(true);
        $notes = [
            ['note_text' => 'Note 1', 'note_type' => 'note'],
            ['note_text' => 'Quote 1', 'note_type' => 'quote'],
            ['note_text' => 'Note 2', 'note_type' => 'note'],
        ];
        $this->movieNoteRepo->method('getByPage')->willReturn($notes);

        $query = new GetMovieNotesQuery(userId: 1, movieIsbn: 'tt1234567', noteType: 'quote');
        $result = $this->useCase->execute($query);

        $this->assertCount(1, $result);
        $this->assertSame('quote', $result[0]['note_type']);
    }
}
