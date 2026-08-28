<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

use InvalidArgumentException;

/**
 * Las listas públicas de otro usuario, para `/user/:username`.
 *
 * Se pide por `username` y no por id porque es lo que la ruta del perfil ya
 * lleva, igual que `GetPublicProfileQuery`. El `viewerUserId` viaja aunque hoy
 * no cambie el resultado —una lista pública lo es para cualquier registrado—:
 * es lo que permite, sin cambiar la firma, que algún día el propio dueño vea
 * también las suyas privadas en su perfil.
 */
final readonly class GetUserListsQuery
{
    public function __construct(
        public string $username,
        public int    $viewerUserId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $username = trim((string) ($data['username'] ?? ''));
        if ($username === '') {
            throw new InvalidArgumentException('username is required');
        }

        return new self(username: $username, viewerUserId: $userId);
    }
}
