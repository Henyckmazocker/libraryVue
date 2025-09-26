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
          :editable="editable"
          @rating-changed="onRatingChange"
        />
        
        <!-- Reading Progress Bar -->
        <ReadingProgressBar
          :current-page="currentPage"
          :total-pages="book.pages || 0"
          :editable="!readonly"
          theme="blue"
          @update-progress="onUpdateProgress"
        />
        
        <!-- Status Selector Component -->
        <StatusSelector
          v-model="selectedUserStatuses"
          :allowed-statuses="allowedUserStatuses"
          :multiple="true"
          :readonly="readonly"
          label="Status"
          :subtitle="readonly ? '(solo lectura - usa el modal para editar)' : 'Selecciona estados'"
          @status-changed="onStatusesChange"
        />
        
        <!-- Book Actions Component -->
        <div class="book-actions">
          <!-- Save button for new books -->
          <button 
            v-if="isNewBook"
            @click="onSaveBook" 
            class="action-button save-button" 
            :disabled="!canSave"
            title="Guardar libro"
          >
            <i class="fas fa-save"></i>
            <span>Guardar</span>
          </button>
          
          <!-- Edit button -->
          <button 
            v-if="!isNewBook"
            @click="onEditBook" 
            class="action-button edit-button" 
            title="Editar libro"
          >
            <i class="fas fa-pencil-alt"></i>
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
import { defineProps, defineEmits, ref, computed, watch } from 'vue';
import ReadingProgressBar from '@/components/common/ReadingProgressBar.vue';
import RatingComponent from '@/components/common/RatingComponent.vue';
import StatusSelector from '@/components/common/StatusSelector.vue';
import { useBooks } from '@/composables/useBooks';
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
  },
  readonly: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['delete-book', 'update-progress', 'edit-item', 'update-rating', 'update-statuses', 'save-book']);

// Composables
const { updateReadingProgress } = useBooks();

// Estados seleccionados (locales para display)
const selectedUserStatuses = ref(props.book.userStatuses || []);
const rating = ref(props.book.user_rating || 0);
const currentPage = ref(props.book.currentPage || 0);

// Capturar si es nuevo al inicializar (no reactivo)
const isNewBook = ref(!props.book.userStatuses || props.book.userStatuses.length === 0);

const canDelete = computed(() => {
  return props.book.userStatuses && props.book.userStatuses.length > 0;
});

const canSave = computed(() => {
  return true; // Simplificado por ahora
});

// Methods
const onRatingChange = (newRating) => {
  rating.value = newRating;
  Logger.debug('Rating changed:', { isbn: props.book.isbn, rating: newRating });
  emit('update-rating', { isbn: props.book.isbn, rating: newRating, itemType: 'book' });
};

const onStatusesChange = (newStatuses) => {
  selectedUserStatuses.value = newStatuses;
  Logger.debug('Statuses changed:', { isbn: props.book.isbn, statuses: newStatuses });
  emit('update-statuses', { isbn: props.book.isbn, statuses: newStatuses, itemType: 'book' });
};

const onSaveBook = () => {
  // Emitir evento para guardar el libro
  Logger.debug('Saving book:', props.book.isbn);
  emit('save-book', { book: props.book, statuses: selectedUserStatuses.value, itemType: 'book' });
};

// Methods
const onEditBook = () => {
  emit('edit-item', props.book, 'book');
};

const onDeleteBook = () => {
  Logger.debug('Deleting book:', props.book.isbn);
  emit('delete-book', { isbn: props.book.isbn, itemType: 'book' });
};

// Maneja la actualización del progreso de lectura
const onUpdateProgress = async (currentPageValue) => {
  try {
    Logger.debug('Updating reading progress:', { isbn: props.book.isbn, currentPage: currentPageValue });
    const result = await updateReadingProgress(props.book.isbn, currentPageValue);
    currentPage.value = currentPageValue; // Actualiza el valor local
    
    // Emite evento para que el componente padre actualice el libro
    emit('update-progress', { 
      isbn: props.book.isbn, 
      updates: { currentPage: currentPageValue } 
    });
    
    if (!result.success) {
      Logger.error('Error updating reading progress:', result.message);
    }
  } catch (error) {
    Logger.error('Error updating reading progress:', error);
  }
};

watch(() => props.book.user_rating, (newRating) => {
  rating.value = newRating || 0;
});

watch(() => props.book.currentPage, (newPage) => {
  currentPage.value = newPage || 0;
});
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
}

.save-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #20c997, #17a2b8);
}

.edit-button {
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white;
}

.edit-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #0056b3, #004085);
}

.delete-button {
  background: linear-gradient(135deg, #dc3545, #c82333);
  color: white;
}

.delete-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #c82333, #bd2130);
}

@media (max-width: 768px) {
  .book-actions {
    flex-direction: column;
    gap: 8px;
  }
  
  .action-button {
    width: 100%;
    justify-content: center;
  }
}
</style>
