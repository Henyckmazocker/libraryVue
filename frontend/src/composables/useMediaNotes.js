import { ref } from 'vue'
import { useAuthStore } from '@/store/auth'
import { getMediaConfig } from '@/config/mediaRegistry'
import Logger from '@/utils/logger'

/**
 * Composable único de notas para los cinco medios.
 *
 * Sustituye a useAlbumNotes / useEditionNotes / useGameNotes / useMovieNotes /
 * useVideoNotes, que quedan como reexportes mientras haya quien los importe.
 * Todo lo que cambiaba entre ellos —las acciones del backend, la clave del
 * identificador en el payload, los tipos de nota— sale de `mediaRegistry`.
 *
 * @param {string} media - clave del medio: 'book' | 'movie' | 'game' | 'album' | 'video'
 */
export function useMediaNotes (media) {
  const config = getMediaConfig(media)
  const { actions, typeIcons, typeFallbackIcon, typeFallbackLabel, types } = config.notes
  const idKey = config.idPayloadKey

  const authStore = useAuthStore()
  const notes = ref([])
  const loading = ref(false)
  const error = ref(null)

  /**
   * Envuelve una llamada al backend con el mismo manejo de loading, error y
   * logging que tenían los cinco composables por separado.
   * @param {string} action - acción del backend
   * @param {object} payload
   * @param {string} what - qué se estaba haciendo, para el mensaje de error
   * @param {function} onSuccess - recibe response.data y devuelve el resultado
   */
  async function call (action, payload, what, onSuccess) {
    loading.value = true
    error.value = null

    try {
      const response = await authStore.authenticatedApiCall(action, payload)

      if (response.data && response.data.status === 'success') {
        return onSuccess(response.data)
      }

      error.value = response.data?.message || `Failed to ${what}`
      Logger.error(`Failed to ${what}`, { media, error: error.value })
      return { success: false, error: error.value }
    } catch (err) {
      error.value = err.message || `Error ${what}`
      Logger.error(`Error ${what}`, { media, error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Carga todas las notas de un ítem.
   * @param {number|string} itemId
   * @param {string|null} noteType - filtro opcional por tipo
   * @param {number|null} pageNumber - solo ediciones, filtro por página
   */
  async function getNotes (itemId, noteType = null, pageNumber = null) {
    const payload = { [idKey]: itemId }
    if (noteType) payload.noteType = noteType
    if (config.notes.hasPageNumber && pageNumber) payload.pageNumber = pageNumber

    return call(actions.list, payload, 'load notes', (data) => {
      notes.value = data.data || []
      Logger.info('Notes loaded', { media, count: notes.value.length })
      return { success: true, data: notes.value }
    })
  }

  /**
   * Carga una nota concreta. Solo existe para ediciones
   * (`get_edition_note`, routes.php:265).
   * @param {number} noteId
   */
  async function getNote (noteId) {
    if (!actions.get) {
      throw new Error(`[useMediaNotes] "${media}" no tiene acción para leer una nota suelta`)
    }
    return call(actions.get, { noteId }, 'load note', (data) => ({ success: true, data: data.data }))
  }

  /**
   * Crea una nota.
   * @param {number|string} itemId
   * @param {string} noteText
   * @param {string} noteType
   * @param {boolean} isPrivate
   * @param {object} extra - campos propios del medio (p. ej. `pageNumber`)
   */
  async function addNote (itemId, noteText, noteType = 'note', isPrivate = true, extra = {}) {
    const payload = { [idKey]: itemId, noteText, noteType, isPrivate, ...extra }

    return call(actions.add, payload, 'add note', async (data) => {
      Logger.info('Note added', { media, noteType })
      await getNotes(itemId)
      return { success: true, data: data.data }
    })
  }

  /**
   * Actualiza una nota. `itemId` solo se usa para recargar la lista después.
   * @param {number} noteId
   * @param {number|string} itemId
   * @param {string} noteText
   * @param {string} noteType
   * @param {boolean} isPrivate
   * @param {object} extra - campos propios del medio (p. ej. `pageNumber`)
   */
  async function updateNote (noteId, itemId, noteText, noteType, isPrivate, extra = {}) {
    const payload = { noteId, noteText, noteType, isPrivate, ...extra }

    return call(actions.update, payload, 'update note', async () => {
      Logger.info('Note updated', { media, noteId })
      await getNotes(itemId)
      return { success: true }
    })
  }

  /**
   * Borra una nota. `itemId` solo se usa para recargar la lista después.
   * @param {number} noteId
   * @param {number|string} itemId
   */
  async function deleteNote (noteId, itemId) {
    return call(actions.delete, { noteId }, 'delete note', async () => {
      Logger.info('Note deleted', { media, noteId })
      await getNotes(itemId)
      return { success: true }
    })
  }

  /** Etiqueta legible de un tipo de nota. */
  function getNoteTypeLabel (type) {
    const found = types.find((t) => t.value === type)
    // Las ediciones caen al propio `type`; el resto, a 'Nota'.
    return found?.label || typeFallbackLabel || type
  }

  /** Icono de PrimeIcons de un tipo de nota, sin el prefijo `pi`. */
  function getNoteTypeIcon (type) {
    return typeIcons[type] || typeFallbackIcon
  }

  return {
    notes,
    loading,
    error,
    getNotes,
    getNote,
    addNote,
    updateNote,
    deleteNote,
    getNoteTypeLabel,
    getNoteTypeIcon
  }
}
