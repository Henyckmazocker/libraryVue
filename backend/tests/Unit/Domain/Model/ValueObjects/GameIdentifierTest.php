<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model\ValueObjects;

use App\Domain\Model\ValueObjects\GameIdentifier;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GameIdentifierTest extends TestCase
{
    // ── Valid creation ──

    #[Test]
    public function creates_from_positive_int(): void
    {
        $id = GameIdentifier::fromInt(12345);
        $this->assertSame(12345, $id->toInt());
        $this->assertEquals('12345', $id->toString());
    }

    #[Test]
    public function creates_from_numeric_string(): void
    {
        $id = GameIdentifier::fromString('67890');
        $this->assertSame(67890, $id->toInt());
    }

    #[Test]
    public function creates_from_one(): void
    {
        $id = GameIdentifier::fromInt(1);
        $this->assertSame(1, $id->toInt());
    }

    // ── Invalid creation ──

    #[Test]
    public function throws_on_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('positive integer');
        GameIdentifier::fromInt(0);
    }

    #[Test]
    public function throws_on_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GameIdentifier::fromInt(-5);
    }

    #[Test]
    public function throws_on_non_numeric_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be numeric');
        GameIdentifier::fromString('abc');
    }

    #[Test]
    public function throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GameIdentifier::fromString('');
    }

    // ── Nullable ──

    #[Test]
    public function from_nullable_int_returns_null(): void
    {
        $this->assertNull(GameIdentifier::fromNullableInt(null));
    }

    #[Test]
    public function from_nullable_int_returns_identifier(): void
    {
        $id = GameIdentifier::fromNullableInt(42);
        $this->assertNotNull($id);
        $this->assertSame(42, $id->toInt());
    }

    #[Test]
    public function from_nullable_string_returns_null(): void
    {
        $this->assertNull(GameIdentifier::fromNullableString(null));
    }

    #[Test]
    public function from_nullable_string_returns_identifier(): void
    {
        $id = GameIdentifier::fromNullableString('42');
        $this->assertNotNull($id);
        $this->assertSame(42, $id->toInt());
    }

    // ── Equality ──

    #[Test]
    public function equal_identifiers_match(): void
    {
        $a = GameIdentifier::fromInt(100);
        $b = GameIdentifier::fromString('100');
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function different_identifiers_do_not_match(): void
    {
        $a = GameIdentifier::fromInt(100);
        $b = GameIdentifier::fromInt(200);
        $this->assertFalse($a->equals($b));
    }

    // ── Static validation ──

    #[Test]
    public function is_valid_returns_true_for_positive(): void
    {
        $this->assertTrue(GameIdentifier::isValid(1));
        $this->assertTrue(GameIdentifier::isValid(999999));
    }

    #[Test]
    public function is_valid_returns_false_for_zero_and_negative(): void
    {
        $this->assertFalse(GameIdentifier::isValid(0));
        $this->assertFalse(GameIdentifier::isValid(-1));
    }

    #[Test]
    public function is_valid_string_checks_correctly(): void
    {
        $this->assertTrue(GameIdentifier::isValidString('42'));
        $this->assertFalse(GameIdentifier::isValidString('abc'));
        $this->assertFalse(GameIdentifier::isValidString('0'));
        $this->assertFalse(GameIdentifier::isValidString('-5'));
    }

    // ── Aliases / serialisation ──

    #[Test]
    public function get_value_is_alias_for_to_int(): void
    {
        $id = GameIdentifier::fromInt(77);
        $this->assertSame($id->toInt(), $id->getValue());
    }

    #[Test]
    public function json_serialize_returns_int(): void
    {
        $id = GameIdentifier::fromInt(42);
        $this->assertSame(42, $id->jsonSerialize());
    }

    #[Test]
    public function to_string_magic_works(): void
    {
        $id = GameIdentifier::fromInt(42);
        $this->assertEquals('42', (string) $id);
    }
}
