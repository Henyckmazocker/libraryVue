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
        
        <!-- Status Selector Component -->
        <StatusSelector
          v-model="selectedUserStatuses"
          :allowed-statuses="allowedUserStatuses"
          :multiple="true"
          label="Status"
          subtitle="(selecciona uno o más)"
          @status-changed="onStatusesChange"
        />
        
        <!-- Book Actions Component -->
        <BookActions
          :item="book"
          :is-new="!book.userStatuses || book.userStatuses.length === 0"
          :can-save="canSave"
          :can-delete="canDelete"
          :show-edit-button="true"
          :show-update-button="false"
          @save="onSaveBook"
          @delete="onDeleteBook"
          :allowed-statuses="allowedUserStatuses"
          @close="handleBookEditClose"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, ref, computed, watch } from 'vue';
import RatingComponent from '@/components/common/RatingComponent.vue';
import StatusSelector from '@/components/common/StatusSelector.vue';
import BookActions from '@/components/Books/BookActions.vue';
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

const emit = defineEmits(['delete-book', 'update-rating', 'update-statuses', 'save-book']);

// Estados seleccionados (editable)
const getInitialStatuses = () => {
  if (props.book.userStatuses && props.book.userStatuses.length > 0) {
    // Si el libro ya tiene estados, usar esos
    return [...props.book.userStatuses];
  } else {
    // Si es un libro nuevo, establecer "owned" como predeterminado
    return props.allowedUserStatuses.includes('owned') ? ['owned'] : [];
  }
};

const selectedUserStatuses = ref(getInitialStatuses());
const rating = ref(props.book.user_rating);

// Computed properties
const canSave = computed(() => {
  return props.book.title && selectedUserStatuses.value.length > 0;
});

const canDelete = computed(() => {
  return props.book.userStatuses && props.book.userStatuses.length > 0;
});

// Methods
const onRatingChange = (rating) => {
  Logger.debug('Rating changed to:', rating);
  emit('update-rating', { isbn: props.book.isbn, rating });
};

const onStatusesChange = (statuses) => {
  selectedUserStatuses.value = statuses;
  Logger.debug('Statuses changed to:', statuses);
  
  // Si el libro ya está guardado (tiene userStatuses), emitir inmediatamente
  if (props.book.userStatuses && props.book.userStatuses.length > 0) {
    emit('update-statuses', { isbn: props.book.isbn, statuses: [...selectedUserStatuses.value] });
  }
  // Si no está guardado, no emitir nada (esperar a guardar)
};

const onSaveBook = () => {
  if (canSave.value) {
    Logger.debug('Saving book with statuses:', selectedUserStatuses.value);
    emit('save-book', { 
      book: { ...props.book, userStatuses: [...selectedUserStatuses.value] }, 
      statuses: [...selectedUserStatuses.value] 
    });
  }
};

// Actualiza el objeto local al recibir el emit 'close' desde BookActions o MovieActions
const handleBookEditClose = (updatedBook) => {
  if (updatedBook && updatedBook.isbn) {
    selectedUserStatuses.value = updatedBook.userStatuses;
    rating.value = updatedBook.user_rating;
  }
};

const onDeleteBook = () => {
  Logger.debug('Deleting book:', props.book.isbn);
  emit('delete-book', { isbn: props.book.isbn, itemType: 'book' });
};

// Mantener sincronía solo si cambia el ISBN (nuevo libro)
watch(() => props.book.isbn, (newIsbn, oldIsbn) => {
  if (newIsbn !== oldIsbn) {
    selectedUserStatuses.value = getInitialStatuses();
  }
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
</style>
