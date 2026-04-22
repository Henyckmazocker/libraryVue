<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: SpotifyId
 *
 * Represents a unique Spotify identifier (base62 string, ~22 characters).
 * - Type: string (base62 alphanumeric)
 * - Validation: only [a-zA-Z0-9], length between 15 and 25 characters
 * - Immutable
 * - Specific to Album / Spotify API
 */
final class SpotifyId
{
    private string $value;

    private const PATTERN = '/^[a-zA-Z0-9]{15,25}$/';

    private function __construct(string $id)
    {
        $this->validate($id);
        $this->value = $id;
    }

    /**
     * Create a SpotifyId from a string
     *
     * @throws InvalidArgumentException if the ID format is invalid
     */
    public static function fromString(string $id): self
    {
        return new self($id);
    }

    /**
     * Create a SpotifyId from a nullable string
     *
     * @return self|null null if the value is null
     */
    public static function fromNullableString(?string $id): ?self
    {
        return $id !== null ? new self($id) : null;
    }

    /**
     * Return the string value
     */
    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Compare equality with another SpotifyId
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function validate(string $id): void
    {
        if (empty($id)) {
            throw new InvalidArgumentException('Spotify ID cannot be empty.');
        }

        if (!preg_match(self::PATTERN, $id)) {
            throw new InvalidArgumentException(
                "Invalid Spotify ID format: \"{$id}\". Must be 15–25 base62 characters [a-zA-Z0-9]."
            );
        }
    }
}
