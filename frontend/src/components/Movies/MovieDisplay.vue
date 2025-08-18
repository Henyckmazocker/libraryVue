<template>
  <div class="movie-result">
    <img v-if="movie.Poster && movie.Poster !== 'N/A'" :src="movie.Poster" alt="Poster" class="movie-poster" />
    <div class="movie-info">
      <h2>{{ movie.Title }} ({{ movie.Year }})</h2>
      <p><strong>Director:</strong> {{ movie.Director }}</p>
      <p><strong>Género:</strong> {{ movie.Genre }}</p>
      <p><strong>Sinopsis:</strong> {{ movie.Plot }}</p>
      <p><strong>IMDb:</strong> <a :href="'https://www.imdb.com/title/' + movie.imdbID" target="_blank">{{ movie.imdbID }}</a></p>
      <p><strong>Valoración:</strong> {{ movie.imdbRating }}/10</p>

      <!-- Status Selector -->
      <StatusSelector
        v-model="selectedUserStatuses"
        :allowed-statuses="normalizedAllowedUserStatuses"
        :multiple="true"
        label="Status"
        subtitle="(selecciona uno o más)"
      />

      <!-- Rating Component -->
      <div class="rating-section">
        <label class="rating-label">Tu calificación:</label>
        <RatingComponent
          :rating="currentMovie.user_rating || 0"
          :editable="true"
          @rating-changed="onUpdateRating"
        />
      </div>

      <!-- Movie Actions -->
      <MovieActions
        :item="movie"
        :is-new="true"
        :can-save="selectedUserStatuses.length > 0"
        :show-update-button="false"
        :show-delete-button="false"
        save-button-text="Guardar en mi colección"
        @save="onSaveMovie"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, defineProps, computed } from 'vue';
import { useMovies } from '@/composables/useMovies';
import StatusSelector from '@/components/common/StatusSelector.vue';
import MovieActions from '@/components/Movies/MovieActions.vue';
import RatingComponent from '@/components/common/RatingComponent.vue';
import Logger from '@/utils/logger';

const props = defineProps({
  movie: { type: Object, required: true },
  allowedUserStatuses: {
    type: Array,
    default: () => []
  }
});

// Composables
const moviesComposable = useMovies();

const selectedUserStatuses = ref([]);

// Create a reactive copy of the movie to track user rating
const currentMovie = ref({
  ...props.movie,
  user_rating: 0
});

// Normaliza la prop para asegurar que vue-multiselect siempre reciba un array plano de strings
const normalizedAllowedUserStatuses = computed(() => {
  return Array.isArray(props.allowedUserStatuses)
    ? props.allowedUserStatuses.map(s => typeof s === 'string' ? s : String(s))
    : [];
});

Logger.debug('MovieDisplay allowedUserStatuses:', props.allowedUserStatuses);
Logger.debug('MovieDisplay normalizedAllowedUserStatuses:', normalizedAllowedUserStatuses.value);

// Handle rating updates
const onUpdateRating = (newRating) => {
  Logger.debug('Updating movie rating:', newRating);
  currentMovie.value.user_rating = newRating;
};

const onSaveMovie = async () => {
  try {
    const movieData = {
      tmdbId: currentMovie.value.imdbID, // Usar tmdbId como espera el composable
      title: currentMovie.value.Title,
      originalTitle: currentMovie.value.Title,
      director: currentMovie.value.Director,
      posterUrl: currentMovie.value.Poster, // Usar posterUrl como espera el composable
      synopsis: currentMovie.value.Plot || "", // Usar synopsis como espera el composable
      releaseDate: currentMovie.value.Year || "", // Agregar año de lanzamiento
      genre: currentMovie.value.Genre || "", // Agregar género
      duration: 0, // No tenemos duración en OMDb, usar 0
      user_rating: currentMovie.value.user_rating // Include user rating
    };

    const success = await moviesComposable.addMovie(movieData, selectedUserStatuses.value);
    if (success) {
      alert('Película guardada correctamente en tu colección.');
    } else {
      alert('Error al guardar la película.');
    }
  } catch (error) {
    Logger.error('Error al guardar película:', error);
    alert('No se pudo guardar la película en el backend.');
  }
};
</script>

<style>
.movie-result {
  display: flex;
  gap: 24px;
  background: #232323;
  border-radius: 16px;
  padding: 20px;
  margin-top: 20px;
  width: 100%;
  max-width: 600px;
}
.movie-poster {
  width: 120px;
  height: auto;
  max-width: 120px;
  max-height: 180px;
  object-fit: contain;
  border-radius: 8px;
  border: 1px solid #444;
  background: #181818;
}
.movie-info {
  flex: 1;
  color: #e0e0e0;
}
.movie-info h2 {
  margin: 0 0 10px 0;
  font-size: 1.3rem;
}
.movie-info p {
  margin: 4px 0;
  font-size: 1rem;
}
.movie-info a {
  color: #88aaff;
  text-decoration: underline;
}

.rating-section {
  margin: 16px 0;
  padding: 12px 0;
  border-top: 1px solid #444;
  border-bottom: 1px solid #444;
}

.rating-label {
  display: block;
  font-weight: bold;
  margin-bottom: 8px;
  color: #e0e0e0;
  font-size: 1rem;
}
</style>
