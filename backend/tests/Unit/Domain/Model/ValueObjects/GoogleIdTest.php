<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model\ValueObjects;

use App\Domain\Model\ValueObjects\GoogleId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GoogleIdTest extends TestCase
{
    private const VALID_GOOGLE_ID = '123456789012345678901'; // 21 digits, typical

    // ── Valid creation ──

    #[Test]
    public function creates_from_valid_string(): void
    {
        $id = GoogleId::fromString(self::VALID_GOOGLE_ID);
        $this->assertEquals(self::VALID_GOOGLE_ID, $id->toString());
    }

    #[Test]
    public function creates_from_10_char_minimum(): void
    {
        $id = GoogleId::fromString('1234567890');
        $this->assertEquals('1234567890', $id->toString());
    }

    #[Test]
    public function creates_from_255_char_maximum(): void
    {
        $long = str_repeat('a', 255);
        $id = GoogleId::fromString($long);
        $this->assertEquals($long, $id->toString());
    }

    // ── Invalid creation ──

    #[Test]
    public function throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        GoogleId::fromString('');
    }

    #[Test]
    public function throws_on_whitespace_only(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GoogleId::fromString('   ');
    }

    #[Test]
    public function throws_on_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('between 10 and 255');
        GoogleId::fromString('123456789'); // 9 chars
    }

    #[Test]
    public function throws_on_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('between 10 and 255');
        GoogleId::fromString(str_repeat('a', 256));
    }

    #[Test]
    public function throws_on_angle_brackets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid characters');
        GoogleId::fromString('1234567890<script>');
    }

    #[Test]
    public function throws_on_quotes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GoogleId::fromString('1234567890"alert');
    }

    #[Test]
    public function throws_on_single_quotes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GoogleId::fromString("1234567890'test");
    }

    // ── Nullable ──

    #[Test]
    public function from_nullable_returns_null_for_null(): void
    {
        $this->assertNull(GoogleId::fromNullableString(null));
    }

    #[Test]
    public function from_nullable_returns_id_for_valid_string(): void
    {
        $id = GoogleId::fromNullableString(self::VALID_GOOGLE_ID);
        $this->assertNotNull($id);
        $this->assertEquals(self::VALID_GOOGLE_ID, $id->toString());
    }

    // ── Equality ──

    #[Test]
    public function equal_ids_match(): void
    {
        $a = GoogleId::fromString(self::VALID_GOOGLE_ID);
        $b = GoogleId::fromString(self::VALID_GOOGLE_ID);
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function different_ids_do_not_match(): void
    {
        $a = GoogleId::fromString('1234567890123456789aa');
        $b = GoogleId::fromString('1234567890123456789bb');
        $this->assertFalse($a->equals($b));
    }

    // ── Masking ──

    #[Test]
    public function to_masked_hides_middle_for_long_ids(): void
    {
        $id = GoogleId::fromString(self::VALID_GOOGLE_ID);
        $masked = $id->toMasked();
        // first 4 + *** + last 4
        $this->assertEquals('1234***8901', $masked);
    }

    #[Test]
    public function to_masked_returns_stars_for_short_ids(): void
    {
        // Exactly 8 chars or less → '***'
        // But min is 10, so test with 10 (which is > 8, should mask)
        $id = GoogleId::fromString('1234567890');
        $masked = $id->toMasked();
        $this->assertEquals('1234***7890', $masked);
    }

    // ── __toString ──

    #[Test]
    public function to_string_magic_works(): void
    {
        $id = GoogleId::fromString(self::VALID_GOOGLE_ID);
        $this->assertEquals(self::VALID_GOOGLE_ID, (string) $id);
    }
}
