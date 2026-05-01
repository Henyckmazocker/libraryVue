<template>
  <div class="library-book-item-container">
    <div class="book-details">
      <div class="cover-image-container" v-if="book.coverUrl">
        <img :src="book.coverUrl" alt="Book Cover" class="cover-image" />
      </div>
      <div class="info-text">
        <h3 class="book-title">{{ book.title }}</h3>
        <p v-if="book.author" class="book-author"><strong>Author:</strong> {{ book.author }}</p>
        <p v-if="book.publishers && Array.isArray(book.publishers) && book.publishers.length > 0" class="book-publisher">
          <strong>Editorial:</strong> {{ book.publishers.join(', ') }}
        </p>
        <p v-else-if="book.publisher" class="book-publisher">
          <strong>Editorial:</strong> {{ book.publisher }}
        </p>
        <p v-if="book.publicationDate" class="book-publication-date"><strong>Publication Date:</strong> {{ book.publicationDate }}</p>
        
        <!-- Rating Component -->
        <RatingComponent
          :rating="rating"
          :editable="false"
        />
        
        <!-- Reading Progress Bar -->
        <ReadingProgressBar
          :current-page="currentPage"
          :total-pages="book.pages || 0"
          :editable="false"
          theme="blue"
        />
        
        <!-- Status Selector Component -->
        <StatusSelector
          v-model="selectedUserStatuses"
          :allowed-statuses="allowedUserStatuses"
          :multiple="true"
          :readonly="!isNewBook"
          :label="isNewBook ? 'Añadir con estado' : 'Status'"
          :subtitle="isNewBook ? '' : '(solo lectura - usa el modal para editar)'"
        />
        
        <!-- Reading Status Widget -->
        <ReadingStatusWidget
          v-if="!isNewBook"
          :book="book"
        />

        <p v-if="ownershipFormatLabel" class="book-field"><strong>Formato:</strong> <span class="ownership-format-badge">{{ ownershipFormatLabel }}</span></p>

        <!-- Book Actions Component -->
        <div class="book-actions">

          <!-- Save button for new books -->
          <button
            v-if="isNewBook"
            @click="onSaveBook"
            :class="['action-button', 'save-button', `save-button--${saveButtonState}`]"
            :disabled="!canSave"
            title="Guardar libro"
          >
            <i v-if="saveButtonState === 'idle'" class="fas fa-save"></i>
            <i v-else-if="saveButtonState === 'success'" class="fas fa-check"></i>
            <i v-else-if="saveButtonState === 'error'" class="fas fa-times"></i>
            <span>Guardar</span>
          </button>

          <!-- View History button -->
          <button
            v-if="!isNewBook"
            @click="onShowHistory"
            class="action-button history-button"
            title="Ver historial de lectura"
          >
            <i class="fas fa-history"></i>
            <span>Historial</span>
          </button>

          <!-- Edit button -->
          <button
            v-if="!isNewBook"
            @click="onEditBook"
            :class="['action-button', 'edit-button', `edit-button--${editButtonState}`]"
            :disabled="editButtonState !== 'idle'"
            title="Editar libro"
          >
            <i v-if="editButtonState === 'idle'" class="fas fa-pencil-alt"></i>
            <i v-else-if="editButtonState === 'success'" class="fas fa-check"></i>
            <i v-else-if="editButtonState === 'error'" class="fas fa-times"></i>
            <span>Editar</span>
          </button>

          <!-- Delete button -->
          <button
            v-if="!isNewBook && canDelete"
            @click="onDeleteBook"
            class="action-button delete-button"
            title="Eliminar libro"
          >
            <i class="fas fa-trash"></i>
            <span>Eliminar</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, defineExpose, ref, computed, watch } from 'vue';
import ReadingProgressBar from '@/components/common/ReadingProgressBar.vue';
import RatingComponent from '@/components/common/RatingComponent.vue';
import StatusSelector from '@/components/common/StatusSelector.vue';
import ReadingStatusWidget from '@/components/Books/ReadingStatusWidget.vue';
import Logger from '@/utils/logger';

