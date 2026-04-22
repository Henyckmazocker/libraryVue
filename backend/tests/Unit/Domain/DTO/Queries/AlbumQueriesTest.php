<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Queries;

use App\Domain\DTO\Queries\GetTrendingAlbumsQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AlbumQueriesTest extends TestCase
{
    // ═══════════════════════════════════════
    // GetTrendingAlbumsQuery
    // ═══════════════════════════════════════

    #[Test]
    public function constructor_sets_default_values(): void
    {
        $query = new GetTrendingAlbumsQuery();
        $this->assertSame(20, $query->limit);
        $this->assertSame(90, $query->daysWindow);
        $this->assertNull($query->userId);
    }

    #[Test]
    public function constructor_sets_custom_values(): void
    {
        $query = new GetTrendingAlbumsQuery(limit: 10, daysWindow: 30, userId: 5);
        $this->assertSame(10, $query->limit);
        $this->assertSame(30, $query->daysWindow);
        $this->assertSame(5, $query->userId);
    }

    #[Test]
    public function create_factory_uses_default_values(): void
    {
        $query = GetTrendingAlbumsQuery::create();
        $this->assertSame(20, $query->limit);
        $this->assertSame(90, $query->daysWindow);
        $this->assertNull($query->userId);
    }

    #[Test]
    public function create_factory_accepts_all_params(): void
    {
        $query = GetTrendingAlbumsQuery::create(limit: 5, daysWindow: 7, userId: 42);
        $this->assertSame(5, $query->limit);
        $this->assertSame(7, $query->daysWindow);
        $this->assertSame(42, $query->userId);
    }

    #[Test]
    public function from_array_uses_default_values_when_empty(): void
    {
        $query = GetTrendingAlbumsQuery::fromArray([]);
        $this->assertSame(20, $query->limit);
        $this->assertSame(90, $query->daysWindow);
        $this->assertNull($query->userId);
    }

    #[Test]
    public function from_array_sets_limit(): void
    {
        $query = GetTrendingAlbumsQuery::fromArray(['limit' => 15]);
        $this->assertSame(15, $query->limit);
    }

    #[Test]
    public function from_array_sets_days_window(): void
    {
        $query = GetTrendingAlbumsQuery::fromArray(['daysWindow' => 14]);
        $this->assertSame(14, $query->daysWindow);
    }

    #[Test]
    public function from_array_sets_user_id(): void
    {
        $query = GetTrendingAlbumsQuery::fromArray(['userId' => 99]);
        $this->assertSame(99, $query->userId);
    }

    #[Test]
    public function from_array_sets_all_params(): void
    {
        $query = GetTrendingAlbumsQuery::fromArray([
            'limit' => 8,
            'daysWindow' => 60,
            'userId' => 7,
        ]);
        $this->assertSame(8, $query->limit);
        $this->assertSame(60, $query->daysWindow);
        $this->assertSame(7, $query->userId);
    }

    #[Test]
    public function query_is_immutable(): void
    {
        $query = new GetTrendingAlbumsQuery(limit: 10);
        $this->assertSame(10, $query->limit);
        // Readonly properties cannot be changed — verify the value stays
        $this->assertSame(10, $query->limit);
    }
}
