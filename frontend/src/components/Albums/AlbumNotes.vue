<template>
  <div class="album-notes">
    <div class="notes-header">
      <h3>Notas del Álbum</h3>
      <button
        class="add-note-btn"
        @click="showAddNoteDialog = true"
        :disabled="!albumId"
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
            <i :class="['pi', albumNotes.getNoteTypeIcon(note.noteType || note.note_type)]"></i>
            <span class="note-type">{{ albumNotes.getNoteTypeLabel(note.noteType || note.note_type) }}</span>
            <span v-if="!(note.isPrivate ?? note.is_private)" class="note-public">
              <i class="pi pi-eye"></i> Pública
            </span>
          </div>
          <div class="note-actions">
            <button class="note-action-btn" @click="editNote(note)" title="Editar">
              <i class="pi pi-pencil"></i>
            </button>
            <button class="note-action-btn delete" @click="confirmDeleteNote(note)" title="Eliminar">
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
          <span
            v-if="(note.updatedAt || note.updated_at) !== (note.createdAt || note.created_at)"
            class="note-updated"
          >
            (editado {{ formatDate(note.updatedAt || note.updated_at) }})
          </span>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="empty-state">
      <i class="pi pi-music"></i>
      <p>No hay notas todavía</p>
      <p class="empty-hint">Agrega notas para recordar tus opiniones sobre este álbum</p>
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
          <Button label="Cancelar" severity="secondary" @click="closeNoteDialog" />
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
import { useAlbumNotes } from '@/composables/useAlbumNotes'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Logger from '@/utils/logger'

const props = defineProps({
  albumId: {
    type: Number,
    required: true
  }
})

const albumNotes = useAlbumNotes()
const notifications = inject('notifications', null)

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

const sortedNotes = computed(() => {
  return [...albumNotes.notes.value].sort((a, b) => {
    const dateA = new Date(a.createdAt || a.created_at)
    const dateB = new Date(b.createdAt || b.created_at)
    return dateB - dateA
  })
})

const loading = computed(() => albumNotes.loading.value)

async function loadNotes() {
  if (!props.albumId) {
    Logger.warn('No albumId provided, cannot load notes')
    return
  }
  Logger.info('Loading notes for album', { albumId: props.albumId })
  const result = await albumNotes.getNotes(props.albumId)
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
  const result = await albumNotes.deleteNote(note.id, props.albumId)
  if (result.success) {
    if (notifications) notifications.showSuccess('Nota eliminada correctamente')
  } else {
    if (notifications) notifications.showError(result.error || 'Error al eliminar la nota')
  }
}

async function saveNote() {
  if (!noteForm.value.noteText.trim()) {
    if (notifications) notifications.showError('El contenido de la nota no puede estar vacío')
    return
  }

  saving.value = true
  try {
    let result
    if (editingNote.value) {
      result = await albumNotes.updateNote(
        editingNote.value.id,
        props.albumId,
        noteForm.value.noteText,
        noteForm.value.noteType,
        noteForm.value.isPrivate
      )
    } else {
      result = await albumNotes.addNote(
        props.albumId,
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
      if (notifications) notifications.showError(result.error || 'Error al guardar la nota')
    }
  } catch (error) {
    Logger.error('Error saving note', { error })
    if (notifications) notifications.showError('Error al guardar la nota')
  } finally {
    saving.value = false
  }
}

function closeNoteDialog() {
  showAddNoteDialog.value = false
  showEditNoteDialog.value = false
  editingNote.value = null
  noteForm.value = { noteType: 'note', noteText: '', isPrivate: true }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(loadNotes)
watch(() => props.albumId, loadNotes)
</script>

<style scoped>
.album-notes {
  width: 100%;
}

.notes-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.notes-header h3 {
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
}

.add-note-btn {
  background: var(--primary-color, #1D4E4A);
  color: white;
  border: none;
  border-radius: 6px;
  padding: 6px 12px;
  font-size: 0.8rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  transition: opacity 0.2s;
}

.add-note-btn:hover:not(:disabled) { opacity: 0.85; }
.add-note-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.loading-state {
  text-align: center;
  padding: 20px;
  color: var(--text-color-secondary, #9ca3af);
}

.notes-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.note-item {
  background: var(--surface-card, #2a2d36);
  border-radius: 8px;
  padding: 12px 14px;
  border-left: 3px solid var(--primary-color, #1D4E4A);
}

.note-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.note-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  color: var(--text-color-secondary, #9ca3af);
}

.note-type { font-weight: 600; }

.note-public {
  color: #4ade80;
  display: flex;
  align-items: center;
  gap: 3px;
}

.note-actions {
  display: flex;
  gap: 6px;
}

.note-action-btn {
  background: transparent;
  border: none;
  color: var(--text-color-secondary, #9ca3af);
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  font-size: 0.8rem;
  transition: color 0.2s;
}

.note-action-btn:hover { color: var(--text-color, #e0e0e0); }
.note-action-btn.delete:hover { color: #f87171; }

.note-text {
  font-size: 0.88rem;
  line-height: 1.5;
  color: var(--text-color, #e0e0e0);
  margin-bottom: 8px;
  white-space: pre-wrap;
}

.note-footer {
  font-size: 0.72rem;
  color: var(--text-color-secondary, #9ca3af);
  display: flex;
  gap: 8px;
}

.empty-state {
  text-align: center;
  padding: 30px 20px;
  color: var(--text-color-secondary, #9ca3af);
}

.empty-state i { font-size: 2rem; margin-bottom: 8px; display: block; }
.empty-hint { font-size: 0.78rem; margin-top: 4px; }

.note-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 320px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.form-group label { font-size: 0.85rem; font-weight: 500; }

.form-group.checkbox {
  flex-direction: row;
  align-items: center;
  gap: 8px;
}

.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 4px;
}
</style>
