<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Commands;

use App\Domain\DTO\Commands\LoginUserCommand;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\GoogleId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LoginUserCommandTest extends TestCase
{
    #[Test]
    public function constructor_sets_all_properties(): void
    {
        $googleId = GoogleId::fromString('1234567890abcdef');
        $email = Email::fromString('user@gmail.com');

        $cmd = new LoginUserCommand(
            googleId: $googleId,
            email: $email,
            name: 'John Doe',
            picture: 'https://photo.jpg'
        );

        $this->assertSame($googleId, $cmd->googleId);
        $this->assertSame($email, $cmd->email);
        $this->assertEquals('John Doe', $cmd->name);
        $this->assertEquals('https://photo.jpg', $cmd->picture);
    }

    #[Test]
    public function constructor_picture_defaults_to_null(): void
    {
        $cmd = new LoginUserCommand(
            googleId: GoogleId::fromString('1234567890abcdef'),
            email: Email::fromString('user@gmail.com'),
            name: 'John'
        );

        $this->assertNull($cmd->picture);
    }

    #[Test]
    public function from_google_token_creates_command(): void
    {
        $cmd = LoginUserCommand::fromGoogleToken([
            'sub' => '1234567890abcdef',
            'email' => 'user@gmail.com',
            'name' => 'John Doe',
            'picture' => 'https://photo.jpg',
        ]);

        $this->assertEquals('1234567890abcdef', $cmd->googleId->toString());
        $this->assertEquals('user@gmail.com', $cmd->email->toString());
        $this->assertEquals('John Doe', $cmd->name);
        $this->assertEquals('https://photo.jpg', $cmd->picture);
    }

    #[Test]
    public function from_google_token_without_picture(): void
    {
        $cmd = LoginUserCommand::fromGoogleToken([
            'sub' => '1234567890abcdef',
            'email' => 'user@gmail.com',
            'name' => 'John',
        ]);

        $this->assertNull($cmd->picture);
    }

    #[Test]
    public function from_google_token_throws_on_missing_sub(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields');

        LoginUserCommand::fromGoogleToken([
            'email' => 'user@gmail.com',
            'name' => 'John',
        ]);
    }

    #[Test]
    public function from_google_token_throws_on_missing_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields');

        LoginUserCommand::fromGoogleToken([
            'sub' => '1234567890abcdef',
            'name' => 'John',
        ]);
    }

    #[Test]
    public function from_google_token_throws_on_missing_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields');

        LoginUserCommand::fromGoogleToken([
            'sub' => '1234567890abcdef',
            'email' => 'user@gmail.com',
        ]);
    }

    #[Test]
    public function to_array_contains_all_fields(): void
    {
        $cmd = LoginUserCommand::fromGoogleToken([
            'sub' => '1234567890abcdef',
            'email' => 'user@gmail.com',
            'name' => 'John Doe',
            'picture' => 'https://photo.jpg',
        ]);

        $arr = $cmd->toArray();

        $this->assertEquals('1234567890abcdef', $arr['google_id']);
        $this->assertEquals('user@gmail.com', $arr['email']);
        $this->assertEquals('John Doe', $arr['name']);
        $this->assertEquals('https://photo.jpg', $arr['picture']);
    }

    #[Test]
    public function to_array_null_picture(): void
    {
        $cmd = LoginUserCommand::fromGoogleToken([
            'sub' => '1234567890abcdef',
            'email' => 'user@gmail.com',
            'name' => 'John',
        ]);

        $arr = $cmd->toArray();
        $this->assertNull($arr['picture']);
    }
}
