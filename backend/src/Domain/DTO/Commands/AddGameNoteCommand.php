<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO para añadir una nota a un game.
 *
 * Nace el 2026-08-25 con su use case: hasta entonces `games` era una de las dos
 * entidades que iban **del controlador al repositorio**, sin use case ni comando,
 * y por eso no tenían dónde poner la guarda de privacidad del feed.
 */
final readonly class AddGameNoteCommand
{
    public function __construct(
        public int    $userId,
        public int    $gameId,
        public string $noteText,
        public string $noteType = 'note',
        public bool   $isPrivate = true
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId:    $userId,
            gameId:    (int) ($data['gameId'] ?? $data['game_id'] ?? 0),
            noteText:  (string) ($data['noteText'] ?? $data['note_text'] ?? ''),
            noteType:  (string) ($data['noteType'] ?? $data['note_type'] ?? 'note'),
            // `true` por defecto, igual que la columna `is_private`: una nota
            // nace privada y hay que marcarla como pública a propósito.
            isPrivate: (bool) ($data['isPrivate'] ?? $data['is_private'] ?? true)
        );
    }
}
