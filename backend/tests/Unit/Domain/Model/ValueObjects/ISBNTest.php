<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model\ValueObjects;

use App\Domain\Model\ValueObjects\ISBN;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ISBNTest extends TestCase
{
    // ── Valid ISBN-13 ──

    #[Test]
    public function creates_from_valid_isbn13(): void
    {
        $isbn = ISBN::fromString('9783161484100');
        $this->assertEquals('9783161484100', $isbn->toString());
        $this->assertTrue($isbn->isISBN13());
        $this->assertFalse($isbn->isISBN10());
    }

    #[Test]
    public function creates_from_isbn13_with_hyphens(): void
    {
        $isbn = ISBN::fromString('978-3-16-148410-0');
        $this->assertEquals('9783161484100', $isbn->toString());
    }

    #[Test]
    public function creates_from_isbn13_with_spaces(): void
    {
        $isbn = ISBN::fromString('978 3 16 148410 0');
        $this->assertEquals('9783161484100', $isbn->toString());
    }

    // ── Valid ISBN-10 ──

    #[Test]
    public function creates_from_valid_isbn10(): void
    {
        $isbn = ISBN::fromString('0306406152');
        $this->assertEquals('0306406152', $isbn->toString());
        $this->assertTrue($isbn->isISBN10());
        $this->assertFalse($isbn->isISBN13());
    }

    #[Test]
    public function creates_from_isbn10_with_hyphens(): void
    {
        $isbn = ISBN::fromString('0-306-40615-2');
        $this->assertEquals('0306406152', $isbn->toString());
    }

    #[Test]
    public function creates_isbn10_with_x_check_digit(): void
    {
        $isbn = ISBN::fromString('080442957X');
        $this->assertEquals('080442957X', $isbn->toString());
        $this->assertTrue($isbn->isISBN10());
    }

    #[Test]
    public function creates_isbn10_with_lowercase_x(): void
    {
        $isbn = ISBN::fromString('080442957x');
        $this->assertEquals('080442957x', $isbn->toString());
    }

    // ── Invalid ISBNs ──

    #[Test]
    public function throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN cannot be empty');
        ISBN::fromString('');
    }

    #[Test]
    public function throws_on_wrong_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be 10 or 13 characters');
        ISBN::fromString('12345');
    }

    #[Test]
    public function throws_on_invalid_isbn13_checksum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISBN-13 checksum');
        ISBN::fromString('9783161484101'); // wrong check digit
    }

    #[Test]
    public function throws_on_invalid_isbn10_checksum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISBN-10 checksum');
        ISBN::fromString('0306406153'); // wrong check digit
    }

    #[Test]
    public function throws_on_isbn13_with_letters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ISBN::fromString('978316148410A');
    }

    // ── Nullable ──

    #[Test]
    public function from_nullable_returns_null_for_null(): void
    {
        $this->assertNull(ISBN::fromNullableString(null));
    }

    #[Test]
    public function from_nullable_returns_isbn_for_valid_string(): void
    {
        $isbn = ISBN::fromNullableString('9783161484100');
        $this->assertNotNull($isbn);
        $this->assertEquals('9783161484100', $isbn->toString());
    }

    // ── Equality ──

    #[Test]
    public function two_isbns_with_same_value_are_equal(): void
    {
        $isbn1 = ISBN::fromString('9783161484100');
        $isbn2 = ISBN::fromString('978-3-16-148410-0');
        $this->assertTrue($isbn1->equals($isbn2));
    }

    #[Test]
    public function two_isbns_with_different_value_are_not_equal(): void
    {
        $isbn1 = ISBN::fromString('9783161484100');
        $isbn2 = ISBN::fromString('0306406152');
        $this->assertFalse($isbn1->equals($isbn2));
    }

    // ── Formatting ──

    #[Test]
    public function formats_isbn13(): void
    {
        $isbn = ISBN::fromString('9783161484100');
        $this->assertEquals('978-3-161-48410-0', $isbn->toFormatted());
    }

    #[Test]
    public function formats_isbn10(): void
    {
        $isbn = ISBN::fromString('0306406152');
        $this->assertEquals('0-306-40615-2', $isbn->toFormatted());
    }

    // ── __toString ──

    #[Test]
    public function to_string_returns_cleaned_value(): void
    {
        $isbn = ISBN::fromString('978-3-16-148410-0');
        $this->assertEquals('9783161484100', (string) $isbn);
    }
}
