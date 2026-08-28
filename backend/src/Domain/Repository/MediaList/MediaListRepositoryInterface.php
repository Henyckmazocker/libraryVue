<?php

declare(strict_types=1);

namespace App\Domain\Repository\MediaList;

use App\Domain\Model\MediaList;

interface MediaListRepositoryInterface
{
    public function save(MediaList $list): MediaList;

    public function findById(int $listId): ?MediaList;

    /**
     * Las listas de las que el usuario es dueño, más aquellas en las que
     * colabora. Es una consulta y no dos filtradas por `ListAccess`: filtrar en
     * PHP obligaría a traerse las listas de todo el mundo.
     *
     * @return MediaList[]
     */
    public function findForUser(int $userId): array;

    /**
     * Las listas **públicas** de un usuario, que es lo único que se enseña en
     * `/user/:username`. No pasa por `ListAccess` porque no es una decisión de
     * permiso sobre una lista concreta, sino el filtro de la consulta.
     *
     * @return MediaList[]
     */
    public function findPublicByOwner(int $ownerId): array;

    public function update(MediaList $list): void;

    /** Las filas de `media_list_item` y `media_list_collaborator` caen por CASCADE. */
    public function delete(int $listId): void;
}
