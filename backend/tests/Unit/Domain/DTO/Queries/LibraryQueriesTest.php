<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Queries;

use App\Domain\DTO\Queries\GetAllowedStatusesQuery;
use App\Domain\DTO\Queries\GetLibraryItemsQuery;
use App\Domain\DTO\Queries\GetLibraryQuery;
use App\Domain\DTO\Queries\GetTrendingGamesQuery;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LibraryQueriesTest extends TestCase
{
    // ═══════════════════════════════════════
    // GetAllowedStatusesQuery
    // ═══════════════════════════════════════

    #[Test]
    public function allowed_statuses_for_books(): void
    {
        $q = GetAllowedStatusesQuery::forBooks();
        $this->assertEquals('book', $q->entityType);
    }

    #[Test]
    public function allowed_statuses_for_movies(): void
    {
        $q = GetAllowedStatusesQuery::forMovies();
        $this->assertEquals('movie', $q->entityType);
    }

    #[Test]
    public function allowed_statuses_throws_on_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GetAllowedStatusesQuery('game');
    }

    // ═══════════════════════════════════════
    // GetLibraryQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_library_constructor(): void
    {
        $q = new GetLibraryQuery(userId: 1, itemType: 'book', status: 'reading', sortBy: 'title', sortOrder: 'asc');

        $this->assertSame(1, $q->userId);
        $this->assertEquals('book', $q->itemType);
        $this->assertEquals('reading', $q->status);
        $this->assertEquals('title', $q->sortBy);
        $this->assertEquals('asc', $q->sortOrder);
    }

    #[Test]
    public function get_library_defaults(): void
    {
        $q = new GetLibraryQuery(userId: 1);

        $this->assertNull($q->itemType);
        $this->assertNull($q->status);
        $this->assertNull($q->sortBy);
        $this->assertEquals('desc', $q->sortOrder);
    }

    #[Test]
    public function get_library_throws_on_invalid_item_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GetLibraryQuery(userId: 1, itemType: 'game');
    }

    #[Test]
    public function get_library_accepts_movie_type(): void
    {
        $q = new GetLibraryQuery(userId: 1, itemType: 'movie');
        $this->assertEquals('movie', $q->itemType);
    }

    #[Test]
    public function get_library_builds_filters(): void
    {
        $q = new GetLibraryQuery(userId: 1, itemType: 'book', status: 'reading', sortBy: 'title');
        $filters = $q->toFilters();

        $this->assertEquals('book', $filters['itemType']);
        $this->assertEquals('reading', $filters['status']);
        $this->assertEquals('title', $filters['sortBy']);
        $this->assertEquals('desc', $filters['sortOrder']);
    }

    #[Test]
    public function get_library_empty_filters(): void
    {
        $q = new GetLibraryQuery(userId: 1);
        $this->assertEmpty($q->toFilters());
    }

    #[Test]
    public function get_library_from_array(): void
    {
        $q = GetLibraryQuery::fromArray([
            'itemType' => 'movie',
            'status' => 'watched',
            'sortBy' => 'title',
            'sortOrder' => 'asc',
        ], 5);

        $this->assertSame(5, $q->userId);
        $this->assertEquals('movie', $q->itemType);
        $this->assertEquals('watched', $q->status);
    }

    // ═══════════════════════════════════════
    // GetLibraryItemsQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_library_items_constructor(): void
    {
        $q = new GetLibraryItemsQuery(title: 'Search', status: 'reading', sortBy: 'author', sortOrder: 'desc');

        $this->assertEquals('Search', $q->title);
        $this->assertEquals('reading', $q->status);
        $this->assertEquals('author', $q->sortBy);
        $this->assertEquals('desc', $q->sortOrder);
    }

    #[Test]
    public function get_library_items_defaults(): void
    {
        $q = new GetLibraryItemsQuery();

        $this->assertNull($q->title);
        $this->assertNull($q->status);
        $this->assertEquals('title', $q->sortBy);
        $this->assertEquals('asc', $q->sortOrder);
    }

    #[Test]
    public function get_library_items_from_array(): void
    {
        $q = GetLibraryItemsQuery::fromArray([
            'title' => 'Tolkien',
            'status' => 'completed',
            'sortBy' => 'author',
            'sortOrder' => 'desc',
        ]);

        $this->assertEquals('Tolkien', $q->title);
        $this->assertEquals('completed', $q->status);
        $this->assertEquals('author', $q->sortBy);
        $this->assertEquals('desc', $q->sortOrder);
    }

    #[Test]
    public function get_library_items_to_filters_array(): void
    {
        $q = new GetLibraryItemsQuery(title: 'Search', status: 'reading');
        $filters = $q->toFiltersArray();

        $this->assertEquals('Search', $filters['title']);
        $this->assertEquals('reading', $filters['status']);
        $this->assertEquals('title', $filters['sortBy']);
        $this->assertEquals('asc', $filters['sortOrder']);
    }

    #[Test]
    public function get_library_items_to_filters_excludes_nulls(): void
    {
        $q = new GetLibraryItemsQuery();
        $filters = $q->toFiltersArray();

        $this->assertArrayNotHasKey('title', $filters);
        $this->assertArrayNotHasKey('status', $filters);
        $this->assertArrayHasKey('sortBy', $filters);
        $this->assertArrayHasKey('sortOrder', $filters);
    }

    // ═══════════════════════════════════════
    // GetTrendingGamesQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_trending_games_defaults(): void
    {
        $q = new GetTrendingGamesQuery();

        $this->assertSame(20, $q->limit);
        $this->assertSame(90, $q->daysWindow);
        $this->assertNull($q->userId);
    }

    #[Test]
    public function get_trending_games_create(): void
    {
        $q = GetTrendingGamesQuery::create(10, 30, 5);

        $this->assertSame(10, $q->limit);
        $this->assertSame(30, $q->daysWindow);
        $this->assertSame(5, $q->userId);
    }

    #[Test]
    public function get_trending_games_from_array(): void
    {
        $q = GetTrendingGamesQuery::fromArray([
            'limit' => 5,
            'daysWindow' => 60,
            'userId' => 3,
        ]);

        $this->assertSame(5, $q->limit);
        $this->assertSame(60, $q->daysWindow);
        $this->assertSame(3, $q->userId);
    }

    #[Test]
    public function get_trending_games_from_array_defaults(): void
    {
        $q = GetTrendingGamesQuery::fromArray([]);

        $this->assertSame(20, $q->limit);
        $this->assertSame(90, $q->daysWindow);
        $this->assertNull($q->userId);
    }
}
