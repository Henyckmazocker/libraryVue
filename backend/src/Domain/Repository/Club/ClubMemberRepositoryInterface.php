<?php

declare(strict_types=1);

namespace App\Domain\Repository\Club;

interface ClubMemberRepositoryInterface
{
    /**
     * Idempotente: entrar dos veces en el mismo club no es un error, es la
     * misma pertenencia. Lo permite el `INSERT IGNORE` sobre la PK compuesta.
     */
    public function add(int $clubId, int $userId): void;

    public function remove(int $clubId, int $userId): void;

    /**
     * La comprobación de pertenencia, que es el permiso de TODO el club salvo
     * `create_club`. Sale entera de la PK `(club_id, user_id)`.
     */
    public function isMember(int $clubId, int $userId): bool;

    /**
     * Los miembros con lo que la pantalla necesita para pintarlos: id, nombre,
     * username y foto. Se devuelve como array asociativo y no como `User[]`
     * porque no hay ninguna regla de dominio que aplicarles.
     *
     * @return array<int, array{user_id:int, name:string, username:?string, picture:?string, joined_at:string}>
     */
    public function findByClub(int $clubId): array;

    public function countMembers(int $clubId): int;
}
