<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model\ValueObjects;

use App\Domain\Model\ValueObjects\Timestamp;
use DateTimeImmutable;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TimestampTest extends TestCase
{
    // ── Factory methods ──

    #[Test]
    public function now_creates_current_timestamp(): void
    {
        $before = new DateTimeImmutable();
        $ts = Timestamp::now();
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $ts->toUnixTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $ts->toUnixTimestamp());
    }

    #[Test]
    public function from_string_with_default_format(): void
    {
        $ts = Timestamp::fromString('2024-06-15 14:30:00');
        $this->assertEquals('2024-06-15 14:30:00', $ts->toString());
    }

    #[Test]
    public function from_string_with_custom_format(): void
    {
        $ts = Timestamp::fromString('15/06/2024', 'd/m/Y');
        $this->assertEquals('15/06/2024', $ts->toString('d/m/Y'));
    }

    #[Test]
    public function from_string_throws_on_invalid_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date string');
        Timestamp::fromString('not-a-date');
    }

    #[Test]
    public function from_unix_timestamp(): void
    {
        $epoch = 1718451000; // ~2024-06-15
        $ts = Timestamp::fromUnixTimestamp($epoch);
        $this->assertSame($epoch, $ts->toUnixTimestamp());
    }

    #[Test]
    public function from_datetime_immutable(): void
    {
        $dt = new DateTimeImmutable('2024-01-01 00:00:00');
        $ts = Timestamp::fromDateTime($dt);
        $this->assertEquals('2024-01-01 00:00:00', $ts->toString());
    }

    #[Test]
    public function from_mutable_datetime(): void
    {
        $dt = new DateTime('2024-01-01 12:00:00');
        $ts = Timestamp::fromDateTime($dt);
        $this->assertEquals('2024-01-01 12:00:00', $ts->toString());
    }

    // ── Nullable ──

    #[Test]
    public function from_nullable_returns_null_for_null(): void
    {
        $this->assertNull(Timestamp::fromNullableString(null));
    }

    #[Test]
    public function from_nullable_returns_timestamp_for_valid(): void
    {
        $ts = Timestamp::fromNullableString('2024-06-15 14:30:00');
        $this->assertNotNull($ts);
        $this->assertEquals('2024-06-15 14:30:00', $ts->toString());
    }

    // ── Conversion ──

    #[Test]
    public function to_iso8601_returns_atom_format(): void
    {
        $ts = Timestamp::fromString('2024-06-15 14:30:00');
        $iso = $ts->toIso8601();
        $this->assertStringContainsString('2024-06-15T14:30:00', $iso);
    }

    #[Test]
    public function to_date_time_returns_immutable(): void
    {
        $ts = Timestamp::fromString('2024-06-15 14:30:00');
        $dt = $ts->toDateTime();
        $this->assertInstanceOf(DateTimeImmutable::class, $dt);
        $this->assertEquals('2024-06-15', $dt->format('Y-m-d'));
    }

    // ── Comparisons ──

    #[Test]
    public function equals_returns_true_for_same_time(): void
    {
        $a = Timestamp::fromString('2024-06-15 14:30:00');
        $b = Timestamp::fromString('2024-06-15 14:30:00');
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function equals_returns_false_for_different_time(): void
    {
        $a = Timestamp::fromString('2024-06-15 14:30:00');
        $b = Timestamp::fromString('2024-06-15 14:31:00');
        $this->assertFalse($a->equals($b));
    }

    #[Test]
    public function is_before_works(): void
    {
        $earlier = Timestamp::fromString('2024-01-01 00:00:00');
        $later = Timestamp::fromString('2024-12-31 23:59:59');
        $this->assertTrue($earlier->isBefore($later));
        $this->assertFalse($later->isBefore($earlier));
    }

    #[Test]
    public function is_after_works(): void
    {
        $earlier = Timestamp::fromString('2024-01-01 00:00:00');
        $later = Timestamp::fromString('2024-12-31 23:59:59');
        $this->assertTrue($later->isAfter($earlier));
        $this->assertFalse($earlier->isAfter($later));
    }

    #[Test]
    public function is_past_for_old_date(): void
    {
        $past = Timestamp::fromString('2000-01-01 00:00:00');
        $this->assertTrue($past->isPast());
    }

    #[Test]
    public function is_future_for_far_future(): void
    {
        $future = Timestamp::fromString('2099-12-31 23:59:59');
        $this->assertTrue($future->isFuture());
    }

    #[Test]
    public function is_today_for_today(): void
    {
        $today = Timestamp::now();
        $this->assertTrue($today->isToday());
    }

    #[Test]
    public function is_today_false_for_yesterday(): void
    {
        $yesterday = Timestamp::now()->subDays(1);
        $this->assertFalse($yesterday->isToday());
    }

    // ── Arithmetic ──

    #[Test]
    public function add_days_returns_new_timestamp(): void
    {
        $ts = Timestamp::fromString('2024-06-15 00:00:00');
        $added = $ts->addDays(10);

        $this->assertEquals('2024-06-25', $added->toString('Y-m-d'));
        // Original is unchanged (immutable)
        $this->assertEquals('2024-06-15', $ts->toString('Y-m-d'));
    }

    #[Test]
    public function sub_days_returns_new_timestamp(): void
    {
        $ts = Timestamp::fromString('2024-06-15 00:00:00');
        $subtracted = $ts->subDays(5);
        $this->assertEquals('2024-06-10', $subtracted->toString('Y-m-d'));
    }

    #[Test]
    public function diff_in_days_calculates_correctly(): void
    {
        $a = Timestamp::fromString('2024-06-01 00:00:00');
        $b = Timestamp::fromString('2024-06-15 00:00:00');
        $this->assertSame(14, $a->diffInDays($b));
    }

    // ── Human readable ──

    #[Test]
    public function to_human_readable_shows_just_now_for_recent(): void
    {
        $ts = Timestamp::now();
        $this->assertEquals('just now', $ts->toHumanReadable());
    }

    #[Test]
    public function to_human_readable_shows_days_ago(): void
    {
        $ts = Timestamp::now()->subDays(3);
        $this->assertEquals('3 days ago', $ts->toHumanReadable());
    }

    #[Test]
    public function to_human_readable_shows_1_day_ago(): void
    {
        $ts = Timestamp::now()->subDays(1);
        $this->assertEquals('1 day ago', $ts->toHumanReadable());
    }

    // ── __toString ──

    #[Test]
    public function to_string_magic_uses_default_format(): void
    {
        $ts = Timestamp::fromString('2024-06-15 14:30:00');
        $this->assertEquals('2024-06-15 14:30:00', (string) $ts);
    }
}
