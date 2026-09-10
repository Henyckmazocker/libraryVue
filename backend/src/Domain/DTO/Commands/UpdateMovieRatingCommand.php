<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for updating movie rating
 *
 * `$rating` es nulable: `null` (o un `rating` de 0) borra la valoración.
 */
final readonly class UpdateMovieRatingCommand
{
    public function __construct(
        public int $userId,
        public MovieIdentifier $id,
        public ?Rating $rating
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            // El último `??` iba SIN default, así que un payload sin
            // identificador salía por un warning de índice indefinido que en dev
            // se imprime como HTML antes del JSON. Ahora es una excepción
            // explícita; la ruta, además, ya exige `isbn`.
            id: MovieIdentifier::fromString(
                $data['id']
                    ?? $data['imdbID']
                    ?? $data['isbn']
                    ?? throw new \InvalidArgumentException('Movie identifier is required.')
            ),
            rating: isset($data['rating']) && (float)$data['rating'] > 0
                ? Rating::fromNullableFloat((float)$data['rating'])
                : null
        );
    }
}
