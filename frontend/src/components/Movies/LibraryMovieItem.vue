<template>
  <div class="library-movie-item-container">
    <div class="movie-details">
      <div class="cover-image-container" v-if="movie.coverUrl">
        <img :src="movie.coverUrl" alt="Movie Poster" class="cover-image" />
      </div>
      <div class="info-text">
        <h3 class="movie-title">{{ movie.title }}</h3>
        <p v-if="movie.originalTitle && movie.originalTitle !== movie.title" class="movie-original-title"><strong>Original Title:</strong> {{ movie.originalTitle }}</p>
        <p v-if="movie.director" class="movie-director"><strong>Director:</strong> {{ movie.director }}</p>
        <p v-if="movie.author" class="movie-author"><strong>Author:</strong> {{ movie.author }}</p>
        <p v-if="movie.year" class="movie-year"><strong>Year:</strong> {{ movie.year }}</p>
        <p class="movie-isbn"><strong>IMDb ID:</strong> {{ movie.isbn }}</p>
        
        <!-- Rating Component -->
        <RatingComponent
          :rating="rating"
          :editable="false"
        />
        
        <!-- Status Selector Component -->
        <StatusSelector
          v-model="selectedUserStatuses"
          :allowed-statuses="allowedUserStatuses"
          :multiple="true"
          :readonly="!isNewMovie"
          :label="isNewMovie ? 'Añadir con estado' : 'Status'"
          :subtitle="isNewMovie ? '' : '(solo lectura - usa el modal para editar)'"
        />
        
        <!-- Direct action buttons -->
        <div class="movie-actions">
          <!-- Save button for new movies -->
          <button 
            v-if="isNewMovie"
            @click="onSaveMovie" 
            :class="['action-button', 'save-button', `save-button--${saveButtonState}`]"
            :disabled="!canSave"
            title="Guardar película"
          >
            <i v-if="saveButtonState === 'idle'" class="fas fa-save"></i>
            <i v-else-if="saveButtonState === 'success'" class="fas fa-check"></i>
            <i v-else-if="saveButtonState === 'error'" class="fas fa-times"></i>
            <span>Guardar</span>
          </button>
          
          <button 
            v-if="!isNewMovie"
            @click="onEditMovie"
            :class="['action-button', 'edit-button', `edit-button--${editButtonState}`]"
            :disabled="editButtonState !== 'idle'"
            title="Editar película"
          >
            <i v-if="editButtonState === 'idle'" class="fas fa-pencil-alt"></i>
            <i v-else-if="editButtonState === 'success'" class="fas fa-check"></i>
            <i v-else-if="editButtonState === 'error'" class="fas fa-times"></i>
            <span>Editar</span>
          </button>
          
          <button 
            v-if="!isNewMovie && canDelete"
            @click="onDeleteMovie"
            class="action-button delete-button"
            title="Eliminar película"
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
import RatingComponent from '@/components/common/RatingComponent.vue';
import StatusSelector from '@/components/common/StatusSelector.vue';
import Logger from '@/utils/logger';

