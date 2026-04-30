import { ref } from 'vue'
import { useAuthStore } from '@/store/auth'
import Logger from '@/utils/logger'

/**
 * Composable for managing game notes
 * Provides functions to add, update, delete and fetch notes for games
 */
export function useGameNotes() {
  const authStore = useAuthStore()
  const notes = ref([])
  const loading = ref(false)
  const error = ref(null)

  /**
   * Get all notes for a game
   * @param {number} gameId - Game ID
   * @param {string|null} noteType - Optional filter by note type
   * @returns {Promise<{success: boolean, data?: Array, error?: string}>}
   */
  async function getNotes(gameId, noteType = null) {
    loading.value = true
    error.value = null
    
    try {
      const payload = { gameId }
      if (noteType) payload.noteType = noteType

      const response = await authStore.authenticatedApiCall('get_game_notes', payload)

      if (response.data && response.data.status === 'success') {
        notes.value = response.data.data || []
        Logger.info('Game notes loaded', { count: notes.value.length })
        return { success: true, data: notes.value }
      } else {
        error.value = response.data?.message || 'Failed to load notes'
        Logger.error('Failed to load game notes', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error loading notes'
      Logger.error('Error loading game notes', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Add a new note to a game
   * @param {number} gameId - Game ID
   * @param {string} noteText - Note text content
   * @param {string} noteType - Note type (note, review, thought)
   * @param {boolean} isPrivate - Whether note is private
   * @returns {Promise<{success: boolean, data?: Object, error?: string}>}
   */
  async function addNote(gameId, noteText, noteType = 'note', isPrivate = true) {
    loading.value = true
    error.value = null
    
    try {
      const response = await authStore.authenticatedApiCall('add_game_note', {
        gameId,
        noteText,
        noteType,
        isPrivate
      })

      if (response.data && response.data.status === 'success') {
        Logger.info('Game note added', { gameId, noteType })
        // Reload notes to get the updated list
        await getNotes(gameId)
        return { success: true, data: response.data.data }
      } else {
        error.value = response.data?.message || 'Failed to add note'
        Logger.error('Failed to add game note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error adding note'
      Logger.error('Error adding game note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Update an existing note
   * @param {number} noteId - Note ID
   * @param {number} gameId - Game ID (for reloading notes)
   * @param {string} noteText - Note text content
   * @param {string} noteType - Note type
   * @param {boolean} isPrivate - Whether note is private
   * @returns {Promise<{success: boolean, error?: string}>}
   */
  async function updateNote(noteId, gameId, noteText, noteType, isPrivate) {
    loading.value = true
    error.value = null
    
    try {
      const response = await authStore.authenticatedApiCall('update_game_note', {
        noteId,
        noteText,
        noteType,
        isPrivate
      })

      if (response.data && response.data.status === 'success') {
        Logger.info('Game note updated', { noteId })
        // Reload notes to get the updated list
        await getNotes(gameId)
        return { success: true }
      } else {
        error.value = response.data?.message || 'Failed to update note'
        Logger.error('Failed to update game note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error updating note'
      Logger.error('Error updating game note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Delete a note
   * @param {number} noteId - Note ID
   * @param {number} gameId - Game ID (for reloading notes)
   * @returns {Promise<{success: boolean, error?: string}>}
   */
  async function deleteNote(noteId, gameId) {
    loading.value = true
    error.value = null
    
    try {
      const response = await authStore.authenticatedApiCall('delete_game_note', { noteId })

      if (response.data && response.data.status === 'success') {
        Logger.info('Game note deleted', { noteId })
        // Reload notes to get the updated list
        await getNotes(gameId)
        return { success: true }
      } else {
        error.value = response.data?.message || 'Failed to delete note'
        Logger.error('Failed to delete game note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error deleting note'
      Logger.error('Error deleting game note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Get note type label
   * @param {string} type - Note type
   * @returns {string} Label
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
   * Get note type icon
   * @param {string} type - Note type
   * @returns {string} Icon class
   */
  function getNoteTypeIcon(type) {
    const icons = {
      'note': 'pi-file-edit',
      'review': 'pi-star',
      'thought': 'pi-comment'
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
