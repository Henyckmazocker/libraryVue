<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model\ValueObjects;

use App\Domain\Model\ValueObjects\Genre;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GenreTest extends TestCase
{
    // ── Valid creation ──

    #[Test]
    public function creates_from_valid_string(): void
    {
        $genre = Genre::fromString('Science Fiction');
        $this->assertEquals('Science Fiction', $genre->toString());
    }

    #[Test]
    public function creates_from_single_word(): void
    {
        $genre = Genre::fromString('Horror');
        $this->assertEquals('Horror', $genre->toString());
    }

    #[Test]
    public function accepts_max_length_100(): void
    {
        $name = str_repeat('a', 100);
        $genre = Genre::fromString($name);
        $this->assertEquals($name, $genre->toString());
    }

    // ── Invalid creation ──

    #[Test]
    public function throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        Genre::fromString('');
    }

    #[Test]
    public function throws_on_whitespace_only(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Genre::fromString('   ');
    }

    #[Test]
    public function throws_on_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('too long');
        Genre::fromString(str_repeat('a', 101));
    }

    // ── Nullable ──

    #[Test]
    public function from_nullable_returns_null_for_null(): void
    {
        $this->assertNull(Genre::fromNullableString(null));
    }

    #[Test]
    public function from_nullable_returns_genre_for_valid(): void
    {
        $genre = Genre::fromNullableString('Drama');
        $this->assertNotNull($genre);
        $this->assertEquals('Drama', $genre->toString());
    }

    // ── Equality (case-insensitive) ──

    #[Test]
    public function equal_genres_match_case_insensitive(): void
    {
        $a = Genre::fromString('horror');
        $b = Genre::fromString('Horror');
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function different_genres_do_not_match(): void
    {
        $a = Genre::fromString('Horror');
        $b = Genre::fromString('Comedy');
        $this->assertFalse($a->equals($b));
    }

    // ── Normalization ──

    #[Test]
    public function to_normalized_returns_title_case(): void
    {
        $genre = Genre::fromString('science fiction');
        $this->assertEquals('Science Fiction', $genre->toNormalized());
    }

    #[Test]
    public function to_normalized_handles_all_caps(): void
    {
        $genre = Genre::fromString('HORROR');
        $this->assertEquals('Horror', $genre->toNormalized());
    }

    #[Test]
    public function to_normalized_preserves_single_word(): void
    {
        $genre = Genre::fromString('drama');
        $this->assertEquals('Drama', $genre->toNormalized());
    }

    // ── __toString ──

    #[Test]
    public function to_string_magic_works(): void
    {
        $genre = Genre::fromString('Comedy');
        $this->assertEquals('Comedy', (string) $genre);
    }
}
