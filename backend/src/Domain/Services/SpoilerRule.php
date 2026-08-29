<?php

declare(strict_types=1);

namespace App\Domain\Services;

/**
 * Si la nota de otro miembro me destriparía el ítem activo del club.
 *
 * La regla existe AQUÍ y en ningún otro sitio, y sobre todo **no en la
 * plantilla**: difuminar con CSS un texto que está en el DOM es enseñarlo — se
 * lee con «inspeccionar elemento» o con un lector de pantalla. El servidor
 * decide y el texto de una nota marcada **no viaja**.
 *
 * ## La tabla de verdad
 *
 * | eje del medio | la nota tiene punto | spoiler si…                |
 * |---------------|---------------------|----------------------------|
 * | `page`/`season` | sí                | mi progreso < punto        |
 * | `page`/`season` | no                | no lo he completado        |
 * | `null`          | (nunca)           | no lo he completado        |
 *
 * Dos lecturas que se hacen mal:
 *
 * - **En libros el caso «nota sin página» no existe**: `user_edition_notes.page_number`
 *   es `INT UNSIGNED NOT NULL`. Esa rama está por las **series**, cuyas notas
 *   viven en `user_movie_notes` y cuyo `page_number` es `NULL` y no significa
 *   una temporada — leerlo sería un bug silencioso—; y por si algún día la
 *   columna de libros se relaja.
 * - **Sin progreso registrado no es «página 0», es «no he empezado»**, y por
 *   tanto cualquier nota con punto me adelanta. Tratar el `null` como cero da
 *   el mismo resultado aquí, pero deja de darlo en cuanto exista una nota en el
 *   punto 0.
 *
 * Y una guarda que la tabla no dice: **mi propia nota nunca es un spoiler**. La
 * escribí yo, así que ya sé lo que pone; marcarla me ocultaría mi propio texto
 * por ir por detrás de donde estaba cuando la escribí.
 */
final class SpoilerRule
{
    /**
     * @param int|null $notePoint     el punto de la nota en el eje, o `null`
     * @param string|null $axis       `'page'`, `'season'` o `null`
     * @param int|null $myPoint       mi posición en el eje, o `null` si no empecé
     * @param bool $iCompleted        si ya lo he terminado
     * @param bool $isMine            si la nota la escribí yo
     */
    public function isSpoiler(
        ?int $notePoint,
        ?string $axis,
        ?int $myPoint,
        bool $iCompleted,
        bool $isMine = false
    ): bool {
        if ($isMine) {
            return false;
        }

        // Haberlo completado desactiva la regla entera: ya no hay nada que
        // destripar. Va antes que el eje porque vale para los seis medios.
        if ($iCompleted) {
            return false;
        }

        if ($axis !== null && $notePoint !== null) {
            return ($myPoint ?? -1) < $notePoint;
        }

        // Sin eje, o con eje pero sin punto en la nota: lo único que se puede
        // afirmar es que no lo he terminado, y entonces cualquier nota puede
        // adelantarme.
        return true;
    }
}
