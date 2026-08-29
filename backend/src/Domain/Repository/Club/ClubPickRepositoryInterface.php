<?php

declare(strict_types=1);

namespace App\Domain\Repository\Club;

use App\Domain\Model\ClubPick;

interface ClubPickRepositoryInterface
{
    public function save(ClubPick $pick): ClubPick;

    /**
     * El ítem activo, que es el único con `finished_at IS NULL`. Sale por
     * `idx_club_pick_active (club_id, finished_at)`.
     */
    public function findActive(int $clubId): ?ClubPick;

    /**
     * El historial: los terminados, del más reciente al más antiguo. El activo
     * NO entra — la pantalla lo pinta aparte y mezclarlos obligaría al frontend
     * a filtrar por una fecha nula.
     *
     * @return ClubPick[]
     */
    public function findHistory(int $clubId): array;

    /**
     * Marca terminado. Devuelve `true` solo si esta llamada fue la que lo
     * cerró: el `UPDATE` lleva `AND finished_at IS NULL`, así que dos lecturas
     * simultáneas del club no pueden cerrar dos veces ni pisar una fecha ya
     * puesta. Quien recibe `false` es que llegó tarde, no que fallara.
     */
    public function finish(int $pickId): bool;
}
