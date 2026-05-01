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
        <p v-if="ownershipFormatLabel" class="movie-field"><strong>Formato:</strong> <span class="ownership-format-badge">{{ ownershipFormatLabel }}</span></p>
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

const ownershipFormatLabel = ref(
  props.movie.ownershipFormat?.label ?? props.movie.ownership_format?.label ?? ''
)
watch(() => [props.movie.ownershipFormat, props.movie.ownership_format], ([fmt1, fmt2]) => {
  ownershipFormatLabel.value = fmt1?.label ?? fmt2?.label ?? ''
}, { immediate: true, deep: true })

// Mantener sincronía solo si cambia el IMDb ID (nueva película)
watch(() => props.movie.imdbID, (newId, oldId) => {
  if (newId !== oldId) {
    selectedUserStatuses.value = getInitialStatuses();
  }
});
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/library-item' as *;

.library-movie-item-container {
  @include library-item('movie', '2/3', 80px, 'movie');
}
</style>
