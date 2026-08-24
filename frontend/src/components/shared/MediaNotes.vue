<template>
  <div
    class="media-notes"
    :class="'media-notes--' + media"
  >
    <div class="notes-header">
      <h3>{{ config.notes.title }}</h3>
      <button
        class="add-note-btn"
        :disabled="!itemId"
        @click="showAddNoteDialog = true"
      >
        <i class="pi pi-plus" /> Nueva Nota
      </button>
    </div>

    <!-- Loading state -->
    <div
      v-if="loading"
      class="loading-state"
    >
      <i class="pi pi-spin pi-spinner" /> Cargando notas...
    </div>

    <!-- Notes list -->
    <div
      v-else-if="sortedNotes.length > 0"
      class="notes-list"
    >
      <div
        v-for="note in sortedNotes"
        :key="note.id"
        class="note-item"
      >
        <div class="note-header">
          <div class="note-meta">
            <i :class="['pi', mediaNotes.getNoteTypeIcon(note.noteType || note.note_type)]" />
            <span class="note-type">{{ mediaNotes.getNoteTypeLabel(note.noteType || note.note_type) }}</span>
            <span
              v-if="config.notes.hasPageNumber"
              class="note-page"
            >
              Página {{ note.pageNumber || note.page_number }}
            </span>
            <span
              v-if="!(note.isPrivate ?? note.is_private)"
              class="note-public"
            >
              <i class="pi pi-eye" /> Pública
            </span>
          </div>
          <div class="note-actions">
            <button
              class="note-action-btn"
              title="Editar"
              aria-label="Editar nota"
              @click="editNote(note)"
            >
              <i
                class="pi pi-pencil"
                aria-hidden="true"
              />
            </button>
            <button
              class="note-action-btn delete"
              title="Eliminar"
              aria-label="Eliminar nota"
              @click="confirmDeleteNote(note)"
            >
              <i
                class="pi pi-trash"
                aria-hidden="true"
              />
            </button>
          </div>
        </div>
        <div
          v-if="note.noteText || note.note_text"
          class="note-text"
        >
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
    <div
      v-else
      class="empty-state"
    >
      <slot name="empty">
        <i :class="config.notes.emptyIcon" />
        <p>No hay notas todavía</p>
        <p class="empty-hint">
          {{ config.notes.emptyHint }}
        </p>
      </slot>
    </div>

    <!-- Add/Edit Note Dialog -->
    <Dialog
      v-model:visible="showNoteDialog"
      :header="editingNote ? 'Editar Nota' : 'Nueva Nota'"
      :modal="true"
      :dismissable-mask="true"
      class="note-dialog"
      :pt="{ mask: { style: 'z-index: 2500' } }"
    >
      <div class="note-form">
        <div
          v-if="config.notes.hasPageNumber"
          class="form-group"
        >
          <label for="pageNumber">Página</label>
          <InputNumber
            id="pageNumber"
            v-model="noteForm.pageNumber"
            :min="1"
            :use-grouping="false"
            placeholder="Número de página"
          />
        </div>

        <div class="form-group">
          <label for="noteType">Tipo de Nota</label>
          <Dropdown
            id="noteType"
            v-model="noteForm.noteType"
            :options="config.notes.types"
            option-label="label"
            option-value="value"
            placeholder="Selecciona un tipo"
            append-to="self"
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
            :loading="saving"
            @click="saveNote"
          />
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, inject } from 'vue'
import { useMediaNotes } from '@/composables/useMediaNotes'
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import Logger from '@/utils/logger'

const props = defineProps({
  media: {
    type: String,
    required: true,
    validator: (value) => mediaKeys.includes(value)
  },
  itemId: {
    type: [Number, String],
    required: true
  }
})

const config = getMediaConfig(props.media)
const mediaNotes = useMediaNotes(props.media)
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

/** El formulario solo lleva `pageNumber` en los medios que lo usan. */
function emptyForm () {
  const form = { noteType: 'note', noteText: '', isPrivate: true }
  if (config.notes.hasPageNumber) form.pageNumber = 1
  return form
}

const noteForm = ref(emptyForm())

