<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\GetTrendingBooksUseCase;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\DTO\Queries\GetTrendingBooksQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetTrendingBooksUseCaseTest extends TestCase
{
    private GetTrendingBooksUseCase $useCase;
    private UserBookRepositoryInterface $userBookRepo;

    protected function setUp(): void
    {
        $this->userBookRepo = $this->createMock(UserBookRepositoryInterface::class);
        $this->useCase = new GetTrendingBooksUseCase($this->userBookRepo, new NullLogger());
    }

    #[Test]
    public function successfully_returns_trending_books(): void
    {
        $trending = [
            ['title' => 'Popular Book', 'count' => 10],
            ['title' => 'Another Book', 'count' => 5],
        ];
        $this->userBookRepo->method('getTrendingBooks')
            ->with(20, 90, null)
            ->willReturn($trending);

        $query = GetTrendingBooksQuery::create(20, 90);
        $result = $this->useCase->execute($query);

        $this->assertCount(2, $result);
        $this->assertSame('Popular Book', $result[0]['title']);
    }

    #[Test]
    public function passes_custom_limit_and_window(): void
    {
        $this->userBookRepo->expects($this->once())
            ->method('getTrendingBooks')
            ->with(5, 30, 1)
            ->willReturn([]);

        $query = GetTrendingBooksQuery::create(5, 30, 1);
        $result = $this->useCase->execute($query);

        $this->assertEmpty($result);
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }
}
