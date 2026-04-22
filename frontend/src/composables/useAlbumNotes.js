import { ref } from 'vue'
import { useAuthStore } from '@/store/auth'
import Logger from '@/utils/logger'

/**
 * Composable for managing album notes
 * Provides functions to add, update, delete and fetch notes for albums
 */
export function useAlbumNotes() {
  const authStore = useAuthStore()
  const notes = ref([])
  const loading = ref(false)
  const error = ref(null)

  /**
   * Get all notes for an album
   * @param {number} albumId - Album ID
   * @param {string|null} noteType - Optional filter by note type
   */
  async function getNotes(albumId, noteType = null) {
    loading.value = true
    error.value = null

    try {
      const payload = { albumId }
      if (noteType) payload.noteType = noteType

      const response = await authStore.authenticatedApiCall('get_album_notes', payload)

      if (response.data && response.data.status === 'success') {
        notes.value = response.data.data || []
        Logger.info('Album notes loaded', { count: notes.value.length })
        return { success: true, data: notes.value }
      } else {
        error.value = response.data?.message || 'Failed to load notes'
        Logger.error('Failed to load album notes', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error loading notes'
      Logger.error('Error loading album notes', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Add a new note to an album
   * @param {number} albumId - Album ID
   * @param {string} noteText - Note text content
   * @param {string} noteType - Note type (note, review, thought)
   * @param {boolean} isPrivate - Whether note is private
   */
  async function addNote(albumId, noteText, noteType = 'note', isPrivate = true) {
    loading.value = true
    error.value = null

    try {
      const response = await authStore.authenticatedApiCall('add_album_note', {
        albumId,
        noteText,
        noteType,
        isPrivate
      })

      if (response.data && response.data.status === 'success') {
        Logger.info('Album note added', { albumId, noteType })
        await getNotes(albumId)
        return { success: true, data: response.data.data }
      } else {
        error.value = response.data?.message || 'Failed to add note'
        Logger.error('Failed to add album note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error adding note'
      Logger.error('Error adding album note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Update an existing note
   * @param {number} noteId - Note ID
   * @param {number} albumId - Album ID (for reloading notes)
   * @param {string} noteText - Note text content
   * @param {string} noteType - Note type
   * @param {boolean} isPrivate - Whether note is private
   */
  async function updateNote(noteId, albumId, noteText, noteType, isPrivate) {
    loading.value = true
    error.value = null

    try {
      const response = await authStore.authenticatedApiCall('update_album_note', {
        noteId,
        noteText,
        noteType,
        isPrivate
      })

      if (response.data && response.data.status === 'success') {
        Logger.info('Album note updated', { noteId })
        await getNotes(albumId)
        return { success: true }
      } else {
        error.value = response.data?.message || 'Failed to update note'
        Logger.error('Failed to update album note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error updating note'
      Logger.error('Error updating album note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Delete a note
   * @param {number} noteId - Note ID
   * @param {number} albumId - Album ID (for reloading notes)
   */
  async function deleteNote(noteId, albumId) {
    loading.value = true
    error.value = null

    try {
      const response = await authStore.authenticatedApiCall('delete_album_note', { noteId })

      if (response.data && response.data.status === 'success') {
        Logger.info('Album note deleted', { noteId })
        await getNotes(albumId)
        return { success: true }
      } else {
        error.value = response.data?.message || 'Failed to delete note'
        Logger.error('Failed to delete album note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error deleting note'
      Logger.error('Error deleting album note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  function getNoteTypeLabel(type) {
    const labels = {
      'note': 'Nota',
      'review': 'Reseña',
      'thought': 'Pensamiento'
    }
    return labels[type] || 'Nota'
  }

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
