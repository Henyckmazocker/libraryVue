import { ref } from 'vue'
import { useAuthStore } from '@/store/auth'
import Logger from '@/utils/logger'

/**
 * Composable for managing edition notes
 * Provides functions to add, update, delete and fetch notes for book editions
 */
export function useEditionNotes() {
  const authStore = useAuthStore()
  const notes = ref([])
  const loading = ref(false)
  const error = ref(null)

  /**
   * Get all notes for a user edition
   * @param {number} userEditionId - User edition ID
   * @param {string|null} noteType - Optional filter by note type
   * @param {number|null} pageNumber - Optional filter by page number
   * @returns {Promise<{success: boolean, data?: Array, error?: string}>}
   */
  async function getNotes(userEditionId, noteType = null, pageNumber = null) {
    loading.value = true
    error.value = null
    
    try {
      const payload = { userEditionId }
      if (noteType) payload.noteType = noteType
      if (pageNumber) payload.pageNumber = pageNumber

      const response = await authStore.authenticatedApiCall('get_edition_notes', payload)

      if (response.data && response.data.status === 'success') {
        notes.value = response.data.data || []
        Logger.info('Edition notes loaded', { count: notes.value.length })
        return { success: true, data: notes.value }
      } else {
        error.value = response.data?.message || 'Failed to load notes'
        Logger.error('Failed to load edition notes', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error loading notes'
      Logger.error('Error loading edition notes', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Get a single note by ID
   * @param {number} noteId - Note ID
   * @returns {Promise<{success: boolean, data?: Object, error?: string}>}
   */
  async function getNote(noteId) {
    loading.value = true
    error.value = null
    
    try {
      const response = await authStore.authenticatedApiCall('get_edition_note', { noteId })

      if (response.data && response.data.status === 'success') {
        Logger.info('Edition note loaded', { noteId })
        return { success: true, data: response.data.data }
      } else {
        error.value = response.data?.message || 'Failed to load note'
        Logger.error('Failed to load edition note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error loading note'
      Logger.error('Error loading edition note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Add a new note to an edition
   * @param {number} userEditionId - User edition ID
   * @param {number} pageNumber - Page number
   * @param {string|null} noteText - Note text content
   * @param {string} noteType - Note type (note, quote, thought, question, summary, progress, general)
   * @param {boolean} isPrivate - Whether note is private
   * @returns {Promise<{success: boolean, data?: Object, error?: string}>}
   */
  async function addNote(userEditionId, pageNumber, noteText = null, noteType = 'progress', isPrivate = true) {
    loading.value = true
    error.value = null
    
    try {
      const response = await authStore.authenticatedApiCall('add_edition_note', {
        userEditionId,
        pageNumber,
        noteText,
        noteType,
        isPrivate
      })

      if (response.data && response.data.status === 'success') {
        const newNote = response.data.data
        notes.value.push(newNote)
        Logger.info('Edition note added', { noteId: newNote?.id })
        return { success: true, data: newNote }
      } else {
        error.value = response.data?.message || 'Failed to add note'
        Logger.error('Failed to add edition note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error adding note'
      Logger.error('Error adding edition note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Update an existing note
   * @param {number} noteId - Note ID
   * @param {Object} updates - Fields to update (pageNumber, noteText, noteType, isPrivate)
   * @returns {Promise<{success: boolean, data?: Object, error?: string}>}
   */
  async function updateNote(noteId, updates) {
    loading.value = true
    error.value = null
    
    try {
      const response = await authStore.authenticatedApiCall('update_edition_note', {
        noteId,
        ...updates
      })

      if (response.data && response.data.status === 'success') {
        const updatedNote = response.data.data
        // Update in local notes array
        const index = notes.value.findIndex(n => n.id === noteId)
        if (index !== -1) {
          notes.value[index] = updatedNote
        }
        Logger.info('Edition note updated', { noteId })
        return { success: true, data: updatedNote }
      } else {
        error.value = response.data?.message || 'Failed to update note'
        Logger.error('Failed to update edition note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error updating note'
      Logger.error('Error updating edition note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Delete a note
   * @param {number} noteId - Note ID
   * @returns {Promise<{success: boolean, error?: string}>}
   */
  async function deleteNote(noteId) {
    loading.value = true
    error.value = null
    
    try {
      const response = await authStore.authenticatedApiCall('delete_edition_note', { noteId })

      if (response.data && response.data.status === 'success') {
        // Remove from local notes array
        notes.value = notes.value.filter(n => n.id !== noteId)
        Logger.info('Edition note deleted', { noteId })
        return { success: true }
      } else {
        error.value = response.data?.message || 'Failed to delete note'
        Logger.error('Failed to delete edition note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error deleting note'
      Logger.error('Error deleting edition note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  /**
   * Group notes by page number
   * @returns {Object} Notes grouped by page
   */
  function groupNotesByPage() {
    const grouped = {}
    notes.value.forEach(note => {
      const page = note.pageNumber || note.page_number
      if (!grouped[page]) {
        grouped[page] = []
      }
      grouped[page].push(note)
    })
    return grouped
  }

  /**
   * Get note type display name
   * @param {string} type - Note type
   * @returns {string} Display name
   */
  function getNoteTypeLabel(type) {
    const labels = {
      note: 'Nota',
      quote: 'Cita',
      thought: 'Reflexión',
      question: 'Pregunta',
      summary: 'Resumen',
      progress: 'Progreso',
      general: 'General'
    }
    return labels[type] || type
  }

  /**
   * Get note type icon
   * @param {string} type - Note type
   * @returns {string} Icon name
   */
  function getNoteTypeIcon(type) {
    const icons = {
      note: 'pi-pencil',
      quote: 'pi-quote-right',
      thought: 'pi-lightbulb',
      question: 'pi-question-circle',
      summary: 'pi-list',
      progress: 'pi-chart-line',
      general: 'pi-file'
    }
    return icons[type] || 'pi-file'
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
    groupNotesByPage,
    getNoteTypeLabel,
    getNoteTypeIcon
  }
}
