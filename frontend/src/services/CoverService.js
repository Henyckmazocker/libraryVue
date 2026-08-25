/**
 * Service para las portadas servidas por el propio backend.
 *
 * El mirror de catálogos dejó las búsquedas funcionando sin red, pero las
 * carátulas seguían viniendo de CDN ajenos: si `image.tmdb.org` no responde, la
 * biblioteca se ve como una lista de rectángulos rotos. El backend guarda una
 * copia de la portada de todo lo que el usuario añade y la sirve por
 * `GET ?cover=<media_type>/<clave>`.
 *
 * Hay **dos** métodos y no son intercambiables, porque el backend distingue dos
 * poblaciones distintas en `cover_file`:
 *
 *  - `localCoverUrl()` → `scope = 'library'`. Solo vale para lo que el usuario
 *    **ha guardado**. La clave tiene que ser la de `idOf` del bloque
 *    `libraryItem` de `config/mediaRegistry.js`, porque es la misma con la que
 *    `CoverSeeder`/`CoverStore` registran la fila.
 *  - `catalogCoverUrl()` → `scope = 'catalog'`. Vale para lo que **no** está
 *    guardado: un resultado de búsqueda o un carrusel. El backend deduce la URL
 *    de origen a partir de una clave del mirror —un tconst para películas, un
 *    MBID para álbumes—, la registra y la descarga después de responder, así que
 *    la segunda vez ya sale del disco. Solo hay resolución para `movie` y
 *    `album`: libros, juegos y vídeos se buscan contra APIs sin dump y su URL no
 *    se deduce de nada sin llamarlas antes.
 *
 * Hasta el 2026-08-25 esta nota decía que el endpoint «solo vale para lo que
 * está en la biblioteca». Dejó de ser cierto con
 * [[LibraryVue/Planes/…/Plan - Caché de Portadas del Catálogo]].
 *
 * El endpoint degrada solo: si aún no hay copia local devuelve un 302 al origen,
 * así que apuntar aquí nunca es peor que apuntar al CDN. Aun así, quien lo use
 * debería llevar un `@error` que caiga a la URL remota, por si la fila no existe.
 */

// Misma resolución que store/auth.js:134. No se importa de allí para no
// arrastrar el store entero a algo que solo compone una cadena.
const API_URL = process.env.VUE_APP_API_URL || '/index.php';

class CoverService {

  /**
   * URL de la portada local de un ítem de la biblioteca.
   *
   * @param {string} mediaType 'movie' | 'book' | 'album' | 'game' | 'video'
   * @param {string|number} entityKey la clave de `idOf` del registry
   * @returns {string|null} null si falta algún dato, para que quien llama caiga
   *                        a la URL remota en vez de pedir una portada imposible
   */
  localCoverUrl(mediaType, entityKey) {
    if (!mediaType || entityKey === null || entityKey === undefined || entityKey === '') {
      return null;
    }

    const separator = API_URL.includes('?') ? '&' : '?';

    return `${API_URL}${separator}cover=${mediaType}/${encodeURIComponent(entityKey)}`;
  }

  /**
   * URL de la portada de un ítem del **catálogo**, que no está en la biblioteca.
   *
   * La cadena es idéntica a la de `localCoverUrl`: el endpoint es el mismo y
   * decide por sí solo si la clave es de biblioteca o de catálogo. Existe como
   * método aparte porque lo que cambia es el **contrato de quien llama** — aquí
   * la clave es la del mirror (tconst o MBID), no la de `idOf`, y un 404 es un
   * resultado esperado en vez de un síntoma.
   *
   * @param {string} mediaType 'movie' | 'album' — los únicos con resolución
   * @param {string|number} entityKey tconst de IMDb o MBID de MusicBrainz
   * @returns {string|null} null si falta algún dato, para caer a la URL remota
   */
  catalogCoverUrl(mediaType, entityKey) {
    if (mediaType !== 'movie' && mediaType !== 'album') {
      return null;
    }

    return this.localCoverUrl(mediaType, entityKey);
  }

}

export default new CoverService();
