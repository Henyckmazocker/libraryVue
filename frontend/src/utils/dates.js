import { intlLocale, t } from '@/config/i18n';

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

/**
 * El rótulo de un día del diario: `Hoy`, `Ayer`, o la fecha larga con su día de
 * la semana.
 *
 * `entry_date` es una cadena `YYYY-MM-DD` y se compara **como cadena** con la de
 * hoy: convertirla a `Date` para compararlas es donde aparecen los errores de
 * huso. Solo se construye un `Date` para formatear, y con la hora del mediodía
 * para que ningún desplazamiento de zona lo mueva de día.
 *
 * Vive aquí, y no en `JournalView.vue`, porque el diario se pinta en dos sitios:
 * el propio y la sección del perfil público de un amigo.
 *
 * @param {string} fecha Fecha `YYYY-MM-DD`.
 * @returns {string} p. ej. `Hoy` o `viernes, 4 de septiembre de 2026`.
 */
/**
 * Hoy en formato `YYYY-MM-DD`, que es el que `<input type="date">` exige en
 * `min` y `max`.
 *
 * Local y no UTC a propósito. `toISOString()` devuelve el día de Greenwich, y
 * España va **por delante**: entre las 00:00 y las 02:00 de un verano español
 * daría **ayer** (`2026-09-10T00:30+02:00` → `2026-09-09`), así que el tope
 * impediría fechar el día en curso justo en la franja en la que nadie lo
 * prueba. Es el mismo cuidado que `journalDayLabel` toma más abajo al comparar
 * `entry_date` como cadena en vez de construir un `Date`.
 *
 * No se cachea: una pestaña abierta a través de la medianoche tiene que cambiar
 * de tope. Misma decisión que `intlLocale()` (ver la cabecera de este módulo).
 *
 * @returns {string} p. ej. `2026-09-09`.
 */
export function hoyISO () {
  const d = new Date();
  const mes = String(d.getMonth() + 1).padStart(2, '0');
  const dia = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mes}-${dia}`;
}

export function journalDayLabel (fecha) {
  const hoy = new Date().toLocaleDateString('sv-SE');

  if (fecha === hoy) {
    return t('journal.today');
  }

  const ayer = new Date(Date.now() - 86400000).toLocaleDateString('sv-SE');

  if (fecha === ayer) {
    return t('journal.yesterday');
  }

  return new Date(`${fecha}T12:00:00`).toLocaleDateString(intlLocale(), {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  });
}
