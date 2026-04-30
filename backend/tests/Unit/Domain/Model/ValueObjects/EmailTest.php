<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model\ValueObjects;

use App\Domain\Model\ValueObjects\Email;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    // ── Valid emails ──

    #[Test]
    public function creates_from_valid_email(): void
    {
        $email = Email::fromString('user@example.com');
        $this->assertEquals('user@example.com', $email->toString());
    }

    #[Test]
    public function normalizes_to_lowercase(): void
    {
        $email = Email::fromString('User@Example.COM');
        $this->assertEquals('user@example.com', $email->toString());
    }

    #[Test]
    public function trims_whitespace(): void
    {
        $email = Email::fromString('  user@example.com  ');
        $this->assertEquals('user@example.com', $email->toString());
    }

    #[Test]
    public function accepts_email_with_plus(): void
    {
        $email = Email::fromString('user+tag@example.com');
        $this->assertEquals('user+tag@example.com', $email->toString());
    }

    #[Test]
    public function accepts_email_with_dots_in_local(): void
    {
        $email = Email::fromString('first.last@example.com');
        $this->assertEquals('first.last@example.com', $email->toString());
    }

    #[Test]
    public function accepts_subdomain_email(): void
    {
        $email = Email::fromString('user@mail.example.com');
        $this->assertEquals('user@mail.example.com', $email->toString());
    }

    // ── Invalid emails ──

    #[Test]
    public function throws_on_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email cannot be empty');
        Email::fromString('');
    }

    #[Test]
    public function throws_on_whitespace_only(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Email::fromString('   ');
    }

    #[Test]
    public function throws_on_missing_at_sign(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');
        Email::fromString('userexample.com');
    }

    #[Test]
    public function throws_on_missing_domain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Email::fromString('user@');
    }

    #[Test]
    public function throws_on_missing_local_part(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Email::fromString('@example.com');
    }

    #[Test]
    public function throws_on_too_long_email(): void
    {
        $local = str_repeat('a', 250);
        $this->expectException(InvalidArgumentException::class);
        // filter_var may reject before length check — just assert it throws
        Email::fromString($local . '@example.com');
    }

    // ── Nullable ──

    #[Test]
    public function from_nullable_returns_null_for_null(): void
    {
        $this->assertNull(Email::fromNullableString(null));
    }

    #[Test]
    public function from_nullable_returns_email_for_valid_string(): void
    {
        $email = Email::fromNullableString('user@example.com');
        $this->assertNotNull($email);
        $this->assertEquals('user@example.com', $email->toString());
    }

    // ── Domain / Local Part ──

    #[Test]
    public function get_domain_returns_domain_part(): void
    {
        $email = Email::fromString('user@example.com');
        $this->assertEquals('example.com', $email->getDomain());
    }

    #[Test]
    public function get_local_part_returns_local(): void
    {
        $email = Email::fromString('user@example.com');
        $this->assertEquals('user', $email->getLocalPart());
    }

    #[Test]
    public function is_domain_matches_case_insensitive(): void
    {
        $email = Email::fromString('user@Gmail.COM');
        $this->assertTrue($email->isDomain('gmail.com'));
        $this->assertTrue($email->isDomain('GMAIL.COM'));
    }

    #[Test]
    public function is_gmail_detects_gmail_addresses(): void
    {
        $gmail = Email::fromString('user@gmail.com');
        $this->assertTrue($gmail->isGmail());

        $other = Email::fromString('user@yahoo.com');
        $this->assertFalse($other->isGmail());
    }

    // ── Equality ──

    #[Test]
    public function equal_emails_match(): void
    {
        $a = Email::fromString('user@example.com');
        $b = Email::fromString('USER@Example.COM');
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function different_emails_do_not_match(): void
    {
        $a = Email::fromString('user1@example.com');
        $b = Email::fromString('user2@example.com');
        $this->assertFalse($a->equals($b));
    }

    // ── __toString ──

    #[Test]
    public function to_string_magic_works(): void
    {
        $email = Email::fromString('user@example.com');
        $this->assertEquals('user@example.com', (string) $email);
    }
}
