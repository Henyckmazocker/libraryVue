<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\MovieIdentifier;

/**
 * Command DTO for deleting a movie from user's library
 */
final readonly class DeleteMovieCommand
{
    public function __construct(
        public MovieIdentifier $id,
        public int $userId
    ) {}

    /**
     * `isbn` va primero porque es lo que manda el cliente: la columna que identifica
     * una película se llama así por herencia del esquema de libros. Las otras tres se
     * aceptan para quien ya llamaba de otra forma, `movieIsbn` incluida —que es la
     * clave del ALTA y de los estados, y confundirlas es fácil—.
     */
    public static function fromArray(array $data, int $userId): self
    {
        $id = $data['isbn'] ?? $data['id'] ?? $data['imdbID'] ?? $data['movieIsbn'] ?? null;

        // Sin esto, un payload sin identificador moría en un TypeError de
        // `fromString(null)`: un 500 donde lo correcto es decir qué falta.
        if ($id === null || $id === '') {
            throw new \InvalidArgumentException(
                'delete_movie needs one of: isbn, id, imdbID, movieIsbn.'
            );
        }

        return new self(
            id: MovieIdentifier::fromString((string) $id),
            userId: $userId
        );
    }
}
