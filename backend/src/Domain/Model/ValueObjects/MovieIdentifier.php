<?php
declare(strict_types=1);

namespace App\Domain\Model\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: MovieIdentifier
 * 
 * Representa un identificador único de película.
 * - Soporta múltiples formatos: IMDB ID, TMDB ID, ISBN (para películas en formato físico)
 * - Validación según tipo
 * - Inmutable
 * - Específico para Movie
 */
final class MovieIdentifier
{
    private string $value;
    private string $type; // 'imdb', 'tmdb', 'isbn', 'custom'

    private function __construct(string $id, string $type = 'custom')
    {
        $this->validate($id, $type);
        $this->value = $id;
        $this->type = $type;
    }

    /**
     * Crea un MovieIdentifier desde un string (detecta tipo automáticamente)
     */
    public static function fromString(string $id): self
    {
        $type = self::detectType($id);
        return new self($id, $type);
    }

    /**
     * Crea un MovieIdentifier desde IMDB ID
     */
    public static function fromImdb(string $imdbId): self
    {
        return new self($imdbId, 'imdb');
    }

    /**
     * Crea un MovieIdentifier desde TMDB ID
     */
    public static function fromTmdb(string $tmdbId): self
    {
        return new self($tmdbId, 'tmdb');
    }

    /**
     * Crea un MovieIdentifier desde ISBN (películas en formato físico)
     */
    public static function fromIsbn(string $isbn): self
    {
        return new self($isbn, 'isbn');
    }

    /**
     * Crea un MovieIdentifier custom (ID propio de la aplicación)
     */
    public static function fromCustom(string $id): self
    {
        return new self($id, 'custom');
    }

    /**
     * Crea desde nullable
     */
    public static function fromNullableString(?string $id): ?self
    {
        return $id !== null ? self::fromString($id) : null;
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
     * Obtiene el tipo de identificador
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Verifica si es un IMDB ID
     */
    public function isImdb(): bool
    {
        return $this->type === 'imdb';
    }

    /**
     * Verifica si es un TMDB ID
     */
    public function isTmdb(): bool
    {
        return $this->type === 'tmdb';
    }

    /**
     * Verifica si es un ISBN
     */
    public function isIsbn(): bool
    {
        return $this->type === 'isbn';
    }

    /**
     * Compara dos identificadores
     */
    public function equals(MovieIdentifier $other): bool
    {
        return $this->value === $other->value && $this->type === $other->type;
    }

    /**
     * Convierte a formato de visualización
     */
    public function toDisplay(): string
    {
        return match($this->type) {
            'imdb' => "IMDB: {$this->value}",
            'tmdb' => "TMDB: {$this->value}",
            'isbn' => "ISBN: {$this->value}",
            default => $this->value
        };
    }

    /**
     * Detecta el tipo de identificador automáticamente
     */
    private static function detectType(string $id): string
    {
        // IMDB ID: ttXXXXXXX (7+ dígitos)
        if (preg_match('/^tt\d{7,}$/i', $id)) {
            return 'imdb';
        }
        
        // TMDB ID: número puro
        if (ctype_digit($id) && strlen($id) <= 8) {
            return 'tmdb';
        }
        
        // ISBN: 10 o 13 caracteres (números y posible X)
        $cleaned = preg_replace('/[^0-9Xx]/', '', $id);
        if (strlen($cleaned) === 10 || strlen($cleaned) === 13) {
            return 'isbn';
        }
        
        return 'custom';
    }

    /**
     * Valida el identificador según su tipo
     */
    private function validate(string $id, string $type): void
    {
        if (empty(trim($id))) {
            throw new InvalidArgumentException('Movie identifier cannot be empty');
        }

        switch ($type) {
            case 'imdb':
                if (!preg_match('/^tt\d{7,}$/i', $id)) {
                    throw new InvalidArgumentException(
                        "Invalid IMDB ID format: {$id}. Expected format: tt1234567"
                    );
                }
                break;
                
            case 'tmdb':
                if (!ctype_digit($id)) {
                    throw new InvalidArgumentException(
                        "Invalid TMDB ID: must be numeric, got: {$id}"
                    );
                }
                break;
                
            case 'isbn':
                // Validar usando ISBN VO
                try {
                    ISBN::fromString($id);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException(
                        "Invalid ISBN for movie: {$e->getMessage()}"
                    );
                }
                break;
                
            case 'custom':
                // Validación flexible para IDs custom
                if (strlen($id) > 255) {
                    throw new InvalidArgumentException(
                        "Custom ID too long: maximum 255 characters"
                    );
                }
                break;
                
            default:
                throw new InvalidArgumentException("Unknown identifier type: {$type}");
        }
    }
}
