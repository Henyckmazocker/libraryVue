<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Movies;

use App\Domain\UseCases\Movies\UpdateMovieNoteUseCase;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\DTO\Commands\UpdateMovieNoteCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class UpdateMovieNoteUseCaseTest extends TestCase
{
    private UpdateMovieNoteUseCase $useCase;
    private MovieNoteRepositoryInterface $movieNoteRepo;

    protected function setUp(): void
    {
        $this->movieNoteRepo = $this->createMock(MovieNoteRepositoryInterface::class);
        $this->useCase = new UpdateMovieNoteUseCase($this->movieNoteRepo, new NullLogger());
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function successfully_updates_note(): void
    {
        $this->movieNoteRepo->method('update')->willReturn(true);

        $command = new UpdateMovieNoteCommand(
            noteId: 1, userId: 1,
            noteText: 'Updated text', noteType: 'note'
        );

        $result = $this->useCase->execute($command);
        $this->assertTrue($result);
    }

    #[Test]
    public function throws_when_note_not_found(): void
    {
        $this->movieNoteRepo->method('update')->willReturn(false);

        $command = new UpdateMovieNoteCommand(
            noteId: 99, userId: 1,
            noteText: 'text', noteType: 'note'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Note not found');
        $this->useCase->execute($command);
    }
}
