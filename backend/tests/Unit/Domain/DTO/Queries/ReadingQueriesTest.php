<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Queries;

use App\Domain\DTO\Queries\GetReadingSessionQuery;
use App\Domain\DTO\Queries\GetUserReadingStatsQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReadingQueriesTest extends TestCase
{
    // ═══════════════════════════════════════
    // GetReadingSessionQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_reading_session_constructor(): void
    {
        $q = new GetReadingSessionQuery(userId: 1, isbn: '9783161484100');

        $this->assertSame(1, $q->userId);
        $this->assertEquals('9783161484100', $q->isbn);
    }

    #[Test]
    public function get_reading_session_from_array(): void
    {
        $q = GetReadingSessionQuery::fromArray(['isbn' => '9783161484100'], 5);

        $this->assertSame(5, $q->userId);
        $this->assertEquals('9783161484100', $q->isbn);
    }

    #[Test]
    public function get_reading_session_from_array_defaults(): void
    {
        $q = GetReadingSessionQuery::fromArray([], 1);
        $this->assertEquals('', $q->isbn);
    }

    // ═══════════════════════════════════════
    // GetUserReadingStatsQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_user_reading_stats_constructor(): void
    {
        $q = new GetUserReadingStatsQuery(userId: 42);
        $this->assertSame(42, $q->userId);
    }

    #[Test]
    public function get_user_reading_stats_from_array(): void
    {
        $q = GetUserReadingStatsQuery::fromArray([], 5);
        $this->assertSame(5, $q->userId);
    }
}
