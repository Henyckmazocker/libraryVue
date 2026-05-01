<template>
  <div class="movie-notes">
    <div class="notes-header">
      <h3>Notas de la Película</h3>
      <button 
        class="add-note-btn" 
        @click="showAddNoteDialog = true"
        :disabled="!movieIsbn"
      >
        <i class="pi pi-plus"></i> Nueva Nota
      </button>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="loading-state">
      <i class="pi pi-spin pi-spinner"></i> Cargando notas...
    </div>

    <!-- Notes list -->
    <div v-else-if="sortedNotes.length > 0" class="notes-list">
      <div 
        v-for="note in sortedNotes" 
        :key="note.id" 
        class="note-item"
      >
        <div class="note-header">
          <div class="note-meta">
            <i :class="['pi', movieNotes.getNoteTypeIcon(note.noteType || note.note_type)]"></i>
            <span class="note-type">{{ movieNotes.getNoteTypeLabel(note.noteType || note.note_type) }}</span>
            <span v-if="!(note.isPrivate ?? note.is_private)" class="note-public">
              <i class="pi pi-eye"></i> Pública
            </span>
          </div>
          <div class="note-actions">
            <button 
              class="note-action-btn" 
              @click="editNote(note)" 
              title="Editar"
            >
              <i class="pi pi-pencil"></i>
            </button>
            <button 
              class="note-action-btn delete" 
              @click="confirmDeleteNote(note)" 
              title="Eliminar"
            >
              <i class="pi pi-trash"></i>
            </button>
          </div>
        </div>
        <div v-if="note.noteText || note.note_text" class="note-text">
          {{ note.noteText || note.note_text }}
        </div>
        <div class="note-footer">
          <span class="note-date">
            {{ formatDate(note.createdAt || note.created_at) }}
          </span>
          <span v-if="(note.updatedAt || note.updated_at) !== (note.createdAt || note.created_at)" class="note-updated">
            (editado {{ formatDate(note.updatedAt || note.updated_at) }})
          </span>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="empty-state">
      <i class="pi pi-video"></i>
      <p>No hay notas todavía</p>
      <p class="empty-hint">Agrega notas para recordar tus opiniones sobre esta película</p>
    </div>

    <!-- Add/Edit Note Dialog -->
    <Dialog 
      v-model:visible="showNoteDialog" 
      :header="editingNote ? 'Editar Nota' : 'Nueva Nota'"
      :modal="true"
      :dismissableMask="true"
      class="note-dialog"
      :pt="{ mask: { style: 'z-index: 2500' } }"
    >
      <div class="note-form">
        <div class="form-group">
          <label for="noteType">Tipo de Nota</label>
          <Dropdown 
            id="noteType"
            v-model="noteForm.noteType" 
            :options="noteTypes"
            optionLabel="label"
            optionValue="value"
            placeholder="Selecciona un tipo"
            appendTo="self"
          />
        </div>

        <div class="form-group">
          <label for="noteText">Contenido</label>
          <Textarea 
            id="noteText"
            v-model="noteForm.noteText" 
            rows="5"
            placeholder="Escribe tu nota aquí..."
          />
        </div>

        <div class="form-group checkbox">
          <Checkbox 
            id="isPrivate"
            v-model="noteForm.isPrivate" 
            :binary="true"
          />
          <label for="isPrivate">Nota privada</label>
        </div>

        <div class="dialog-actions">
          <Button 
            label="Cancelar" 
            severity="secondary"
            @click="closeNoteDialog"
          />
          <Button 
            :label="editingNote ? 'Actualizar' : 'Guardar'" 
            @click="saveNote"
            :loading="saving"
          />
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, inject } from 'vue'
import { useMovieNotes } from '@/composables/useMovieNotes'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Logger from '@/utils/logger'

const props = defineProps({
  movieIsbn: {
    type: String,
    required: true
  }
})

// Composables
const movieNotes = useMovieNotes()
const notifications = inject('notifications', null)

// State
const showNoteDialog = computed({
  get: () => showAddNoteDialog.value || showEditNoteDialog.value,
  set: (val) => {
    showAddNoteDialog.value = val
    showEditNoteDialog.value = val
  }
})
const showAddNoteDialog = ref(false)
const showEditNoteDialog = ref(false)
const editingNote = ref(null)
const saving = ref(false)

