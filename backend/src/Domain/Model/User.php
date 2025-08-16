<?php
declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

class User
{
    private ?int $id;
    private string $googleId;
    private string $email;
    private string $name;
    private ?string $picture;
    private ?int $createdAt;
    private ?int $updatedAt;
    private ?int $lastLogin;
    private ?array $preferences;
    private bool $isActive;

    public function __construct(
        ?int $id,
        string $googleId,
        string $email,
        string $name,
        ?string $picture = null,
        ?int $createdAt = null,
        ?int $updatedAt = null,
        ?int $lastLogin = null,
        ?array $preferences = null,
        bool $isActive = true
    ) {
        $this->id = $id;
        $this->setGoogleId($googleId);
        $this->setEmail($email);
        $this->setName($name);
        $this->picture = $picture;
        $this->createdAt = $createdAt ?? time();
        $this->updatedAt = $updatedAt ?? time();
        $this->lastLogin = $lastLogin;
        $this->preferences = $preferences;
        $this->isActive = $isActive;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getGoogleId(): string { return $this->googleId; }
    public function getEmail(): string { return $this->email; }
    public function getName(): string { return $this->name; }
    public function getPicture(): ?string { return $this->picture; }
    public function getCreatedAt(): ?int { return $this->createdAt; }
    public function getUpdatedAt(): ?int { return $this->updatedAt; }
    public function getLastLogin(): ?int { return $this->lastLogin; }
    public function getPreferences(): ?array { return $this->preferences; }
    public function isActive(): bool { return $this->isActive; }

    // Setters with validation
    public function setGoogleId(string $googleId): void
    {
        if (empty(trim($googleId))) {
            throw new InvalidArgumentException('Google ID cannot be empty');
        }
        $this->googleId = $googleId;
    }

    public function setEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        $this->email = $email;
    }

    public function setName(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Name cannot be empty');
        }
        $this->name = trim($name);
    }

    public function setPicture(?string $picture): void
    {
        $this->picture = $picture;
    }

    public function setPreferences(?array $preferences): void
    {
        $this->preferences = $preferences;
    }

    public function updateLastLogin(): void
    {
        $this->lastLogin = time();
        $this->updatedAt = time();
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
        $this->updatedAt = time();
    }

    // Factory method
    public static function create(array $data): self
    {
        return new self(
            null, // ID will be set by repository
            $data['google_id'],
            $data['email'],
            $data['name'],
            $data['picture'] ?? null,
            null, // createdAt will be set by constructor
            null, // updatedAt will be set by constructor
            null, // lastLogin
            $data['preferences'] ?? null,
            $data['is_active'] ?? true
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'google_id' => $this->googleId,
            'email' => $this->email,
            'name' => $this->name,
            'picture' => $this->picture,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'last_login' => $this->lastLogin,
            'preferences' => $this->preferences,
            'is_active' => $this->isActive
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['google_id'],
            $data['email'],
            $data['name'],
            $data['picture'] ?? null,
            $data['created_at'] ?? null,
            $data['updated_at'] ?? null,
            $data['last_login'] ?? null,
            $data['preferences'] ?? null,
            $data['is_active'] ?? true
        );
    }
}
