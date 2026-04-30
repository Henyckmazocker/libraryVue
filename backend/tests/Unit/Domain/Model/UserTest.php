<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private function makeUser(array $overrides = []): User
    {
        $defaults = [
            'id' => 1,
            'googleId' => GoogleId::fromString('123456789012345678901'),
            'email' => Email::fromString('test@gmail.com'),
            'name' => 'Test User',
            'picture' => 'https://img.test/pic.jpg',
        ];
        $d = array_merge($defaults, $overrides);
        return new User(
            $d['id'], $d['googleId'], $d['email'], $d['name'], $d['picture']
        );
    }

    // ── Constructor ──

    #[Test]
    public function creates_user_with_all_fields(): void
    {
        $user = $this->makeUser();
        $this->assertSame(1, $user->getId());
        $this->assertEquals('test@gmail.com', $user->getEmail()->toString());
        $this->assertEquals('Test User', $user->getName());
        $this->assertNotNull($user->getPicture());
        $this->assertTrue($user->isActive());
    }

    #[Test]
    public function creates_user_with_null_id(): void
    {
        $user = $this->makeUser(['id' => null]);
        $this->assertNull($user->getId());
    }

    #[Test]
    public function constructor_sets_timestamps_automatically(): void
    {
        $user = $this->makeUser();
        $this->assertTrue($user->getCreatedAt()->isToday());
        $this->assertTrue($user->getUpdatedAt()->isToday());
        $this->assertNull($user->getLastLogin());
    }

    #[Test]
    public function throws_on_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot be empty');
        $this->makeUser(['name' => '']);
    }

    #[Test]
    public function throws_on_whitespace_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeUser(['name' => '   ']);
    }

    // ── Setters ──

    #[Test]
    public function set_name_trims(): void
    {
        $user = $this->makeUser();
        $user->setName('  New Name  ');
        $this->assertEquals('New Name', $user->getName());
    }

    #[Test]
    public function set_name_throws_on_empty(): void
    {
        $user = $this->makeUser();
        $this->expectException(InvalidArgumentException::class);
        $user->setName('');
    }

    #[Test]
    public function update_last_login_sets_timestamps(): void
    {
        $user = $this->makeUser();
        $this->assertNull($user->getLastLogin());

        $user->updateLastLogin();

        $this->assertNotNull($user->getLastLogin());
        $this->assertTrue($user->getLastLogin()->isToday());
    }

    #[Test]
    public function set_active_updates_updated_at(): void
    {
        $user = $this->makeUser();
        $user->setActive(false);
        $this->assertFalse($user->isActive());
    }

    #[Test]
    public function set_preferences(): void
    {
        $user = $this->makeUser();
        $this->assertNull($user->getPreferences());

        $user->setPreferences(['theme' => 'dark', 'language' => 'es']);
        $this->assertEquals(['theme' => 'dark', 'language' => 'es'], $user->getPreferences());
    }

    // ── Factory methods ──

    #[Test]
    public function create_from_data_array(): void
    {
        $user = User::create([
            'google_id' => '123456789012345678901',
            'email' => 'new@example.com',
            'name' => 'New User',
            'picture' => 'https://pic.jpg',
        ]);

        $this->assertNull($user->getId());
        $this->assertEquals('new@example.com', $user->getEmail()->toString());
        $this->assertEquals('New User', $user->getName());
        $this->assertTrue($user->isActive());
    }

    #[Test]
    public function register_with_google(): void
    {
        $user = User::registerWithGoogle(
            GoogleId::fromString('123456789012345678901'),
            Email::fromString('google@gmail.com'),
            'Google User',
            'https://avatar.jpg'
        );

        $this->assertNull($user->getId());
        $this->assertEquals('google@gmail.com', $user->getEmail()->toString());
        $this->assertTrue($user->isActive());
    }

    // ── toArray ──

    #[Test]
    public function to_array_contains_all_fields(): void
    {
        $user = $this->makeUser();
        $arr = $user->toArray();

        $this->assertSame(1, $arr['id']);
        $this->assertEquals('123456789012345678901', $arr['google_id']);
        $this->assertEquals('test@gmail.com', $arr['email']);
        $this->assertEquals('Test User', $arr['name']);
        $this->assertEquals('https://img.test/pic.jpg', $arr['picture']);
        $this->assertIsInt($arr['created_at']);
        $this->assertIsInt($arr['updated_at']);
        $this->assertNull($arr['last_login']);
        $this->assertNull($arr['preferences']);
        $this->assertTrue($arr['is_active']);
    }

    // ── fromArray ──

    #[Test]
    public function from_array_creates_user(): void
    {
        $now = time();
        $data = [
            'id' => 5,
            'google_id' => '123456789012345678901',
            'email' => 'from@example.com',
            'name' => 'From Array',
            'picture' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'is_active' => false,
        ];

        $user = User::fromArray($data);
        $this->assertSame(5, $user->getId());
        $this->assertEquals('from@example.com', $user->getEmail()->toString());
        $this->assertEquals('From Array', $user->getName());
        $this->assertFalse($user->isActive());
    }

    // ── Round-trip ──

    #[Test]
    public function to_array_from_array_round_trip(): void
    {
        $original = $this->makeUser();
        $arr = $original->toArray();
        $restored = User::fromArray($arr);

        $this->assertSame($original->getId(), $restored->getId());
        $this->assertEquals($original->getEmail()->toString(), $restored->getEmail()->toString());
        $this->assertEquals($original->getName(), $restored->getName());
        $this->assertEquals($original->getGoogleId()->toString(), $restored->getGoogleId()->toString());
        $this->assertTrue($restored->isActive());
    }
}
