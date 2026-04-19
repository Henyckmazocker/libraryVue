<?php
declare(strict_types=1);

namespace App\Domain\Model\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: GameIdentifier
 * 
 * Representa un identificador único de videojuego de RAWG API.
 * - Tipo: INT UNSIGNED (entero positivo)
 * - Validación: debe ser mayor a 0
 * - Inmutable
 * - Específico para Game
 */
final class GameIdentifier
{
    private int $value;

    private function __construct(int $id)
    {
        $this->validate($id);
        $this->value = $id;
    }

    /**
     * Crea un GameIdentifier desde un entero
     * 
     * @throws InvalidArgumentException si el ID no es válido
     */
    public static function fromInt(int $id): self
    {
        return new self($id);
    }

    /**
     * Crea un GameIdentifier desde un string (convierte a int)
     * 
     * @throws InvalidArgumentException si el ID no es numérico o no es válido
     */
    public static function fromString(string $id): self
    {
        if (!is_numeric($id)) {
            throw new InvalidArgumentException("Game ID must be numeric. Given: {$id}");
        }
        
        $intId = (int) $id;
        return new self($intId);
    }

    /**
     * Crea desde un entero nullable
     * 
     * @return self|null null si el valor es null
     */
    public static function fromNullableInt(?int $id): ?self
    {
        return $id !== null ? new self($id) : null;
    }

    /**
     * Crea desde un string nullable
     * 
     * @return self|null null si el valor es null
     */
    public static function fromNullableString(?string $id): ?self
    {
        return $id !== null ? self::fromString($id) : null;
    }

    /**
     * Convierte a entero
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * Convierte a string
     */
    public function toString(): string
    {
        return (string) $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Compara dos GameIdentifiers
     */
    public function equals(GameIdentifier $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Valida el ID del juego
     * 
     * @throws InvalidArgumentException si el ID no es válido
     */
    private function validate(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                "Game ID must be a positive integer greater than 0. Given: {$id}"
            );
        }
    }

    /**
     * Verifica si el ID es válido sin lanzar excepción
     */
    public static function isValid(int $id): bool
    {
        return $id > 0;
    }

    /**
     * Verifica si un string representa un ID válido
     */
    public static function isValidString(string $id): bool
    {
        if (!is_numeric($id)) {
            return false;
        }
        
        $intId = (int) $id;
        return self::isValid($intId);
    }

    /**
     * Obtiene el valor como entero (alias de toInt)
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * Serialización para JSON
     */
    public function jsonSerialize(): int
    {
        return $this->value;
    }
}
