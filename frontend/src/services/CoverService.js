/**
 * Service para las portadas servidas por el propio backend.
 *
 * El mirror de catálogos dejó las búsquedas funcionando sin red, pero las
 * carátulas seguían viniendo de CDN ajenos: si `image.tmdb.org` no responde, la
 * biblioteca se ve como una lista de rectángulos rotos. El backend guarda una
 * copia de la portada de todo lo que el usuario añade y la sirve por
 * `GET ?cover=<media_type>/<clave>`.
 *
 * Dos límites que conviene tener presentes antes de usar esto en otro sitio:
 *
 *  1. **Solo vale para lo que está en la biblioteca.** El endpoint únicamente
 *     conoce lo que se ha guardado; un resultado de búsqueda no tiene fila y
 *     recibiría un 404. Ahí la portada tiene que seguir viniendo de su CDN.
 *  2. **La clave tiene que ser la de `idOf`** del bloque `libraryItem` de
 *     `config/mediaRegistry.js`, porque es la misma con la que
 *     `CoverSeeder`/`CoverStore` registran la fila en el backend.
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

}

export default new CoverService();
