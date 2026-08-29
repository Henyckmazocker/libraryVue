<?php

declare(strict_types=1);

namespace App\Domain\Repository\Club;

use App\Domain\Model\Club;

interface ClubRepositoryInterface
{
    public function save(Club $club): Club;

    public function findById(int $clubId): ?Club;

    /**
     * Los clubs en los que el usuario está, sea dueño o miembro invitado.
     *
     * Es UNA consulta sobre `club_member` y no dos: al dueño se le da de alta
     * como miembro al crear el club, así que la tabla de miembros ya lo
     * contiene. Es la diferencia con `MediaListRepository::findForUser`, que sí
     * necesita un `UNION` porque el dueño de una lista no es un colaborador.
     *
     * @return Club[]
     */
    public function findForUser(int $userId): array;

    /** Las filas de `club_member` y `club_pick` caen por CASCADE. */
    public function delete(int $clubId): void;
}
