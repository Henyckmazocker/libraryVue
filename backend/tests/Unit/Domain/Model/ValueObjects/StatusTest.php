<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model\ValueObjects;

use App\Domain\Model\ValueObjects\Status;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatusTest extends TestCase
{
    // ── Valid creation ──

    #[Test]
    public function creates_from_valid_lowercase_string(): void
    {
        $status = Status::fromString('reading');
        $this->assertEquals('reading', $status->toString());
    }

    #[Test]
    public function creates_from_hyphenated_string(): void
    {
        $status = Status::fromString('to-read');
        $this->assertEquals('to-read', $status->toString());
    }

    #[Test]
    public function creates_containing_numbers(): void
    {
        $status = Status::fromString('tier-1');
        $this->assertEquals('tier-1', $status->toString());
    }

    // ── Normalization ──

    #[Test]
    public function normalizes_uppercase_to_lowercase(): void
    {
        $status = Status::fromString('Reading');
        $this->assertEquals('reading', $status->toString());
    }

    #[Test]
    public function normalizes_spaces_to_hyphens(): void
    {
        $status = Status::fromString('to read');
        $this->assertEquals('to-read', $status->toString());
    }

    #[Test]
    public function normalizes_mixed_case_with_spaces(): void
    {
        $status = Status::fromString('Currently Reading');
        $this->assertEquals('currently-reading', $status->toString());
    }

    #[Test]
    public function trims_whitespace(): void
    {
        $status = Status::fromString('  reading  ');
        $this->assertEquals('reading', $status->toString());
    }

    // ── Invalid creation ──

    #[Test]
    public function throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        Status::fromString('');
    }

    #[Test]
    public function throws_on_whitespace_only(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Status::fromString('   ');
    }

    #[Test]
    public function throws_on_special_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status format');
        Status::fromString('read@ing');
    }

    #[Test]
    public function throws_on_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('too long');
        Status::fromString(str_repeat('a', 51));
    }

    #[Test]
    public function throws_on_leading_hyphen_after_normalization(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Status::fromString('-reading');
    }

    #[Test]
    public function throws_on_trailing_hyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Status::fromString('reading-');
    }

    #[Test]
    public function throws_on_double_hyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Status::fromString('to--read');
    }

    // ── Nullable ──

    #[Test]
    public function from_nullable_returns_null_for_null(): void
    {
        $this->assertNull(Status::fromNullableString(null));
    }

    #[Test]
    public function from_nullable_returns_status_for_valid(): void
    {
        $status = Status::fromNullableString('reading');
        $this->assertNotNull($status);
        $this->assertEquals('reading', $status->toString());
    }

    // ── fromArray ──

    #[Test]
    public function from_array_creates_multiple_statuses(): void
    {
        $statuses = Status::fromArray(['reading', 'owned', 'wishlist']);
        $this->assertCount(3, $statuses);
        $this->assertEquals('reading', $statuses[0]->toString());
        $this->assertEquals('owned', $statuses[1]->toString());
        $this->assertEquals('wishlist', $statuses[2]->toString());
    }

    #[Test]
    public function from_array_normalizes_each_entry(): void
    {
        $statuses = Status::fromArray(['To Read', 'COMPLETED']);
        $this->assertEquals('to-read', $statuses[0]->toString());
        $this->assertEquals('completed', $statuses[1]->toString());
    }

    #[Test]
    public function from_array_returns_empty_for_empty_input(): void
    {
        $statuses = Status::fromArray([]);
        $this->assertCount(0, $statuses);
    }

    // ── Equality ──

    #[Test]
    public function equal_statuses_match(): void
    {
        $a = Status::fromString('reading');
        $b = Status::fromString('Reading'); // normalizes
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function different_statuses_do_not_match(): void
    {
        $a = Status::fromString('reading');
        $b = Status::fromString('completed');
        $this->assertFalse($a->equals($b));
    }

    // ── Human readable ──

    #[Test]
    public function to_human_readable_capitalizes_and_removes_hyphens(): void
    {
        $status = Status::fromString('to-read');
        $this->assertEquals('To Read', $status->toHumanReadable());
    }

    #[Test]
    public function to_human_readable_single_word(): void
    {
        $status = Status::fromString('completed');
        $this->assertEquals('Completed', $status->toHumanReadable());
    }

    // ── __toString ──

    #[Test]
    public function to_string_magic_works(): void
    {
        $status = Status::fromString('reading');
        $this->assertEquals('reading', (string) $status);
    }
}
