import { apiError } from '@/composables/useApiError'
import { t } from '@/config/i18n'

/**
 * Shared utilities for Pinia stores
 * Extracted to avoid code duplication across books, movies, and games stores
 */

/**
 * Traduce un error de una llamada al backend a algo que el usuario pueda leer.
 *
 * Era la **tercera** copia de la misma lógica —`_messageFor` en dos stores y
 * `_handleError` en `store/sessions.js` eran las otras— y además enseñaba el
 * `message` del backend, que llega en inglés. Ahora delega en `apiError`, que
 * resuelve por código y manda ese mensaje al `Logger`.
 *
 * Se conserva el nombre porque lo usa la factoría de los cinco stores de medio
 * (`store/createMediaStore.js:5`), y cambiarlo no aporta nada.
 *
 * @param {Error|object|number} err
 * @param {object} [claves] - claves del catálogo por código; ver `apiError`
 * @returns {string}
 */
export function handleStoreError (err, claves = {}) {
  // Un error de red no trae respuesta ni código: no hay nada que consultar.
  if (err && typeof err === 'object' && err.request && !err.response) {
    return t('errors.network')
  }

  return apiError(err, claves)
}
