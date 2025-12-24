<?php
declare(strict_types=1);

namespace App\Domain\Model\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: Rating
 * 
 * Representa una calificación con validación robusta.
 * - Rango: 0.5 a 5.0
 * - Incrementos: múltiplos de 0.5
 * - Inmutable
 * - Compartido entre Book, Movie y User
 */
final class Rating
{
    private float $value;

    private function __construct(float $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    /**
     * Crea un Rating desde un valor float
     * 
     * @throws InvalidArgumentException si el valor no es válido
     */
    public static function fromFloat(float $value): self
    {
        return new self($value);
    }

    /**
     * Crea un Rating desde un valor float nullable
     * 
     * @return self|null null si el valor es null
     * @throws InvalidArgumentException si el valor no es válido
     */
    public static function fromNullableFloat(?float $value): ?self
    {
        return $value !== null ? new self($value) : null;
    }

    /**
     * Convierte el Rating a float
     */
    public function toFloat(): float
    {
        return $this->value;
    }

    /**
     * Compara dos Ratings
     */
    public function equals(Rating $other): bool
    {
        return abs($this->value - $other->value) < 0.01;
    }

    /**
     * Convierte a string para representación
     */
    public function toString(): string
    {
        return number_format($this->value, 1);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Verifica si el rating es alto (>= 4.0)
     */
    public function isHigh(): bool
    {
        return $this->value >= 4.0;
    }

    /**
     * Verifica si el rating es bajo (< 3.0)
     */
    public function isLow(): bool
    {
        return $this->value < 3.0;
    }

    /**
     * Valida el valor del rating
     * 
     * @throws InvalidArgumentException
     */
    private function validate(float $value): void
    {
        if ($value < 0.5 || $value > 5.0) {
            throw new InvalidArgumentException(
                "Rating must be between 0.5 and 5.0, got: {$value}"
            );
        }

        // Verificar que sea múltiplo de 0.5
        $remainder = fmod($value * 2, 1);
        if (abs($remainder) > 0.01) {
            throw new InvalidArgumentException(
                "Rating must be a multiple of 0.5, got: {$value}"
            );
        }
    }
}
