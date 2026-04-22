<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model\ValueObjects;

use App\Domain\Model\ValueObjects\SpotifyId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SpotifyIdTest extends TestCase
{
    // ── Valid IDs ──

    #[Test]
    public function creates_from_valid_spotify_id(): void
    {
        $id = SpotifyId::fromString('4aawyAB9vmqN3uQ7FjRGTy');
        $this->assertEquals('4aawyAB9vmqN3uQ7FjRGTy', $id->toString());
    }

    #[Test]
    public function to_string_magic_method_works(): void
    {
        $id = SpotifyId::fromString('4aawyAB9vmqN3uQ7FjRGTy');
        $this->assertEquals('4aawyAB9vmqN3uQ7FjRGTy', (string)$id);
    }

    #[Test]
    #[DataProvider('validSpotifyIdsProvider')]
    public function creates_from_valid_ids(string $input): void
    {
        $id = SpotifyId::fromString($input);
        $this->assertEquals($input, $id->toString());
    }

    public static function validSpotifyIdsProvider(): array
    {
        return [
            '22 chars' => ['4aawyAB9vmqN3uQ7FjRGTy'],
            '21 chars' => ['7tFiyTwD0nx5a1eklYtX2J'],
            '20 chars' => ['1DFixLWuPkv3KT3TnV35m3'],
            '15 chars' => ['123456789012345'],
            '25 chars' => ['1234567890abcdefghijklmno'],
            'all caps' => ['ABCDEFGHIJKLMNOPQRSTUVW'],
            'mixed case' => ['aAbBcCdDeEfFgGhHiIjJkKl'],
        ];
    }

    #[Test]
    public function from_nullable_string_returns_null_for_null(): void
    {
        $this->assertNull(SpotifyId::fromNullableString(null));
    }

    #[Test]
    public function from_nullable_string_returns_instance_for_valid_id(): void
    {
        $id = SpotifyId::fromNullableString('4aawyAB9vmqN3uQ7FjRGTy');
        $this->assertNotNull($id);
        $this->assertEquals('4aawyAB9vmqN3uQ7FjRGTy', $id->toString());
    }

    // ── Equality ──

    #[Test]
    public function equal_ids_are_equal(): void
    {
        $a = SpotifyId::fromString('4aawyAB9vmqN3uQ7FjRGTy');
        $b = SpotifyId::fromString('4aawyAB9vmqN3uQ7FjRGTy');
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function different_ids_are_not_equal(): void
    {
        $a = SpotifyId::fromString('4aawyAB9vmqN3uQ7FjRGTy');
        $b = SpotifyId::fromString('7tFiyTwD0nx5a1eklYtX2J');
        $this->assertFalse($a->equals($b));
    }

    // ── Invalid IDs ──

    #[Test]
    public function throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Spotify ID cannot be empty');
        SpotifyId::fromString('');
    }

    #[Test]
    public function throws_on_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Spotify ID format');
        SpotifyId::fromString('abc123');
    }

    #[Test]
    public function throws_on_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Spotify ID format');
        SpotifyId::fromString('12345678901234567890123456'); // 26 chars
    }

    #[Test]
    public function throws_on_hyphens(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SpotifyId::fromString('4aawy-AB9vm-qN3uQ7Fj');
    }

    #[Test]
    public function throws_on_spaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SpotifyId::fromString('4aawyAB9vm qN3uQ7Fj');
    }

    #[Test]
    public function throws_on_special_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SpotifyId::fromString('4aawyAB9vmqN3uQ7Fj!@#');
    }
}
