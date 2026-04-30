<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model\ValueObjects;

use App\Domain\Model\ValueObjects\MovieIdentifier;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MovieIdentifierTest extends TestCase
{
    // ── Auto-detection ──

    #[Test]
    public function detects_imdb_id(): void
    {
        $id = MovieIdentifier::fromString('tt1234567');
        $this->assertTrue($id->isImdb());
        $this->assertFalse($id->isTmdb());
        $this->assertEquals('imdb', $id->getType());
        $this->assertEquals('tt1234567', $id->toString());
    }

    #[Test]
    public function detects_tmdb_id(): void
    {
        $id = MovieIdentifier::fromString('12345');
        $this->assertTrue($id->isTmdb());
        $this->assertFalse($id->isImdb());
        $this->assertEquals('tmdb', $id->getType());
    }

    #[Test]
    public function detects_isbn_id(): void
    {
        $id = MovieIdentifier::fromString('9783161484100');
        $this->assertTrue($id->isIsbn());
        $this->assertEquals('isbn', $id->getType());
    }

    #[Test]
    public function detects_custom_id(): void
    {
        $id = MovieIdentifier::fromString('my-custom-id');
        $this->assertEquals('custom', $id->getType());
    }

    // ── Explicit factory methods ──

    #[Test]
    public function creates_from_imdb(): void
    {
        $id = MovieIdentifier::fromImdb('tt0111161');
        $this->assertTrue($id->isImdb());
        $this->assertEquals('tt0111161', $id->toString());
    }

    #[Test]
    public function creates_from_tmdb(): void
    {
        $id = MovieIdentifier::fromTmdb('550');
        $this->assertTrue($id->isTmdb());
    }

    #[Test]
    public function creates_from_isbn(): void
    {
        $id = MovieIdentifier::fromIsbn('9783161484100');
        $this->assertTrue($id->isIsbn());
    }

    #[Test]
    public function creates_from_custom(): void
    {
        $id = MovieIdentifier::fromCustom('any-string-here');
        $this->assertEquals('custom', $id->getType());
    }

    // ── Invalid inputs ──

    #[Test]
    public function throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        MovieIdentifier::fromString('');
    }

    #[Test]
    public function throws_on_whitespace_only(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MovieIdentifier::fromString('   ');
    }

    #[Test]
    public function throws_on_invalid_imdb_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IMDB ID format');
        MovieIdentifier::fromImdb('tt12'); // too short
    }

    #[Test]
    public function throws_on_non_numeric_tmdb(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be numeric');
        MovieIdentifier::fromTmdb('abc');
    }

    #[Test]
    public function throws_on_invalid_isbn_for_movie(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISBN for movie');
        MovieIdentifier::fromIsbn('1234567890'); // bad checksum
    }

    #[Test]
    public function throws_on_too_long_custom_id(): void
    {
        $longId = str_repeat('x', 256);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('too long');
        MovieIdentifier::fromCustom($longId);
    }

    // ── Nullable ──

    #[Test]
    public function from_nullable_returns_null_for_null(): void
    {
        $this->assertNull(MovieIdentifier::fromNullableString(null));
    }

    #[Test]
    public function from_nullable_returns_identifier_for_valid(): void
    {
        $id = MovieIdentifier::fromNullableString('tt1234567');
        $this->assertNotNull($id);
        $this->assertTrue($id->isImdb());
    }

    // ── Equality ──

    #[Test]
    public function equal_identifiers_match(): void
    {
        $a = MovieIdentifier::fromImdb('tt1234567');
        $b = MovieIdentifier::fromImdb('tt1234567');
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function different_values_do_not_match(): void
    {
        $a = MovieIdentifier::fromImdb('tt1234567');
        $b = MovieIdentifier::fromImdb('tt7654321');
        $this->assertFalse($a->equals($b));
    }

    #[Test]
    public function same_value_different_types_do_not_match(): void
    {
        // A numeric string < 9 digits: auto-detected as tmdb
        $tmdb = MovieIdentifier::fromTmdb('12345');
        // Explicitly custom
        $custom = MovieIdentifier::fromCustom('12345');
        $this->assertFalse($tmdb->equals($custom));
    }

    // ── Display ──

    #[Test]
    public function to_display_formats_correctly(): void
    {
        $this->assertEquals('IMDB: tt1234567', MovieIdentifier::fromImdb('tt1234567')->toDisplay());
        $this->assertEquals('TMDB: 550', MovieIdentifier::fromTmdb('550')->toDisplay());
        $this->assertEquals('ISBN: 9783161484100', MovieIdentifier::fromIsbn('9783161484100')->toDisplay());
        $this->assertEquals('my-id', MovieIdentifier::fromCustom('my-id')->toDisplay());
    }

    // ── __toString ──

    #[Test]
    public function to_string_magic_works(): void
    {
        $id = MovieIdentifier::fromImdb('tt1234567');
        $this->assertEquals('tt1234567', (string) $id);
    }
}
