<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\User\Mappers;

use App\Infrastructure\Persistence\User\Mappers\UserDataMapper;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\Timestamp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserDataMapperTest extends TestCase
{
    private UserDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new UserDataMapper();
    }

    private function fullDbRow(): array
    {
        return [
            'id' => 1,
            'google_id' => '1234567890',
            'email' => 'test@example.com',
            'name' => 'John Doe',
            'picture' => 'https://example.com/photo.jpg',
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-06-15 14:30:00',
            'last_login' => '2024-06-15 14:30:00',
            'preferences' => json_encode(['theme' => 'dark', 'lang' => 'es']),
            'is_active' => 1,
            'is_admin' => 0,
        ];
    }

    // ── toDomain ──

    #[Test]
    public function to_domain_maps_all_fields(): void
    {
        $user = $this->mapper->toDomain($this->fullDbRow());

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(1, $user->getId());
        $this->assertEquals('1234567890', $user->getGoogleId()->toString());
        $this->assertEquals('test@example.com', $user->getEmail()->toString());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('https://example.com/photo.jpg', $user->getPicture());
        $this->assertNotNull($user->getCreatedAt());
        $this->assertNotNull($user->getUpdatedAt());
        $this->assertNotNull($user->getLastLogin());
        $this->assertIsArray($user->getPreferences());
        $this->assertEquals('dark', $user->getPreferences()['theme']);
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isAdmin());
    }

    #[Test]
    public function to_domain_creates_value_objects(): void
    {
        $user = $this->mapper->toDomain($this->fullDbRow());

        $this->assertInstanceOf(GoogleId::class, $user->getGoogleId());
        $this->assertInstanceOf(Email::class, $user->getEmail());
        $this->assertInstanceOf(Timestamp::class, $user->getCreatedAt());
        $this->assertInstanceOf(Timestamp::class, $user->getUpdatedAt());
        $this->assertInstanceOf(Timestamp::class, $user->getLastLogin());
    }

    #[Test]
    public function to_domain_null_optional_fields(): void
    {
        $row = [
            'google_id' => '9999999999',
            'email' => 'user@test.com',
            'name' => 'Test User',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        $user = $this->mapper->toDomain($row);

        $this->assertNull($user->getId());
        $this->assertNull($user->getPicture());
        $this->assertNull($user->getLastLogin());
        $this->assertEmpty($user->getPreferences());
        $this->assertFalse($user->isActive());
    }

    #[Test]
    public function to_domain_is_active_false(): void
    {
        $row = $this->fullDbRow();
        $row['is_active'] = 0;

        $user = $this->mapper->toDomain($row);
        $this->assertFalse($user->isActive());
    }

    #[Test]
    public function to_domain_preferences_already_array(): void
    {
        $row = $this->fullDbRow();
        $row['preferences'] = ['theme' => 'light'];

        $user = $this->mapper->toDomain($row);
        $this->assertEquals(['theme' => 'light'], $user->getPreferences());
    }

    #[Test]
    public function to_domain_null_preferences(): void
    {
        $row = $this->fullDbRow();
        $row['preferences'] = null;

        $user = $this->mapper->toDomain($row);
        $this->assertEmpty($user->getPreferences());
    }

    #[Test]
    public function to_domain_without_last_login(): void
    {
        $row = $this->fullDbRow();
        unset($row['last_login']);

        $user = $this->mapper->toDomain($row);
        $this->assertNull($user->getLastLogin());
    }

    #[Test]
    public function to_domain_is_admin_true(): void
    {
        $row = $this->fullDbRow();
        $row['is_admin'] = 1;

        $this->assertTrue($this->mapper->toDomain($row)->isAdmin());
    }

    #[Test]
    public function to_domain_without_is_admin_defaults_to_false(): void
    {
        $row = $this->fullDbRow();
        unset($row['is_admin']);

        $this->assertFalse($this->mapper->toDomain($row)->isAdmin());
    }

    // ── toPersistence ──

    #[Test]
    public function to_persistence_maps_all_fields_without_id(): void
    {
        $user = $this->mapper->toDomain($this->fullDbRow());
        $data = $this->mapper->toPersistence($user, false);

        $this->assertArrayNotHasKey('id', $data);
        $this->assertEquals('1234567890', $data['google_id']);
        $this->assertEquals('test@example.com', $data['email']);
        $this->assertEquals('John Doe', $data['name']);
        $this->assertEquals('https://example.com/photo.jpg', $data['picture']);
        $this->assertIsString($data['created_at']);
        $this->assertIsString($data['updated_at']);
        $this->assertIsString($data['last_login']);
        $this->assertSame(1, $data['is_active']);
        $this->assertSame(0, $data['is_admin']);
    }

    #[Test]
    public function to_persistence_includes_id_when_requested(): void
    {
        $user = $this->mapper->toDomain($this->fullDbRow());
        $data = $this->mapper->toPersistence($user, true);

        $this->assertArrayHasKey('id', $data);
        $this->assertSame(1, $data['id']);
    }

    #[Test]
    public function to_persistence_no_id_when_user_has_no_id(): void
    {
        $row = $this->fullDbRow();
        unset($row['id']);

        $user = $this->mapper->toDomain($row);
        $data = $this->mapper->toPersistence($user, true);

        // includeId is true but user has no ID → key not included
        $this->assertArrayNotHasKey('id', $data);
    }

    #[Test]
    public function to_persistence_is_active_converts_to_int(): void
    {
        $row = $this->fullDbRow();
        $row['is_active'] = 1;

        $user = $this->mapper->toDomain($row);
        $data = $this->mapper->toPersistence($user);

        $this->assertSame(1, $data['is_active']);
    }

    #[Test]
    public function to_persistence_inactive_converts_to_zero(): void
    {
        $row = $this->fullDbRow();
        $row['is_active'] = 0;

        $user = $this->mapper->toDomain($row);
        $data = $this->mapper->toPersistence($user);

        $this->assertSame(0, $data['is_active']);
    }

    #[Test]
    public function to_persistence_null_last_login(): void
    {
        $row = $this->fullDbRow();
        unset($row['last_login']);

        $user = $this->mapper->toDomain($row);
        $data = $this->mapper->toPersistence($user);

        $this->assertNull($data['last_login']);
    }

    // ── toDomainCollection ──

    #[Test]
    public function to_domain_collection_maps_multiple_rows(): void
    {
        $rows = [
            array_merge($this->fullDbRow(), ['id' => 1, 'email' => 'a@test.com', 'google_id' => '1111111111']),
            array_merge($this->fullDbRow(), ['id' => 2, 'email' => 'b@test.com', 'google_id' => '2222222222']),
        ];

        $users = $this->mapper->toDomainCollection($rows);

        $this->assertCount(2, $users);
        $this->assertInstanceOf(User::class, $users[0]);
        $this->assertInstanceOf(User::class, $users[1]);
        $this->assertSame(1, $users[0]->getId());
        $this->assertSame(2, $users[1]->getId());
    }

    #[Test]
    public function to_domain_collection_empty(): void
    {
        $users = $this->mapper->toDomainCollection([]);
        $this->assertEmpty($users);
    }

    #[Test]
    public function to_persistence_is_admin_converts_to_int(): void
    {
        $row = $this->fullDbRow();
        $row['is_admin'] = 1;

        $data = $this->mapper->toPersistence($this->mapper->toDomain($row));

        $this->assertSame(1, $data['is_admin']);
    }

    // ── Round-trip ──

    #[Test]
    public function round_trip_preserves_core_data(): void
    {
        $original = $this->fullDbRow();
        $user = $this->mapper->toDomain($original);
        $data = $this->mapper->toPersistence($user, true);

        $this->assertSame((int) $original['id'], $data['id']);
        $this->assertEquals($original['google_id'], $data['google_id']);
        $this->assertEquals($original['email'], $data['email']);
        $this->assertEquals($original['name'], $data['name']);
        $this->assertEquals($original['picture'], $data['picture']);
    }

    #[Test]
    public function round_trip_preserves_is_admin(): void
    {
        $original = $this->fullDbRow();
        $original['is_admin'] = 1;

        $user = $this->mapper->toDomain($original);
        $data = $this->mapper->toPersistence($user, true);

        // MySqlUserRepository::update() reescribe todas las columnas de toPersistence(), asi que
        // perder el flag aqui degradaria al administrador en el siguiente login.
        $this->assertSame(1, $data['is_admin']);
    }
}
