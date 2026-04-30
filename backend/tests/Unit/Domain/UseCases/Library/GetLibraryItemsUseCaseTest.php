<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Library;

use App\Domain\UseCases\GetLibraryItemsUseCase;
use App\Domain\UseCases\Books\GetBooksUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetLibraryItemsQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetLibraryItemsUseCaseTest extends TestCase
{
    /**
     * Create a UseCase mock that works with final execute() method.
     * Sets logger via Reflection so the real execute() can call logging methods.
     */
    private function createUseCaseMock(string $class, array $returnValue): object
    {
        $mock = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods(['doExecute', 'getLogContext'])
            ->getMock();

        $mock->method('doExecute')->willReturn($returnValue);
        $mock->method('getLogContext')->willReturn('Test');

        // Set logger via reflection so final execute() can call logExecutionStart
        $ref = new \ReflectionProperty(AbstractUseCase::class, 'logger');
        $ref->setValue($mock, new NullLogger());

        return $mock;
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $getBooksUseCase = $this->createUseCaseMock(GetBooksUseCase::class, []);
        $getMoviesUseCase = $this->createUseCaseMock(GetMoviesUseCase::class, []);

        $useCase = new GetLibraryItemsUseCase($getBooksUseCase, $getMoviesUseCase, new NullLogger());

        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new \stdClass());
    }

    #[Test]
    public function returns_merged_books_and_movies(): void
    {
        $getBooksUseCase = $this->createUseCaseMock(GetBooksUseCase::class, [['title' => 'My Book']]);
        $getMoviesUseCase = $this->createUseCaseMock(GetMoviesUseCase::class, [['title' => 'My Movie']]);

        $useCase = new GetLibraryItemsUseCase($getBooksUseCase, $getMoviesUseCase, new NullLogger());

        $query = new GetLibraryItemsQuery();
        $result = $useCase->execute($query);

        $this->assertCount(2, $result);

        $types = array_column($result, 'itemType');
        $this->assertContains('book', $types);
        $this->assertContains('movie', $types);
    }

    #[Test]
    public function sorts_merged_results_by_title_ascending(): void
    {
        $getBooksUseCase = $this->createUseCaseMock(GetBooksUseCase::class, [['title' => 'Zebra Book']]);
        $getMoviesUseCase = $this->createUseCaseMock(GetMoviesUseCase::class, [['title' => 'Alpha Movie']]);

        $useCase = new GetLibraryItemsUseCase($getBooksUseCase, $getMoviesUseCase, new NullLogger());

        $query = new GetLibraryItemsQuery(sortBy: 'title', sortOrder: 'asc');
        $result = $useCase->execute($query);

        $this->assertSame('Alpha Movie', $result[0]['title']);
        $this->assertSame('Zebra Book', $result[1]['title']);
    }

    #[Test]
    public function sorts_merged_results_descending(): void
    {
        $getBooksUseCase = $this->createUseCaseMock(GetBooksUseCase::class, [['title' => 'Alpha Book']]);
        $getMoviesUseCase = $this->createUseCaseMock(GetMoviesUseCase::class, [['title' => 'Zebra Movie']]);

        $useCase = new GetLibraryItemsUseCase($getBooksUseCase, $getMoviesUseCase, new NullLogger());

        $query = new GetLibraryItemsQuery(sortBy: 'title', sortOrder: 'desc');
        $result = $useCase->execute($query);

        $this->assertSame('Zebra Movie', $result[0]['title']);
        $this->assertSame('Alpha Book', $result[1]['title']);
    }

    #[Test]
    public function passes_filters_to_child_use_cases(): void
    {
        $query = new GetLibraryItemsQuery(title: 'Search', status: 'reading');
        $expectedFilters = $query->toFiltersArray();

        $getBooksUseCase = $this->getMockBuilder(GetBooksUseCase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['doExecute', 'getLogContext'])
            ->getMock();
        $getBooksUseCase->method('doExecute')
            ->with($expectedFilters)
            ->willReturn([]);
        $getBooksUseCase->method('getLogContext')->willReturn('Test');
        $ref = new \ReflectionProperty(AbstractUseCase::class, 'logger');
        $ref->setValue($getBooksUseCase, new NullLogger());

        $getMoviesUseCase = $this->getMockBuilder(GetMoviesUseCase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['doExecute', 'getLogContext'])
            ->getMock();
        $getMoviesUseCase->method('doExecute')
            ->with($expectedFilters)
            ->willReturn([]);
        $getMoviesUseCase->method('getLogContext')->willReturn('Test');
        $ref->setValue($getMoviesUseCase, new NullLogger());

        $useCase = new GetLibraryItemsUseCase($getBooksUseCase, $getMoviesUseCase, new NullLogger());

        $useCase->execute($query);
    }

    #[Test]
    public function returns_empty_when_both_sources_empty(): void
    {
        $getBooksUseCase = $this->createUseCaseMock(GetBooksUseCase::class, []);
        $getMoviesUseCase = $this->createUseCaseMock(GetMoviesUseCase::class, []);

        $useCase = new GetLibraryItemsUseCase($getBooksUseCase, $getMoviesUseCase, new NullLogger());

        $query = new GetLibraryItemsQuery();
        $result = $useCase->execute($query);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
