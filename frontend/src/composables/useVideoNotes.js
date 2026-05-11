import { ref } from 'vue'
import { useAuthStore } from '@/store/auth'
import Logger from '@/utils/logger'

/**
 * Composable for managing video notes
 * Provides functions to add, update, delete and fetch notes for videos
 */
export function useVideoNotes() {
  const authStore = useAuthStore()
  const notes = ref([])
  const loading = ref(false)
  const error = ref(null)

  async function getNotes(youtubeId, noteType = null) {
    loading.value = true
    error.value = null

    try {
      const payload = { youtubeId }
      if (noteType) payload.noteType = noteType

      const response = await authStore.authenticatedApiCall('get_video_notes', payload)

      if (response.data && response.data.status === 'success') {
        notes.value = response.data.data || []
        Logger.info('Video notes loaded', { count: notes.value.length })
        return { success: true, data: notes.value }
      } else {
        error.value = response.data?.message || 'Failed to load notes'
        Logger.error('Failed to load video notes', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error loading notes'
      Logger.error('Error loading video notes', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  async function addNote(youtubeId, noteText, noteType = 'note', isPrivate = true) {
    loading.value = true
    error.value = null

    try {
      const response = await authStore.authenticatedApiCall('add_video_note', {
        youtubeId,
        noteText,
        noteType,
        isPrivate
      })

      if (response.data && response.data.status === 'success') {
        Logger.info('Video note added', { youtubeId, noteType })
        await getNotes(youtubeId)
        return { success: true, data: response.data.data }
      } else {
        error.value = response.data?.message || 'Failed to add note'
        Logger.error('Failed to add video note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error adding note'
      Logger.error('Error adding video note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  async function updateNote(noteId, youtubeId, noteText, noteType, isPrivate) {
    loading.value = true
    error.value = null

    try {
      const response = await authStore.authenticatedApiCall('update_video_note', {
        noteId,
        noteText,
        noteType,
        isPrivate
      })

      if (response.data && response.data.status === 'success') {
        Logger.info('Video note updated', { noteId })
        await getNotes(youtubeId)
        return { success: true }
      } else {
        error.value = response.data?.message || 'Failed to update note'
        Logger.error('Failed to update video note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error updating note'
      Logger.error('Error updating video note', { error: err })
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  async function deleteNote(noteId, youtubeId) {
    loading.value = true
    error.value = null

    try {
      const response = await authStore.authenticatedApiCall('delete_video_note', { noteId })

      if (response.data && response.data.status === 'success') {
        Logger.info('Video note deleted', { noteId })
        await getNotes(youtubeId)
        return { success: true }
      } else {
        error.value = response.data?.message || 'Failed to delete note'
        Logger.error('Failed to delete video note', { error: error.value })
        return { success: false, error: error.value }
      }
    } catch (err) {
      error.value = err.message || 'Error deleting note'
      Logger.error('Error deleting video note', { error: err })
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