const props = defineProps({
  book: {
    type: Object,
    required: true,
    default: () => ({ isbn: "", title: "", author: "", coverUrl: "", rating: null })
  },
  allowedUserStatuses: {
    type: Array,
    required: true
  },
  editable: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['delete-book', 'edit-item', 'save-book', 'show-session-history']);

// Estados seleccionados (locales para display)
const getInitialStatuses = () => {
  if (props.book.userStatuses && props.book.userStatuses.length > 0) {
    return [...props.book.userStatuses];
  } else {
    return props.allowedUserStatuses.includes('owned') ? ['owned'] : [];
  }
};
const selectedUserStatuses = ref(getInitialStatuses());
const rating = ref(props.book.user_rating || 0);
const currentPage = ref(props.book.currentPage || 0);

// Log inicial para debug
Logger.debug('[LibraryBookItem] Component initialized with:', {
  bookIsbn: props.book.isbn,
  bookTitle: props.book.title,
  userStatuses: props.book.userStatuses,
  user_rating: props.book.user_rating,
  currentPage: props.book.currentPage,
  allowedUserStatuses: props.allowedUserStatuses,
  editable: props.editable
});

// Estado del botón de guardar
const saveButtonState = ref('idle'); // 'idle', 'success', 'error'

// Estado del botón de editar
const editButtonState = ref('idle'); // 'idle', 'success', 'error'

// Computed: el libro es nuevo si NO es editable (editable=true significa que ya existe)
const isNewBook = computed(() => !props.editable);

const canDelete = computed(() => {
  return props.editable; // Can only delete if book exists (editable=true)
});

const canSave = computed(() => {
  return true; // Simplificado por ahora
});

// Methods
const onSaveBook = () => {
  Logger.debug('Saving book:', props.book.isbn);
  saveButtonState.value = 'idle';
  emit('save-book', { book: props.book, statuses: selectedUserStatuses.value, itemType: 'book' });
};

const onEditBook = () => {
  emit('edit-item', props.book, 'book');
};

// Métodos públicos para actualizar el estado del botón
const setSaveSuccess = () => {
  saveButtonState.value = 'success';
  setTimeout(() => {
    saveButtonState.value = 'idle';
  }, 2000);
};

const setSaveError = () => {
  saveButtonState.value = 'error';
  setTimeout(() => {
    saveButtonState.value = 'idle';
  }, 2000);
};

const setEditSuccess = () => {
  editButtonState.value = 'success';
  setTimeout(() => {
    editButtonState.value = 'idle';
  }, 2000);
};

const setEditError = () => {
  editButtonState.value = 'error';
  setTimeout(() => {
    editButtonState.value = 'idle';
  }, 2000);
};

// Exponer métodos al componente padre
defineExpose({
  setSaveSuccess,
  setSaveError,
  setEditSuccess,
  setEditError
});

const onShowHistory = () => {
  Logger.debug('[LibraryBookItem] Showing session history for book:', props.book.isbn);
  emit('show-session-history', { book: props.book });
};

const onDeleteBook = () => {
  Logger.debug('Deleting book:', props.book.isbn);
  emit('delete-book', { isbn: props.book.isbn, itemType: 'book' });
};

// ===================================
// HANDLERS DE EVENTOS DEL WIDGET DE SESIONES
// ===================================

watch(() => props.book.user_rating, (newRating) => {
  Logger.debug('[LibraryBookItem] user_rating changed:', { old: rating.value, new: newRating });
  rating.value = newRating || 0;
});

watch(() => props.book.currentPage, (newPage) => {
  Logger.debug('[LibraryBookItem] currentPage changed:', { old: currentPage.value, new: newPage });
  currentPage.value = newPage || 0;
});

watch(() => props.book.userStatuses, (newStatuses) => {
  if (props.editable) {
    // Libro existente: sincronizar siempre (incluso array vacío cuando se eliminan todos)
    selectedUserStatuses.value = Array.isArray(newStatuses) ? [...newStatuses] : [];
  } else if (newStatuses && newStatuses.length > 0) {
    // Libro nuevo: solo sobrescribir si llegan estados; preservar default 'owned' si vacío
    selectedUserStatuses.value = [...newStatuses];
  }
}, { deep: true });


const ownershipFormatLabel = ref(
  props.book.ownershipFormat?.label ?? props.book.ownership_format?.label ?? ''
)
watch(() => [props.book.ownershipFormat, props.book.ownership_format], ([fmt1, fmt2]) => {
  ownershipFormatLabel.value = fmt1?.label ?? fmt2?.label ?? ''
}, { immediate: true, deep: true })
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/library-item' as *;

.library-book-item-container {
  @include library-item('book', '2/3', 80px, 'book');
}

// ─── Sesiones de lectura (específico de Book) ─────────────────────────
.session-info {
  margin: spacing(sm) 0;
  padding: spacing(xs);
  background-color: rgba(40, 167, 69, 0.15);
  border-radius: radius(md);
  border-left: 4px solid #28a745;
}

.session-badge {
  display: flex;
  align-items: center;
  gap: spacing(xs);
  margin-bottom: spacing(xs);
  font-size: var(--font-size-sm);
  color: var(--color-text);

  i { color: #28a745; }
}

.session-actions,
.session-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: spacing(xs);
  margin-bottom: spacing(xs);
}
</style>
