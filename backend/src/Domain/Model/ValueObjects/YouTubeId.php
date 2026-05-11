<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: YouTubeId
 *
 * Represents a unique YouTube video identifier (11-character string).
 * - Type: string (alphanumeric + hyphen + underscore)
 * - Validation: exactly 11 characters matching [a-zA-Z0-9_-]
 * - Immutable
 * - Specific to Video / YouTube Data API v3
 */
final class YouTubeId
{
    private string $value;

    private const PATTERN = '/^[a-zA-Z0-9_-]{11}$/';

    private function __construct(string $id)
    {
        $this->validate($id);
        $this->value = $id;
    }

    /**
     * Create a YouTubeId from a string.
     *
     * @throws InvalidArgumentException if the ID format is invalid
     */
    public static function fromString(string $id): self
    {
        return new self(trim($id));
    }

    /**
     * Create a YouTubeId from a nullable string.
     *
     * @return self|null null if the value is null or empty
     */
    public static function fromNullableString(?string $id): ?self
    {
        return ($id !== null && $id !== '') ? new self(trim($id)) : null;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function validate(string $id): void
    {
        if (!preg_match(self::PATTERN, $id)) {
            throw new InvalidArgumentException(
                "Invalid YouTube video ID: '$id'. Must be exactly 11 characters matching [a-zA-Z0-9_-]."
            );
        }
    }
}
