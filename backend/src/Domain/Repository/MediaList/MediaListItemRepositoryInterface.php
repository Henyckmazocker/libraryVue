<?php

declare(strict_types=1);

namespace App\Domain\Repository\MediaList;

use App\Domain\Model\MediaListItem;

interface MediaListItemRepositoryInterface
{
    public function add(MediaListItem $item): MediaListItem;

    public function findById(int $itemId): ?MediaListItem;

    /**
     * Los ítems de una lista, en su orden.
     *
     * @return MediaListItem[]
     */
    public function findByList(int $listId): array;

    /**
     * ¿Está ya este ítem en la lista? El UNIQUE de la tabla lo impide; esto es
     * lo que lo convierte en un 409 legible en vez de un error de duplicado.
     */
    public function exists(int $listId, string $entityType, string $entityId): bool;

    /**
     * Cuántos ítems tiene cada una de estas listas.
     *
     * Va en bloque a propósito: `get_my_lists` pinta el contador de cada
     * tarjeta, y pedirlo lista por lista sería el N+1 que el índice de
     * `media_list` está puesto para evitar.
     *
     * @param  int[] $listIds
     * @return array<int, int>  listId => número de ítems
     */
    public function countByLists(array $listIds): array;

    public function remove(int $itemId): void;
}
