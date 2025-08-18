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
          :rating="movie.user_rating"
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
        
        <!-- Movie Actions Component -->
        <MovieActions
          :item="movie"
          :is-new="!movie.userStatuses || movie.userStatuses.length === 0"
          :can-save="canSave"
          :can-delete="canDelete"
          :show-update-button="false"
          @save="onSaveMovie"
          @delete="onDeleteMovie"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, ref, computed, watch } from 'vue';
import RatingComponent from '@/components/common/RatingComponent.vue';
import StatusSelector from '@/components/common/StatusSelector.vue';
import MovieActions from '@/components/Movies/MovieActions.vue';
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

const emit = defineEmits(['delete-movie', 'update-rating', 'update-statuses', 'save-movie']);

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

// Computed properties
const canSave = computed(() => {
  return props.movie.title && selectedUserStatuses.value.length > 0;
});

const canDelete = computed(() => {
  return props.movie.userStatuses && props.movie.userStatuses.length > 0;
});

// Methods
const onRatingChange = (rating) => {
  Logger.debug('Rating changed to:', rating);
  emit('update-rating', { isbn: props.movie.imdbID, rating, itemType: 'movie' });
};

const onStatusesChange = (statuses) => {
  selectedUserStatuses.value = statuses;
  Logger.debug('Statuses changed to:', statuses);
  
  // Si la película ya está guardada (tiene userStatuses), emitir inmediatamente
  if (props.movie.userStatuses && props.movie.userStatuses.length > 0) {
    emit('update-statuses', { isbn: props.movie.imdbID, statuses: [...selectedUserStatuses.value], itemType: 'movie' });
  }
  // Si no está guardada, no emitir nada (esperar a guardar)
};

const onSaveMovie = () => {
  if (canSave.value) {
    Logger.debug('Saving movie with statuses:', selectedUserStatuses.value);
    emit('save-movie', { 
      movie: { ...props.movie, userStatuses: [...selectedUserStatuses.value] }, 
      statuses: [...selectedUserStatuses.value] 
    });
  }
};

const onDeleteMovie = () => {
  Logger.debug('Deleting movie:', props.movie.imdbID);
  emit('delete-movie', { isbn: props.movie.imdbID, imdbID: props.movie.imdbID, itemType: 'movie' });
};

// Mantener sincronía solo si cambia el IMDb ID (nueva película)
watch(() => props.movie.imdbID, (newId, oldId) => {
  if (newId !== oldId) {
    selectedUserStatuses.value = getInitialStatuses();
  }
});
</script>

<style>
/* Igual que .library-book-item-container para altura y aspecto uniforme */
.library-movie-item-container {
  padding: 20px;
  background-color: #2c2c2c;
  border-radius: 15px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.25);
  width: auto;
  height: 100%;
  display: flex;
  flex-direction: column;
}
@media (max-width: 480px) {
  .library-movie-item-container {
    width: 100%;
  }
}
.movie-details {
  display: flex;
  align-items: flex-start;
  gap: 20px;
}
.cover-image-container {
  flex-shrink: 0;
}
.cover-image {
  width: 100px;
  height: auto;
  border-radius: 8px;
  border: 1px solid #444;
}
.info-text {
  text-align: left;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
}
.movie-title {
  font-size: 1.3rem;
  color: #e0e0e0;
  margin-top: 0;
  margin-bottom: 8px;
}
.movie-author,
.movie-year,
.movie-isbn {
  font-size: 0.95rem;
  color: #bbb;
  margin-top: 0;
  margin-bottom: 4px;
}
.movie-author strong,
.movie-isbn strong {
  font-weight: 500;
  color: #888;
  margin-right: 6px;
}
</style>
