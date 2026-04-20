<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Library;

use App\Domain\UseCases\GetLibraryUseCase;
use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\DTO\Queries\GetLibraryQuery;
use App\Domain\Model\Book;
use App\Domain\Model\Movie;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetLibraryUseCaseTest extends TestCase
{
    private GetLibraryUseCase $useCase;
    private BookRepositoryInterface $bookRepo;
    private MovieRepositoryInterface $movieRepo;

    protected function setUp(): void
    {
        $this->bookRepo = $this->createMock(BookRepositoryInterface::class);
        $this->movieRepo = $this->createMock(MovieRepositoryInterface::class);
        $this->useCase = new GetLibraryUseCase(
            $this->bookRepo,
            $this->movieRepo,
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
    public function returns_combined_books_and_movies(): void
    {
        $book = $this->createMock(Book::class);
        $book->method('toArray')->willReturn(['title' => 'A Book', 'isbn' => '9781234567890']);

        $movie = $this->createMock(Movie::class);
        $movie->method('toArray')->willReturn(['title' => 'A Movie', 'isbn' => 'tt1234567']);

        $this->bookRepo->method('findAll')->willReturn([$book]);
        $this->movieRepo->method('findAll')->willReturn([$movie]);

        $query = new GetLibraryQuery(userId: 1);
        $result = $this->useCase->execute($query);

        $this->assertCount(2, $result);
        $this->assertSame('book', $result[0]['itemType']);
        $this->assertSame('movie', $result[1]['itemType']);
    }

    #[Test]
    public function filters_by_book_item_type(): void
    {
        $book = $this->createMock(Book::class);
        $book->method('toArray')->willReturn(['title' => 'A Book']);

        $this->bookRepo->method('findAll')->willReturn([$book]);
        $this->movieRepo->expects($this->never())->method('findAll');

        $query = new GetLibraryQuery(userId: 1, itemType: 'book');
        $result = $this->useCase->execute($query);

        $this->assertCount(1, $result);
        $this->assertSame('book', $result[0]['itemType']);
    }

    #[Test]
    public function filters_by_movie_item_type(): void
    {
        $movie = $this->createMock(Movie::class);
        $movie->method('toArray')->willReturn(['title' => 'A Movie']);

        $this->bookRepo->expects($this->never())->method('findAll');
        $this->movieRepo->method('findAll')->willReturn([$movie]);

        $query = new GetLibraryQuery(userId: 1, itemType: 'movie');
        $result = $this->useCase->execute($query);

        $this->assertCount(1, $result);
        $this->assertSame('movie', $result[0]['itemType']);
    }

    #[Test]
    public function sorts_results_by_sortby_ascending(): void
    {
        $book = $this->createMock(Book::class);
        $book->method('toArray')->willReturn(['title' => 'Zebra']);

        $movie = $this->createMock(Movie::class);
        $movie->method('toArray')->willReturn(['title' => 'Alpha']);

        $this->bookRepo->method('findAll')->willReturn([$book]);
        $this->movieRepo->method('findAll')->willReturn([$movie]);

        $query = new GetLibraryQuery(userId: 1, sortBy: 'title', sortOrder: 'asc');
        $result = $this->useCase->execute($query);

        $this->assertSame('Alpha', $result[0]['title']);
        $this->assertSame('Zebra', $result[1]['title']);
    }

    #[Test]
    public function sorts_results_descending(): void
    {
        $book = $this->createMock(Book::class);
        $book->method('toArray')->willReturn(['title' => 'Alpha']);

        $movie = $this->createMock(Movie::class);
        $movie->method('toArray')->willReturn(['title' => 'Zebra']);

        $this->bookRepo->method('findAll')->willReturn([$book]);
        $this->movieRepo->method('findAll')->willReturn([$movie]);

        $query = new GetLibraryQuery(userId: 1, sortBy: 'title', sortOrder: 'desc');
        $result = $this->useCase->execute($query);

        $this->assertSame('Zebra', $result[0]['title']);
        $this->assertSame('Alpha', $result[1]['title']);
    }

    #[Test]
    public function returns_empty_when_no_items(): void
    {
        $this->bookRepo->method('findAll')->willReturn([]);
        $this->movieRepo->method('findAll')->willReturn([]);

        $query = new GetLibraryQuery(userId: 1);
        $result = $this->useCase->execute($query);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
