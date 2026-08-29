<?php

declare(strict_types=1);

namespace App\Domain\Repository\Club;

interface ClubProgressRepositoryInterface
{
    /**
     * Cuántos miembros del club han COMPLETADO el ítem.
     *
     * Es la mitad barata de lo que midió el M0: la otra mitad —la página, la
     * temporada— es el `point` y pertenece al M3. Aquí solo interesa el sí/no.
     *
     * El estado se busca **por nombre**, nunca por id cableado: los
     * `<medio>_statuses.id` son `AUTO_INCREMENT` y su valor depende de cuántas
     * veces se haya sembrado la base — el M0 encontró `read` con `id = 10` en
     * una base recién creada. Un id cableado no revienta: devuelve «nadie lo ha
     * completado», que es un fallo silencioso.
     *
     * @param string $entityType uno de `ClubPick::ENTITY_TYPES`
     * @param string $entityId   el id EXTERNO (isbn, imdb, rawg, mbid, youtube)
     */
    public function countCompleted(int $clubId, string $entityType, string $entityId): int;

    /**
     * El progreso de cada miembro sobre el ítem: dónde va y si lo ha
     * completado. Es la otra mitad del M0, la que `countCompleted` no necesita.
     *
     * `point` es **la posición en el eje del medio** —página en libros,
     * temporada en series— y viaja `null` en los medios sin eje y en quien no
     * ha empezado. Un miembro **sin el ítem en su biblioteca sale igualmente**,
     * con `point: null` y `completed: false`: es «no ha empezado», no un error,
     * y ocultarlo escondería justo a quien bloquea el cierre automático.
     *
     * @return array<int, array{user_id:int, username:string, point:?int, completed:bool}>
     */
    public function findProgress(int $clubId, string $entityType, string $entityId): array;

    /**
     * El eje que le toca a este ítem: `'page'`, `'season'` o `null`.
     *
     * No sale del `entity_type` a secas, y ese es el matiz: **una serie se
     * guarda como `'movie'`** —con `AddMovieUseCase`— así que hay que mirar
     * `movie.media_type` para distinguirla. Un `'movie'` que sea serie va por
     * el eje `season`; el resto de películas, por el binario.
     */
    public function axisFor(string $entityType, string $entityId): ?string;
}
