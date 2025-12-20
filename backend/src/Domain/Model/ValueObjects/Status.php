<?php
declare(strict_types=1);

namespace App\Domain\Model\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: Status
 * 
 * Representa un estado de libro o película.
 * - Inmutable
 * - Validación de formato (sin validar contra lista, eso lo hace la BD)
 * - Normalización de nombre
 * - Compartido entre Book y Movie
 * 
 * Los estados permitidos se gestionan en las tablas:
 * - book_statuses (en BD)
 * - movie_statuses (en BD)
 * 
 * Este VO solo valida el formato, no el contenido.
 */
final class Status
{
    private string $name;

    private function __construct(string $name)
    {
        $normalized = $this->normalize($name);
        $this->validate($normalized);
        $this->name = $normalized;
    }

    /**
     * Crea un Status desde un string
     * 
     * @throws InvalidArgumentException si el estado no es válido
     */
    public static function fromString(string $name): self
    {
        return new self($name);
    }

    /**
     * Crea desde nullable
     */
    public static function fromNullableString(?string $name): ?self
    {
        return $name !== null ? new self($name) : null;
    }

    /**
     * Crea múltiples Status desde un array de strings
     * 
     * @param array<int, string> $names Array de nombres de estados
     * @return array<int, self> Array de Status VOs
     */
    public static function fromArray(array $names): array
    {
        return array_map(fn($name) => new self($name), $names);
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
     * Compara dos estados
     */
    public function equals(Status $other): bool
    {
        return $this->name === $other->name;
    }

    /**
     * Convierte a formato legible para humanos
     * Capitaliza y reemplaza guiones por espacios
     */
    public function toHumanReadable(): string
    {
        // Capitalizar cada palabra y reemplazar guiones
        return ucwords(str_replace('-', ' ', $this->name));
    }

    /**
     * Normaliza el nombre del estado
     * Convierte a lowercase y reemplaza espacios por guiones
     */
    private function normalize(string $name): string
    {
        $trimmed = trim($name);
        $lowercase = strtolower($trimmed);
        $normalized = str_replace(' ', '-', $lowercase);
        
        return $normalized;
    }

    /**
     * Valida el estado
     * 
     * @throws InvalidArgumentException
     */
    private function validate(string $name): void
    {
        $trimmed = trim($name);
        
        if (empty($trimmed)) {
            throw new InvalidArgumentException('Status name cannot be empty');
        }

        // Validar formato: lowercase con guiones, sin espacios
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $trimmed)) {
            throw new InvalidArgumentException(
                "Invalid status format: '{$name}'. Must be lowercase with hyphens (e.g., 'to-read', 'watching')"
            );
        }

        // Validar longitud máxima
        if (strlen($trimmed) > 50) {
            throw new InvalidArgumentException(
                'Status name is too long (max 50 characters)'
            );
        }
    }
}