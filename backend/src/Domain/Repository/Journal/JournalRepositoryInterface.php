<?php

declare(strict_types=1);

namespace App\Domain\Repository\Journal;

use App\Domain\Model\JournalEntry;

interface JournalRepositoryInterface
{
    /**
     * Página del diario de un usuario, de lo más reciente a lo más antiguo.
     *
     * Cada entrada trae resuelto su `isRepeat`: cierto si el usuario ya tenía una
     * entrada anterior del mismo ítem. Se calcula en SQL, no pidiendo el historial
     * de cada fila.
     *
     * @param string|null $media uno de los seis medios, o null para todos
     * @return JournalEntry[]
     */
    public function findByUser(int $userId, int $limit, int $offset, ?string $media = null): array;

    /**
     * Total de entradas del usuario con el mismo filtro que `findByUser`, para
     * que el cliente sepa si hay más páginas.
     */
    public function countByUser(int $userId, ?string $media = null): int;

    /**
     * Inserta la entrada, o **actualiza** la que ya exista con el mismo
     * `(user_id, source, source_id)`. Las entradas manuales llevan `sourceId`
     * NULL y por tanto nunca chocan: repetir es insertar otra fila.
     *
     * @return int el id de la fila insertada o actualizada
     */
    public function add(JournalEntry $entry): int;

    /**
     * Cambia la fecha y/o la valoración de una entrada. Devuelve false si la
     * entrada no existe o no es de ese usuario.
     */
    public function update(int $entryId, int $userId, ?string $entryDate, ?float $rating): bool;

    /**
     * Borra una entrada del usuario. Devuelve false si no existía o no era suya.
     */
    public function delete(int $entryId, int $userId): bool;

    /**
     * Una entrada concreta, solo si es de ese usuario. `isRepeat` viene resuelto.
     */
    public function findById(int $entryId, int $userId): ?JournalEntry;

    /**
     * ¿Hay ya una entrada de este ítem anterior a esta fecha? Es lo que convierte
     * una entrada en «relectura» o «revisionado» de cara al listado.
     */
    public function hasEarlierEntry(int $userId, string $media, string $entityId, string $entryDate): bool;
}
