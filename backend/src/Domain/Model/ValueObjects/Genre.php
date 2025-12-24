<?php
declare(strict_types=1);

namespace App\Domain\Model\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: Genre
 * 
 * Representa un género literario o cinematográfico.
 * - Inmutable
 * - Sin validación estricta (las APIs externas pueden devolver cualquier género)
 * - Normalización de formato
 * - Compartido entre Book y Movie
 */
final class Genre
{
    private string $name;

    private function __construct(string $name)
    {
        $this->validate($name);
        $this->name = $name;
    }

    /**
     * Crea un Genre desde un string
     * 
     * @throws InvalidArgumentException si el género no es válido
     */
    public static function fromString(string $name): self
    {
        return new self($name);
    }

    /**
     * Crea un Genre desde un string nullable
     * 
     * @return self|null null si el valor es null
     */
    public static function fromNullableString(?string $name): ?self
    {
        return $name !== null ? new self($name) : null;
    }

    /**
     * Convierte a string
     */
    public function toString(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Compara dos géneros (case-insensitive)
     */
    public function equals(Genre $other): bool
    {
        return strcasecmp($this->name, $other->name) === 0;
    }

    /**
     * Obtiene el género normalizado (Title Case)
     */
    public function toNormalized(): string
    {
        return ucwords(strtolower($this->name));
    }

    /**
     * Valida el género
     * 
     * @throws InvalidArgumentException
     */
    private function validate(string $name): void
    {
        $trimmed = trim($name);
        
        if (empty($trimmed)) {
            throw new InvalidArgumentException('Genre name cannot be empty');
        }

        // Validar longitud máxima razonable
        if (strlen($trimmed) > 100) {
            throw new InvalidArgumentException(
                'Genre name is too long (max 100 characters)'
            );
        }

        // Validación flexible: permitir cualquier género por ahora
        // En producción, se puede activar validación estricta
        // if (!in_array($name, self::ALLOWED_GENRES, true)) {
        //     throw new InvalidArgumentException(
        //         "Invalid genre: {$name}. Allowed genres: " . 
        //         implode(', ', self::ALLOWED_GENRES)
        //     );
        // }
    }
}