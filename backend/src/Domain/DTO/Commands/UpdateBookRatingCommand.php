<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for updating book rating
 *
 * `$rating` es **nulable a propósito**: `null` (o un `rating` de 0 en el
 * payload) significa «borra mi valoración», no «valor inválido». Los cinco
 * comandos de valoración comparten ese criterio desde el 2026-09-09.
 *
 * El orden de los parámetros `(ISBN, int, ?Rating)` se conserva: lo leen
 * `UpdateBookRatingUseCase` y `BookController::updateBookRating` por nombre de
 * propiedad, y cambiarlo obligaría a tocar los dos sin ganar nada.
 */
final readonly class UpdateBookRatingCommand
{
    public function __construct(
        public ISBN $isbn,
        public int $userId,
        public ?Rating $rating
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            isbn: ISBN::fromString(
                $data['isbn'] ?? throw new \InvalidArgumentException('ISBN is required.')
            ),
            userId: $userId,
            rating: isset($data['rating']) && (float)$data['rating'] > 0
                ? Rating::fromNullableFloat((float)$data['rating'])
                : null
        );
    }
}
