<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Queries;

use App\Domain\DTO\Queries\GetAllBooksQuery;
use App\Domain\DTO\Queries\GetBooksByUserQuery;
use App\Domain\DTO\Queries\GetTrendingBooksQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BookQueriesTest extends TestCase
{
    // ═══════════════════════════════════════
    // GetAllBooksQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_all_books_create(): void
    {
        $q = GetAllBooksQuery::create();
        $this->assertInstanceOf(GetAllBooksQuery::class, $q);
    }

    #[Test]
    public function get_all_books_from_array(): void
    {
        $q = GetAllBooksQuery::fromArray([]);
        $this->assertInstanceOf(GetAllBooksQuery::class, $q);
    }

    // ═══════════════════════════════════════
    // GetBooksByUserQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_books_by_user_constructor(): void
    {
        $q = new GetBooksByUserQuery(userId: 1, status: 'reading', sortBy: 'title', sortOrder: 'desc');

        $this->assertSame(1, $q->userId);
        $this->assertEquals('reading', $q->status);
        $this->assertEquals('title', $q->sortBy);
        $this->assertEquals('desc', $q->sortOrder);
    }

    #[Test]
    public function get_books_by_user_defaults(): void
    {
        $q = new GetBooksByUserQuery(userId: 1);

        $this->assertNull($q->status);
        $this->assertNull($q->sortBy);
        $this->assertEquals('asc', $q->sortOrder);
    }

    #[Test]
    public function get_books_by_user_builds_filters_with_status(): void
    {
        $q = new GetBooksByUserQuery(userId: 1, status: 'reading');
        $filters = $q->toFilters();

        $this->assertEquals('reading', $filters['userStatus']);
        $this->assertArrayNotHasKey('sortBy', $filters);
    }

    #[Test]
    public function get_books_by_user_builds_filters_with_sort(): void
    {
        $q = new GetBooksByUserQuery(userId: 1, sortBy: 'title', sortOrder: 'desc');
        $filters = $q->toFilters();

        $this->assertEquals('title', $filters['sortBy']);
        $this->assertEquals('desc', $filters['sortOrder']);
        $this->assertArrayNotHasKey('userStatus', $filters);
    }

    #[Test]
    public function get_books_by_user_empty_filters_with_no_params(): void
    {
        $q = new GetBooksByUserQuery(userId: 1);
        $this->assertEmpty($q->toFilters());
    }

    #[Test]
    public function get_books_by_user_from_array(): void
    {
        $q = GetBooksByUserQuery::fromArray([
            'status' => 'completed',
            'sortBy' => 'author',
            'sortOrder' => 'desc',
        ], 5);

        $this->assertSame(5, $q->userId);
        $this->assertEquals('completed', $q->status);
        $this->assertEquals('author', $q->sortBy);
        $this->assertEquals('desc', $q->sortOrder);
    }

    // ═══════════════════════════════════════
    // GetTrendingBooksQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_trending_books_defaults(): void
    {
        $q = new GetTrendingBooksQuery();

        $this->assertSame(20, $q->limit);
        $this->assertSame(90, $q->daysWindow);
        $this->assertNull($q->userId);
    }

    #[Test]
    public function get_trending_books_create_factory(): void
    {
        $q = GetTrendingBooksQuery::create(10, 30, 5);

        $this->assertSame(10, $q->limit);
        $this->assertSame(30, $q->daysWindow);
        $this->assertSame(5, $q->userId);
    }

    #[Test]
    public function get_trending_books_from_array(): void
    {
        $q = GetTrendingBooksQuery::fromArray([
            'limit' => 5,
            'daysWindow' => 60,
            'userId' => 3,
        ]);

        $this->assertSame(5, $q->limit);
        $this->assertSame(60, $q->daysWindow);
        $this->assertSame(3, $q->userId);
    }

    #[Test]
    public function get_trending_books_from_array_defaults(): void
    {
        $q = GetTrendingBooksQuery::fromArray([]);

        $this->assertSame(20, $q->limit);
        $this->assertSame(90, $q->daysWindow);
        $this->assertNull($q->userId);
    }
}