const props = defineProps({
  movie: {
    type: Object,
    required: true,
    default: () => ({ imdbID: '', title: '', author: '', coverUrl: '', rating: null })
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

const emit = defineEmits(['delete-movie', 'save-movie', 'edit-item']);

// Estados seleccionados (editable)
const getInitialStatuses = () => {
  if (props.movie.userStatuses && props.movie.userStatuses.length > 0) {
    // Si la película ya tiene estados, usar esos
    return [...props.movie.userStatuses];
  } else {
    // Si es una película nueva, establecer "owned" como predeterminado
    return props.allowedUserStatuses.includes('owned') ? ['owned'] : [];
  }
};

const selectedUserStatuses = ref(getInitialStatuses());
const rating = ref(props.movie.user_rating || 0);

// Estado del botón de guardar
const saveButtonState = ref('idle'); // 'idle', 'success', 'error'

// Estado del botón de editar
const editButtonState = ref('idle'); // 'idle', 'success', 'error'

// Computed: la película es nueva si NO es editable (editable=true significa que ya existe)
const isNewMovie = computed(() => !props.editable);

// Computed properties
const canDelete = computed(() => {
  return props.editable; // Can only delete if movie exists (editable=true)
});

const canSave = computed(() => {
  return true; // Simplificado por ahora, igual que en LibraryBookItem
});

// Methods
const onDeleteMovie = () => {
  Logger.debug('Deleting movie:', props.movie.isbn);
  emit('delete-movie', { isbn: props.movie.isbn, imdbID: props.movie.isbn, itemType: 'movie' });
};

const onSaveMovie = () => {
  // Emitir evento para guardar la película
  Logger.debug('Saving movie:', props.movie.isbn);
  saveButtonState.value = 'idle'; // Reset state
  emit('save-movie', { movie: props.movie, statuses: selectedUserStatuses.value, itemType: 'movie' });
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

const onEditMovie = () => {
  emit('edit-item', props.movie, 'movie');
};

watch(() => props.movie.user_rating, (newRating) => {
  rating.value = newRating || 0;
});

// Mantener sincronía solo si cambia el IMDb ID (nueva película)
watch(() => props.movie.imdbID, (newId, oldId) => {
  if (newId !== oldId) {
    selectedUserStatuses.value = getInitialStatuses();
  }
});
</script>

<style>
@import '@/assets/styles/variables.css';

/* Igual que .library-book-item-container para altura y aspecto uniforme */
.library-movie-item-container {
  padding: 12px;
  background-color: var(--color-background-mute);
  border-radius: 12px;
  box-shadow: var(--shadow-medium);
  width: auto;
  height: 100%;
  display: flex;
  flex-direction: column;
}

@media (max-width: 480px) {
  .library-movie-item-container {
    width: 100%;
    padding: 10px; /* Reducido para móvil */
  }
  
  .movie-details {
    gap: 10px; /* Reducido para móvil */
  }
  
  .cover-image {
    width: 70px; /* Aún más pequeño en móvil */
  }
  
  .movie-title {
    font-size: 1rem; /* Reducido para móvil */
  }
  
  .movie-author,
  .movie-original-title,
  .movie-director,
  .movie-year,
  .movie-isbn {
    font-size: 0.8rem; /* Aún más pequeño en móvil */
  }
}

.movie-details {
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

.movie-title {
  font-size: 1.1rem; /* Reducido de 1.3rem */
  color: #e0e0e0;
  margin-top: 0;
  margin-bottom: 6px; /* Reducido de 8px */
  line-height: 1.3; /* Mejor espaciado de líneas */
}

.movie-author,
.movie-original-title,
.movie-director,
.movie-year,
.movie-isbn {
  font-size: 0.85rem; /* Reducido de 0.95rem */
  color: #bbb;
  margin-top: 0;
  margin-bottom: 3px; /* Reducido de 4px */
  line-height: 1.2; /* Mejor espaciado */
}

.movie-author strong,
.movie-original-title strong,
.movie-director strong,
.movie-year strong,
.movie-isbn strong {
  font-weight: 500;
  color: #888;
  margin-right: 6px;
}

/* Action buttons styles - restored from original BookActions.vue */
.movie-actions {
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
  background: linear-gradient(135deg, var(--color-secondary), var(--color-secondary-light));
  color: var(--color-text-dark);
  transition: all 0.3s ease;
}

.save-button:hover:not(:disabled) {
  background: linear-gradient(135deg, var(--color-secondary-dark), var(--color-secondary));
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
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-hover));
  color: var(--color-text-light);
  transition: all 0.3s ease;
}

.edit-button:hover:not(:disabled) {
  background: linear-gradient(135deg, var(--color-primary-hover), var(--color-primary-light));
}

.edit-button--success {
  background: linear-gradient(135deg, #28a745, #32cd32) !important;
  animation: pulse-success 0.5s ease;
}

.edit-button--error {
  background: linear-gradient(135deg, #dc3545, #ff6b6b) !important;
  animation: shake 0.5s ease;
}

.delete-button {
  background: linear-gradient(135deg, var(--color-tertiary), var(--color-border));
  color: var(--color-text);
}

.delete-button:hover:not(:disabled) {
  background: linear-gradient(135deg, var(--color-border), var(--color-tertiary));
}

@media (max-width: 768px) {
  .movie-actions {
    flex-direction: column;
    gap: 8px;
  }
  
  .action-button {
    width: 100%;
    justify-content: center;
  }
}
</style>
