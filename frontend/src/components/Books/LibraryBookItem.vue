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
  if (newStatuses && newStatuses.length > 0) {
    // Libro existente recargado con sus estados reales
    selectedUserStatuses.value = [...newStatuses];
  }
  // Si llega vacío, no sobreescribir la selección actual (ej. default 'owned')
}, { deep: true });


const ownershipFormatLabel = ref(
  props.book.ownershipFormat?.label ?? props.book.ownership_format?.label ?? ''
)
watch(() => [props.book.ownershipFormat, props.book.ownership_format], ([fmt1, fmt2]) => {
  ownershipFormatLabel.value = fmt1?.label ?? fmt2?.label ?? ''
}, { immediate: true, deep: true })
</script>

<style>


.library-book-item-container {
  padding: 12px; /* Reducido de 20px */
  background-color: #2c2c2c;
  border-radius: 12px; /* Reducido de 15px */
  box-shadow: 0 3px 8px rgba(0,0,0,0.25); /* Sombra más sutil */
  width: auto;
  height: 100%;
  display: flex;
  flex-direction: column;
}

@media (max-width: 480px) {
  .library-book-item-container {
    width: 100%;
    padding: 10px; /* Reducido para móvil */
  }
  
  .book-details {
    gap: 10px; /* Reducido para móvil */
  }
  
  .cover-image {
    width: 70px; /* Aún más pequeño en móvil */
  }
  
  .book-title {
    font-size: 1rem; /* Reducido para móvil */
  }
  
  .book-author,
  .book-publisher,
  .book-isbn,
  .book-publication-date {
    font-size: 0.8rem; /* Aún más pequeño en móvil */
  }
}

.book-details {
  display: flex;
  align-items: flex-start;
  gap: 12px; /* Reducido de 20px */
}

.cover-image-container {
  flex-shrink: 0;
}

.cover-image {
  width: 80px; /* Reducido de 100px */
  height: auto;
  border-radius: 6px; /* Reducido de 8px */
  border: 1px solid #444;
}

.info-text {
  text-align: left;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
}

.book-title {
  font-size: 1.1rem; /* Reducido de 1.3rem */
  color: #e0e0e0;
  margin-top: 0;
  margin-bottom: 6px; /* Reducido de 8px */
  line-height: 1.3; /* Mejor espaciado de líneas */
}


.book-author,
.book-publisher,
.book-isbn,
.book-publication-date {
  font-size: 0.85rem; /* Reducido de 0.95rem */
  color: #bbb;
  margin-top: 0;
  margin-bottom: 3px; /* Reducido de 4px */
  line-height: 1.2; /* Mejor espaciado */
}

.book-author strong,
.book-isbn strong {
  font-weight: 500;
  color: #888;
  margin-right: 6px;
}

.rating-section {
  margin-top: 10px;
  margin-bottom: 10px;
}

.current-rating {
  font-size: 0.9em;
  color: #ccc;
  margin-bottom: 5px;
}

/* Action buttons styles - restored from original BookActions.vue */
.book-actions {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-top: 15px;
}

.action-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 15px;
  font-size: 0.9rem;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 500;
  min-width: auto;
  justify-content: center;
}

.action-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.action-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Button types with gradients */
.save-button {
  background: linear-gradient(135deg, #28a745, #20c997);
  color: white;
  transition: all 0.3s ease;
}

.save-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #20c997, #17a2b8);
}

.save-button--success {
  background: linear-gradient(135deg, #28a745, #32cd32) !important;
  animation: pulse-success 0.5s ease;
}

.save-button--error {
  background: linear-gradient(135deg, #dc3545, #ff6b6b) !important;
  animation: shake 0.5s ease;
}

@keyframes pulse-success {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-5px); }
  75% { transform: translateX(5px); }
}

.edit-button {
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white;
  transition: all 0.3s ease;
}

.edit-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #0056b3, #004085);
}

.edit-button--success {
  background: linear-gradient(135deg, #28a745, #32cd32) !important;
  animation: pulse-success 0.5s ease;
}

.edit-button--error {
  background: linear-gradient(135deg, #dc3545, #ff6b6b) !important;
  animation: shake 0.5s ease;
}

.history-button {
  background: linear-gradient(135deg, #6c757d, #5a6268);
  color: white;
  transition: all 0.3s ease;
}

.history-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #5a6268, #495057);
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.delete-button {
  background: linear-gradient(135deg, #dc3545, #c82333);
  color: white;
}

.delete-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #c82333, #bd2130);
}

/* Estilos para sesiones de lectura */
.session-info {
  margin: 12px 0;
  padding: 10px;
  background-color: #1a472a;
  border-radius: 8px;
  border-left: 4px solid #28a745;
}

.session-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  font-size: 14px;
  color: #d4edda;
}

.session-badge i {
  color: #28a745;
}

.session-actions {
  display: flex;
  gap: 8px;
}

.session-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
}

.btn {
  padding: 6px 12px;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-sm {
  padding: 4px 8px;
  font-size: 11px;
}

.btn-primary {
  background-color: #007bff;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #0056b3;
}

.btn-success {
  background-color: #28a745;
  color: white;
}

.btn-success:hover:not(:disabled) {
  background-color: #1e7e34;
}

.btn-warning {
  background-color: #ffc107;
  color: #212529;
}

.btn-warning:hover:not(:disabled) {
  background-color: #e0a800;
}

.btn-info {
  background-color: #17a2b8;
  color: white;
}

.btn-info:hover:not(:disabled) {
  background-color: #138496;
}

.btn-secondary {
  background-color: #6c757d;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background-color: #5a6268;
}

@media (max-width: 768px) {
  .book-actions {
    flex-direction: column;
    gap: 8px;
  }
  
  .session-buttons {
    justify-content: center;
  }
  
  .session-actions {
    justify-content: center;
  }
  
  .action-button {
    width: 100%;
    justify-content: center;
  }
}
</style>
