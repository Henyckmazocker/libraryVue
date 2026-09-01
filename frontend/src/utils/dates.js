import { intlLocale } from '@/config/i18n';

/**
 * Formateo de fechas para lo que se enseña en pantalla.
 *
 * Existe porque `mediaRegistry` pintaba `published_at` en crudo y la ficha de
 * vídeo enseñaba `2026-08-10T20:54:12Z` tal cual. El resto de la app ya
 * formateaba con `toLocaleDateString(intlLocale(), ...)`, pero cada sitio con su
 * copia; aquí vive la primera versión compartida.
 *
 * `intlLocale()` y no el código del catálogo: `Intl` quiere una etiqueta con
 * región (`es-ES`), y pasarle `'es'` a secas deja el formato a merced de la
 * implementación. Y se lee en cada llamada, no se cachea, para que un cambio de
 * idioma repinte lo ya formateado.
 */

/**
 * Fecha larga en el idioma activo. Cadena vacía si no hay fecha o si no se
 * puede interpretar: un `Invalid Date` en la ficha es peor que un hueco.
 *
 * @param {string|null|undefined} iso Fecha ISO-8601, con o sin hora.
 * @returns {string} p. ej. `10 de agosto de 2026`, o `''`.
 */
export function formatDate (iso) {
  if (!iso) return '';
  const fecha = new Date(iso);
  if (Number.isNaN(fecha.getTime())) return '';
  return fecha.toLocaleDateString(intlLocale(), {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  });
}
