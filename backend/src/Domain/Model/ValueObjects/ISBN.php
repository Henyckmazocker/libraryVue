<?php
declare(strict_types=1);

namespace App\Domain\Model\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: ISBN
 * 
 * Representa un ISBN (International Standard Book Number).
 * - Soporta ISBN-10 e ISBN-13
 * - Validación con checksum
 * - Inmutable
 * - Específico para Book
 */
final class ISBN
{
    private string $value;

    private function __construct(string $isbn)
    {
        $cleaned = $this->clean($isbn);
        $this->validate($cleaned);
        $this->value = $cleaned;
    }

    /**
     * Crea un ISBN desde un string
     * 
     * @throws InvalidArgumentException si el ISBN no es válido
     */
    public static function fromString(string $isbn): self
    {
        return new self($isbn);
    }

    /**
     * Crea un ISBN desde un string nullable
     */
    public static function fromNullableString(?string $isbn): ?self
    {
        return $isbn !== null ? new self($isbn) : null;
    }

    /**
     * Convierte a string
     */
    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Compara dos ISBNs
     */
    public function equals(ISBN $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si es ISBN-10
     */
    public function isISBN10(): bool
    {
        return strlen($this->value) === 10;
    }

    /**
     * Verifica si es ISBN-13
     */
    public function isISBN13(): bool
    {
        return strlen($this->value) === 13;
    }

    /**
     * Formatea el ISBN con guiones
     */
    public function toFormatted(): string
    {
        if ($this->isISBN10()) {
            // Formato: X-XXX-XXXXX-X
            return substr($this->value, 0, 1) . '-' .
                   substr($this->value, 1, 3) . '-' .
                   substr($this->value, 4, 5) . '-' .
                   substr($this->value, 9, 1);
        }
        
        if ($this->isISBN13()) {
            // Formato: XXX-X-XXX-XXXXX-X
            return substr($this->value, 0, 3) . '-' .
                   substr($this->value, 3, 1) . '-' .
                   substr($this->value, 4, 3) . '-' .
                   substr($this->value, 7, 5) . '-' .
                   substr($this->value, 12, 1);
        }
        
        return $this->value;
    }

    /**
     * Limpia el ISBN (elimina guiones, espacios)
     */
    private function clean(string $isbn): string
    {
        return preg_replace('/[^0-9Xx]/', '', $isbn);
    }

    /**
     * Valida el ISBN
     * 
     * @throws InvalidArgumentException
     */
    private function validate(string $isbn): void
    {
        if (empty($isbn)) {
            throw new InvalidArgumentException('ISBN cannot be empty');
        }

        $length = strlen($isbn);
        
        if ($length === 10) {
            $this->validateISBN10($isbn);
        } elseif ($length === 13) {
            $this->validateISBN13($isbn);
        } else {
            throw new InvalidArgumentException(
                "ISBN must be 10 or 13 characters long, got {$length}: {$isbn}"
            );
        }
    }

    /**
     * Valida ISBN-10 con checksum
     */
    private function validateISBN10(string $isbn): void
    {
        $sum = 0;
        
        for ($i = 0; $i < 10; $i++) {
            $digit = $isbn[$i];
            
            // El último dígito puede ser 'X' (representa 10)
            if ($i === 9 && strtoupper($digit) === 'X') {
                $digit = 10;
            } elseif (!ctype_digit($digit)) {
                throw new InvalidArgumentException(
                    "Invalid ISBN-10: contains non-digit character '{$digit}'"
                );
            } else {
                $digit = (int)$digit;
            }
            
            $sum += $digit * (10 - $i);
        }
        
        if ($sum % 11 !== 0) {
            throw new InvalidArgumentException(
                "Invalid ISBN-10 checksum: {$isbn}"
            );
        }
    }

    /**
     * Valida ISBN-13 con checksum
     */
    private function validateISBN13(string $isbn): void
    {
        if (!ctype_digit($isbn)) {
            throw new InvalidArgumentException(
                "Invalid ISBN-13: must contain only digits"
            );
        }
        
        $sum = 0;
        
        for ($i = 0; $i < 13; $i++) {
            $digit = (int)$isbn[$i];
            $multiplier = ($i % 2 === 0) ? 1 : 3;
            $sum += $digit * $multiplier;
        }
        
        if ($sum % 10 !== 0) {
            throw new InvalidArgumentException(
                "Invalid ISBN-13 checksum: {$isbn}"
            );
        }
    }
}
