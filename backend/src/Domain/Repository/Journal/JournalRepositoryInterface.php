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
     * `from`/`to` acotan por `entry_date` (columna `DATE`, comparada por la
     * cadena `YYYY-MM-DD`) y son los dos opcionales e independientes: es lo que
     * usa la vista de mes del calendario para pedir un mes concreto sin
     * paginar hacia atrás desde hoy.
     *
     * @param string|null $media uno de los seis medios, o null para todos
     * @param string|null $from  fecha `YYYY-MM-DD` inclusive, o null
     * @param string|null $to    fecha `YYYY-MM-DD` inclusive, o null
     * @return JournalEntry[]
     */
    public function findByUser(
        int $userId,
        int $limit,
        int $offset,
        ?string $media = null,
        ?string $from = null,
        ?string $to = null
    ): array;

    /**
     * Total de entradas del usuario con el mismo filtro que `findByUser`, para
     * que el cliente sepa si hay más páginas. El rango cuenta: sin él, pedir un
     * mes devolvería el total del diario entero y `hasMore` mentiría.
     */
    public function countByUser(
        int $userId,
        ?string $media = null,
        ?string $from = null,
        ?string $to = null
    ): int;

    /**
     * El agregado por día del calendario: una fila **por día con entradas**,
     * dentro del rango dado. Los días vacíos no salen — un año sin nada son
     * cero filas, no 365 ceros.
     *
     * El `media` es el mismo filtro del listado: las píldoras de `/journal`
     * mandan también sobre el calendario. `null` es «todos».
     *
     * @param string $from fecha `YYYY-MM-DD` inclusive
     * @param string $to   fecha `YYYY-MM-DD` inclusive
     * @return array<string, array{count: int, media: string[]}> indexado por `YYYY-MM-DD`
     */
    public function countByDay(int $userId, string $from, string $to, ?string $media = null): array;

    /**
     * Los años con al menos una entrada, de más reciente a más antiguo, para el
     * navegador de años del calendario.
     *
     * **Sin filtro por medio, a propósito**, al revés que `countByDay`: filtrar
     * aquí haría desaparecer años enteros del selector y dejaría al usuario sin
     * forma de volver a ellos.
     *
     * @return int[]
     */
    public function yearsWithEntries(int $userId): array;

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
