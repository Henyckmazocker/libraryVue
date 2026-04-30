<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\DeleteMovieNoteUseCase;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\DTO\Commands\DeleteMovieNoteCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class DeleteMovieNoteUseCaseTest extends TestCase
{
    private DeleteMovieNoteUseCase $useCase;
    private MovieNoteRepositoryInterface $movieNoteRepo;

    protected function setUp(): void
    {
        $this->movieNoteRepo = $this->createMock(MovieNoteRepositoryInterface::class);
        $this->useCase = new DeleteMovieNoteUseCase($this->movieNoteRepo, new NullLogger());
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function successfully_deletes_note(): void
    {
        $this->movieNoteRepo->method('delete')->with(1, 1)->willReturn(true);

        $command = new DeleteMovieNoteCommand(noteId: 1, userId: 1);
        $result = $this->useCase->execute($command);

        $this->assertTrue($result);
    }

    #[Test]
    public function throws_when_note_not_found(): void
    {
        $this->movieNoteRepo->method('delete')->willReturn(false);

        $command = new DeleteMovieNoteCommand(noteId: 99, userId: 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Note not found');
        $this->useCase->execute($command);
    }
}