const noteForm = ref({
  noteType: 'note',
  noteText: '',
  isPrivate: true
})

const noteTypes = [
  { label: 'Nota', value: 'note' },
  { label: 'Reseña', value: 'review' },
  { label: 'Pensamiento', value: 'thought' }
]

// Computed
const sortedNotes = computed(() => {
  return [...movieNotes.notes.value].sort((a, b) => {
    const dateA = new Date(a.createdAt || a.created_at)
    const dateB = new Date(b.createdAt || b.created_at)
    return dateB - dateA // Most recent first
  })
})

const loading = computed(() => movieNotes.loading.value)

// Methods
async function loadNotes() {
  if (!props.movieIsbn) {
    Logger.warn('No movieIsbn provided, cannot load notes')
    return
  }
  
  Logger.info('Loading notes for movie', { movieIsbn: props.movieIsbn })
  const result = await movieNotes.getNotes(props.movieIsbn)
  if (!result.success && notifications) {
    notifications.showError(result.error || 'Error al cargar las notas')
  }
}

function editNote(note) {
  editingNote.value = note
  noteForm.value = {
    noteType: note.noteType || note.note_type,
    noteText: note.noteText || note.note_text || '',
    isPrivate: note.isPrivate ?? note.is_private ?? true
  }
  showEditNoteDialog.value = true
}

function confirmDeleteNote(note) {
  if (confirm('¿Estás seguro de que quieres eliminar esta nota?')) {
    deleteNote(note)
  }
}

async function deleteNote(note) {
  const result = await movieNotes.deleteNote(note.id, props.movieIsbn)
  if (result.success) {
    if (notifications) {
      notifications.showSuccess('Nota eliminada correctamente')
    }
  } else {
    if (notifications) {
      notifications.showError(result.error || 'Error al eliminar la nota')
    }
  }
}

async function saveNote() {
  if (!noteForm.value.noteText.trim()) {
    if (notifications) {
      notifications.showError('El contenido de la nota no puede estar vacío')
    }
    return
  }

  saving.value = true

  try {
    let result
    if (editingNote.value) {
      // Update existing note
      result = await movieNotes.updateNote(
        editingNote.value.id,
        props.movieIsbn,
        noteForm.value.noteText,
        noteForm.value.noteType,
        noteForm.value.isPrivate
      )
    } else {
      // Add new note
      result = await movieNotes.addNote(
        props.movieIsbn,
        noteForm.value.noteText,
        noteForm.value.noteType,
        noteForm.value.isPrivate
      )
    }

    if (result.success) {
      if (notifications) {
        notifications.showSuccess(
          editingNote.value ? 'Nota actualizada correctamente' : 'Nota agregada correctamente'
        )
      }
      closeNoteDialog()
    } else {
      if (notifications) {
        notifications.showError(result.error || 'Error al guardar la nota')
      }
    }
  } catch (error) {
    Logger.error('Error saving note', { error })
    if (notifications) {
      notifications.showError('Error al guardar la nota')
    }
  } finally {
    saving.value = false
  }
}

function closeNoteDialog() {
  showNoteDialog.value = false
  editingNote.value = null
  noteForm.value = {
    noteType: 'note',
    noteText: '',
    isPrivate: true
  }
}

function formatDate(dateString) {
  if (!dateString) return ''
  
  const date = new Date(dateString)
  const now = new Date()
  const diffTime = Math.abs(now - date)
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  
  if (diffDays === 0) return 'Hoy'
  if (diffDays === 1) return 'Ayer'
  if (diffDays < 7) return `Hace ${diffDays} días`
  
  return date.toLocaleDateString('es-ES', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

// Watch for movieIsbn changes
watch(() => props.movieIsbn, (newIsbn) => {
  if (newIsbn) {
    loadNotes()
  }
}, { immediate: true })

// Load notes on mount
onMounted(() => {
  if (props.movieIsbn) {
    loadNotes()
  }
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/components/notes' as *;

.movie-notes {
  @include notes-panel('movie');
  @include notes-dialog-form;
}
</style>