const sortedNotes = computed(() => {
  const list = [...mediaNotes.notes.value]

  // Las notas de lectura se leen por página ascendente; las demás, por
  // recencia. Ver `sortBy` en mediaRegistry.
  if (config.notes.sortBy === 'page') {
    return list.sort((a, b) => {
      const pageA = a.pageNumber || a.page_number
      const pageB = b.pageNumber || b.page_number
      if (pageA !== pageB) return pageA - pageB

      const dateA = new Date(a.createdAt || a.created_at)
      const dateB = new Date(b.createdAt || b.created_at)
      return dateA - dateB
    })
  }

  return list.sort((a, b) => {
    const dateA = new Date(a.createdAt || a.created_at)
    const dateB = new Date(b.createdAt || b.created_at)
    return dateB - dateA
  })
})

const loading = computed(() => mediaNotes.loading.value)

/** Los campos propios del medio que viajan en el payload. */
function extraPayload () {
  return config.notes.hasPageNumber ? { pageNumber: noteForm.value.pageNumber } : {}
}

async function loadNotes () {
  if (!props.itemId) {
    Logger.warn('No itemId provided, cannot load notes', { media: props.media })
    return
  }
  Logger.info('Loading notes', { media: props.media, itemId: props.itemId })
  const result = await mediaNotes.getNotes(props.itemId)
  if (!result.success && notifications) {
    notifications.showError(result.error || 'Error al cargar las notas')
  }
}

function editNote (note) {
  editingNote.value = note
  noteForm.value = {
    ...emptyForm(),
    noteType: note.noteType || note.note_type,
    noteText: note.noteText || note.note_text || '',
    isPrivate: note.isPrivate ?? note.is_private ?? true
  }
  if (config.notes.hasPageNumber) {
    noteForm.value.pageNumber = note.pageNumber || note.page_number
  }
  showEditNoteDialog.value = true
}

function confirmDeleteNote (note) {
  if (confirm('¿Estás seguro de que quieres eliminar esta nota?')) {
    deleteNote(note)
  }
}

async function deleteNote (note) {
  const result = await mediaNotes.deleteNote(note.id, props.itemId)
  if (result.success) {
    if (notifications) notifications.showSuccess('Nota eliminada correctamente')
  } else {
    if (notifications) notifications.showError(result.error || 'Error al eliminar la nota')
  }
}

async function saveNote () {
  if (!noteForm.value.noteText.trim()) {
    if (notifications) notifications.showError('El contenido de la nota no puede estar vacío')
    return
  }

  saving.value = true
  try {
    let result
    if (editingNote.value) {
      result = await mediaNotes.updateNote(
        editingNote.value.id,
        props.itemId,
        noteForm.value.noteText,
        noteForm.value.noteType,
        noteForm.value.isPrivate,
        extraPayload()
      )
    } else {
      result = await mediaNotes.addNote(
        props.itemId,
        noteForm.value.noteText,
        noteForm.value.noteType,
        noteForm.value.isPrivate,
        extraPayload()
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
    Logger.error('Error saving note', { media: props.media, error })
    if (notifications) notifications.showError('Error al guardar la nota')
  } finally {
    saving.value = false
  }
}

function closeNoteDialog () {
  showAddNoteDialog.value = false
  showEditNoteDialog.value = false
  editingNote.value = null
  noteForm.value = emptyForm()
}

function formatDate (dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(loadNotes)
watch(() => props.itemId, loadNotes)
</script>

<style scoped lang="scss">
@use '@/assets/styles/components/notes' as *;

// `notes-panel($entity)` se resuelve al compilar —el `$entity` solo alimenta
// `var(--color-card-#{$entity}-accent)`—, así que no se puede parametrizar en
// runtime: se emiten las cinco variantes y `:class` elige la del medio.
.media-notes {
  @include notes-dialog-form;

  &--book  { @include notes-panel('book');  }
  &--movie { @include notes-panel('movie'); }
  &--game  { @include notes-panel('game');  }
  &--album { @include notes-panel('album'); }
  &--video { @include notes-panel('video'); }
}
</style>
