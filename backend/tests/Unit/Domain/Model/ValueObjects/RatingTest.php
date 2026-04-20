<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model\ValueObjects;

use App\Domain\Model\ValueObjects\Rating;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RatingTest extends TestCase
{
    // ── Valid ratings ──

    #[Test]
    #[DataProvider('validRatingsProvider')]
    public function creates_from_valid_float(float $input, string $expectedString): void
    {
        $rating = Rating::fromFloat($input);
        $this->assertEquals($expectedString, $rating->toString());
    }

    public static function validRatingsProvider(): array
    {
        return [
            'minimum'     => [0.5, '0.5'],
            'one'         => [1.0, '1.0'],
            'one-and-half'=> [1.5, '1.5'],
            'two'         => [2.0, '2.0'],
            'two-and-half'=> [2.5, '2.5'],
            'three'       => [3.0, '3.0'],
            'three-half'  => [3.5, '3.5'],
            'four'        => [4.0, '4.0'],
            'four-half'   => [4.5, '4.5'],
            'maximum'     => [5.0, '5.0'],
        ];
    }

    // ── Invalid ratings ──

    #[Test]
    public function throws_on_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('between 0.5 and 5.0');
        Rating::fromFloat(0.0);
    }

    #[Test]
    public function throws_on_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rating::fromFloat(-1.0);
    }

    #[Test]
    public function throws_on_above_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rating::fromFloat(5.5);
    }

    #[Test]
    public function throws_on_non_half_increment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple of 0.5');
        Rating::fromFloat(2.3);
    }

    #[Test]
    public function throws_on_small_non_multiple(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rating::fromFloat(1.1);
    }

    // ── Nullable ──

    #[Test]
    public function from_nullable_returns_null_for_null(): void
    {
        $this->assertNull(Rating::fromNullableFloat(null));
    }

    #[Test]
    public function from_nullable_returns_rating_for_valid_float(): void
    {
        $rating = Rating::fromNullableFloat(4.5);
        $this->assertNotNull($rating);
        $this->assertEquals(4.5, $rating->toFloat());
    }

    // ── Equality ──

    #[Test]
    public function ratings_with_same_value_are_equal(): void
    {
        $a = Rating::fromFloat(3.5);
        $b = Rating::fromFloat(3.5);
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function ratings_with_different_values_are_not_equal(): void
    {
        $a = Rating::fromFloat(3.5);
        $b = Rating::fromFloat(4.0);
        $this->assertFalse($a->equals($b));
    }

    // ── High / Low ──

    #[Test]
    public function is_high_returns_true_for_4_and_above(): void
    {
        $this->assertTrue(Rating::fromFloat(4.0)->isHigh());
        $this->assertTrue(Rating::fromFloat(4.5)->isHigh());
        $this->assertTrue(Rating::fromFloat(5.0)->isHigh());
    }

    #[Test]
    public function is_high_returns_false_below_4(): void
    {
        $this->assertFalse(Rating::fromFloat(3.5)->isHigh());
        $this->assertFalse(Rating::fromFloat(1.0)->isHigh());
    }

    #[Test]
    public function is_low_returns_true_below_3(): void
    {
        $this->assertTrue(Rating::fromFloat(0.5)->isLow());
        $this->assertTrue(Rating::fromFloat(1.0)->isLow());
        $this->assertTrue(Rating::fromFloat(2.5)->isLow());
    }

    #[Test]
    public function is_low_returns_false_for_3_and_above(): void
    {
        $this->assertFalse(Rating::fromFloat(3.0)->isLow());
        $this->assertFalse(Rating::fromFloat(4.5)->isLow());
    }

    // ── Conversion ──

    #[Test]
    public function to_float_returns_numeric_value(): void
    {
        $rating = Rating::fromFloat(3.5);
        $this->assertSame(3.5, $rating->toFloat());
    }

    #[Test]
    public function to_string_magic_works(): void
    {
        $rating = Rating::fromFloat(4.0);
        $this->assertEquals('4.0', (string) $rating);
    }
}
