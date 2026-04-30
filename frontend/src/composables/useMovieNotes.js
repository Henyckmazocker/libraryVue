import { ref } from 'vue'
import { useAuthStore } from '@/store/auth'
import Logger from '@/utils/logger'

/**
 * Composable for managing movie notes
 * Provides functions to add, update, delete and fetch notes for movies
 */
export function useMovieNotes() {
  const authStore = useAuthStore()
  const notes = ref([])
  const loading = ref(false)
  const error = ref(null)

  /**
   * Get all notes for a movie
   * @param {string} movieIsbn - Movie ISBN/ID (TMDb or IMDb)
   * @param {string|null} noteType - Optional filter by note type
   * @returns {Promise<{success: boolean, data?: Array, error?: string}>}
   */
  async function getNotes(movieIsbn, noteType = null) {
    loading.value = true
    error.value = null
    
    try {
      const payload = { movieIsbn }
      if (noteType) payload.noteType = noteType

      const response = await authStore.authenticatedApiCall('get_movie_notes', payload)

      if (response.data && response.data.status === 'success') {
        notes.value = response.data.data || []
        Logger.info('Movie notes loaded', { count: notes.value.length })
        return { success: true, data: notes.value }
      } else {
        error.value = response.data?.message || 'Failed to load notes'
        Logger.error('Failed to load movie notes', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error loading notes'
      Logger.error('Error loading movie notes', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Add a new note to a movie
   * @param {string} movieIsbn - Movie ISBN/ID
   * @param {string} noteText - Note text content
   * @param {string} noteType - Note type (note, review, thought)
   * @param {boolean} isPrivate - Whether note is private
   * @returns {Promise<{success: boolean, data?: Object, error?: string}>}
   */
  async function addNote(movieIsbn, noteText, noteType = 'note', isPrivate = true) {
    loading.value = true
    error.value = null
    
    try {
      const response = await authStore.authenticatedApiCall('add_movie_note', {
        movieIsbn,
        noteText,
        noteType,
        isPrivate
      })

      if (response.data && response.data.status === 'success') {
        Logger.info('Movie note added', { movieIsbn, noteType })
        // Reload notes to get the updated list
        await getNotes(movieIsbn)
        return { success: true, data: response.data.data }
      } else {
        error.value = response.data?.message || 'Failed to add note'
        Logger.error('Failed to add movie note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error adding note'
      Logger.error('Error adding movie note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Update an existing note
   * @param {number} noteId - Note ID
   * @param {string} movieIsbn - Movie ISBN/ID (for reloading notes)
   * @param {string} noteText - Note text content
   * @param {string} noteType - Note type
   * @param {boolean} isPrivate - Whether note is private
   * @returns {Promise<{success: boolean, error?: string}>}
   */
  async function updateNote(noteId, movieIsbn, noteText, noteType, isPrivate) {
    loading.value = true
    error.value = null
    
    try {
      const response = await authStore.authenticatedApiCall('update_movie_note', {
        noteId,
        noteText,
        noteType,
        isPrivate
      })

      if (response.data && response.data.status === 'success') {
        Logger.info('Movie note updated', { noteId })
        // Reload notes to get the updated list
        await getNotes(movieIsbn)
        return { success: true }
      } else {
        error.value = response.data?.message || 'Failed to update note'
        Logger.error('Failed to update movie note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error updating note'
      Logger.error('Error updating movie note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Delete a note
   * @param {number} noteId - Note ID
   * @param {string} movieIsbn - Movie ISBN/ID (for reloading notes)
   * @returns {Promise<{success: boolean, error?: string}>}
   */
  async function deleteNote(noteId, movieIsbn) {
    loading.value = true
    error.value = null
    
    try {
      const response = await authStore.authenticatedApiCall('delete_movie_note', { noteId })

      if (response.data && response.data.status === 'success') {
        Logger.info('Movie note deleted', { noteId })
        // Reload notes to get the updated list
        await getNotes(movieIsbn)
        return { success: true }
      } else {
        error.value = response.data?.message || 'Failed to delete note'
        Logger.error('Failed to delete movie note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error deleting note'
      Logger.error('Error deleting movie note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Get human-readable label for note type
   * @param {string} type - Note type
   * @returns {string} Label for the note type
   */
  function getNoteTypeLabel(type) {
    const labels = {
      'note': 'Nota',
      'review': 'Reseña',
      'thought': 'Pensamiento'
    }
    return labels[type] || 'Nota'
  }

  /**
   * Get icon for note type
   * @param {string} type - Note type
   * @returns {string} PrimeIcons class name
   */
  function getNoteTypeIcon(type) {
    const icons = {
      'note': 'pi-file-edit',
      'review': 'pi-star',
      'thought': 'pi-lightbulb'
    }
    return icons[type] || 'pi-file-edit'
  }

  return {
    notes,
    loading,
    error,
    getNotes,
    addNote,
    updateNote,
    deleteNote,
    getNoteTypeLabel,
    getNoteTypeIcon
  }
}
