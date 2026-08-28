<?php

declare(strict_types=1);

namespace App\Domain\Repository\MediaList;

/**
 * Los colaboradores de una lista.
 *
 * Nació en el M0 con una sola operación —la pregunta que `ListAccess` necesita—
 * porque la regla de visibilidad se cerró antes que el esquema. El alta, la baja
 * y el listado llegan con el M4.
 */
interface MediaListCollaboratorRepositoryInterface
{
    /**
     * ¿Es este usuario colaborador de esta lista?
     *
     * Se consulta en las TRES visibilidades, no solo en `collaborative`: una
     * lista `public` puede tener colaboradores que la editen, y es así como se
     * consigue «que todos la vean y unos pocos la editen».
     */
    public function isCollaborator(int $listId, int $userId): bool;

    /**
     * Da de alta a un colaborador. Idempotente: la PK compuesta ya impide el
     * duplicado, y aceptar dos veces la misma invitación desde dos pestañas no
     * es un error que merezca un 500.
     */
    public function add(int $listId, int $userId): void;

    public function remove(int $listId, int $userId): void;

    /**
     * Los colaboradores de una lista, con lo que la interfaz necesita para
     * pintarlos. Devuelve filas planas y no modelos: no hay comportamiento que
     * colgar de un colaborador, solo un nombre y una cara.
     *
     * @return array<int, array{user_id:int, username:string, name:string, picture:?string, added_at:string}>
     */
    public function findByList(int $listId): array;

    /** Se lleva a todos: lo usa `update_list` al bajar de `collaborative`. */
    public function removeAll(int $listId): void;
}
