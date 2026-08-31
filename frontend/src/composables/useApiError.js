import { t } from '@/config/i18n'
import Logger from '@/utils/logger'

/**
 * Traduce el resultado de una llamada al backend a algo que el usuario pueda
 * leer, **por código y no por texto**.
 *
 * El `message` del backend **no se enseña nunca**: llega en inglés y con la
 * redacción de quien escribió el endpoint. Sigue siendo útil, así que viaja al
 * `Logger`, que es su sitio.
 *
 * Sustituye a las **cuatro** copias que hacían esto mismo y cada una a su
 * manera: `_messageFor` de `store/lists.js` y de `store/clubs.js`,
 * `handleStoreError` de `utils/storeHelpers.js` —que usa la factoría de los
 * cinco stores de medio— y `_handleError` de `store/sessions.js`.
 *
 * La cadena de respaldo, de más específico a menos:
 *
 *   1. `claves[codigo]` — lo que el dominio quiera decir de ESE código
 *      («No tienes permiso sobre esta lista» no es lo mismo que «…este club»).
 *   2. `errors.<codigo>` — el genérico del catálogo, si existe.
 *   3. `claves.defecto` — lo que el dominio diga cuando no hay código.
 *   4. `errors.unknown`.
 *
 * Los valores de `claves` son **claves del catálogo**, no textos: así el idioma
 * se resuelve aquí y una sola vez.
 */

/** Los códigos con texto genérico propio en el catálogo. */
const GENERICOS = [400, 401, 403, 404, 409, 422, 429, 500, 503]

/**
 * Saca el código de lo que sea que le den: un número, un error de axios, o el
 * `data` de una respuesta que trae `http_code` (el backend lo manda ahí, ver
 * `BaseController::errorResponse`).
 */
function codigoDe (origen) {
  if (typeof origen === 'number') return origen
  if (!origen || typeof origen !== 'object') return null

  return origen.response?.data?.http_code ??
    origen.response?.status ??
    origen.data?.http_code ??
    origen.http_code ??
    null
}

/** El texto que mandó el backend, para el log. Nunca para la pantalla. */
function mensajeDelBackend (origen) {
  if (!origen || typeof origen !== 'object') return null
  return origen.response?.data?.message ?? origen.data?.message ?? origen.message ?? null
}

export function apiError (origen, claves = {}) {
  const codigo = codigoDe(origen)
  const delBackend = mensajeDelBackend(origen)

  if (delBackend) {
    Logger.error('[apiError]', { codigo, mensaje: delBackend })
  }

  if (codigo && claves[codigo]) return t(claves[codigo])
  if (codigo && GENERICOS.includes(Number(codigo))) return t(`errors.${codigo}`)
  if (claves.defecto) return t(claves.defecto)

  return t('errors.unknown')
}

/** Para consumirlo desde un componente, con la forma de siempre. */
export function useApiError () {
  return { apiError }
}
