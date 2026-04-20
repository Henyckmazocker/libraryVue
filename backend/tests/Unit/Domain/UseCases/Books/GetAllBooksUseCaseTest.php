<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\GetAllBooksUseCase;
use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\DTO\Queries\GetAllBooksQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetAllBooksUseCaseTest extends TestCase
{
    private GetAllBooksUseCase $useCase;
    private BookRepositoryInterface $bookRepo;

    protected function setUp(): void
    {
        $this->bookRepo = $this->createMock(BookRepositoryInterface::class);
        $this->useCase = new GetAllBooksUseCase($this->bookRepo, new NullLogger());
    }

    #[Test]
    public function successfully_returns_all_books(): void
    {
        $books = [
            ['id' => 1, 'title' => 'Book A'],
            ['id' => 2, 'title' => 'Book B'],
        ];
        $this->bookRepo->method('findAll')->willReturn($books);

        $result = $this->useCase->execute(new GetAllBooksQuery());
        $this->assertCount(2, $result);
        $this->assertSame('Book A', $result[0]['title']);
    }

    #[Test]
    public function returns_empty_array_when_no_books(): void
    {
        $this->bookRepo->method('findAll')->willReturn([]);

        $result = $this->useCase->execute(new GetAllBooksQuery());
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }
}
