<?php

declare(strict_types=1);

namespace App\Domain\Repository\Club;

interface ClubNoteRepositoryInterface
{
    /**
     * Las notas **públicas** de los miembros del club sobre el ítem activo.
     *
     * Dos filtros que no se pueden mover al PHP:
     *
     *  - **`is_private = 0` va en el `WHERE`.** Filtrarlo después significaría
     *    que las notas privadas de otro viajaron hasta aquí, que es el mismo
     *    criterio por el que `get_my_lists` filtra la visibilidad en la
     *    consulta y no en el store.
     *  - **La pertenencia al club también.** Un `JOIN` con `club_member`, no un
     *    `array_filter`: si alguien salió del club, sus notas dejan de verse en
     *    esa pantalla sin que haya que acordarse en tres sitios.
     *
     * `point` es la posición de la nota en el eje del medio. Solo los libros lo
     * tienen de verdad (`user_edition_notes.page_number`, `NOT NULL`); en los
     * otros cuatro medios la columna no existe o no significa nada, y llega
     * `null`.
     *
     * @return array<int, array{note_id:int, user_id:int, username:string, text:string, point:?int, created_at:string}>
     */
    public function findPublicForPick(int $clubId, string $entityType, string $entityId): array;
}
