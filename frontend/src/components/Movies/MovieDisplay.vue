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
      <div class="status-selector-container">
        <p class="status-selector-title"><strong>Status:</strong> (selecciona uno o más)</p>
        <MultiSelect
          v-model="selectedUserStatuses"
          :options="normalizedAllowedUserStatuses"
          :filter="true"
          :display="'chip'"
          placeholder="Selecciona estados"
          style="width: 100%; max-width: 20rem;"
        />
      </div>

      <div class="actions-container">
        <button 
          @click="onSaveMovie" 
          class="add-button"
          :disabled="selectedUserStatuses.length === 0"
        >
          Guardar en mi colección
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, defineProps, computed } from 'vue';
import MultiSelect from 'primevue/multiselect';
import Logger from '@/utils/logger';

const props = defineProps({
  movie: { type: Object, required: true },
  allowedUserStatuses: {
    type: Array,
    default: () => []
  }
});
const selectedUserStatuses = ref([]);

// Normaliza la prop para asegurar que vue-multiselect siempre reciba un array plano de strings
const normalizedAllowedUserStatuses = computed(() => {
  return Array.isArray(props.allowedUserStatuses)
    ? props.allowedUserStatuses.map(s => typeof s === 'string' ? s : String(s))
    : [];
});

Logger.debug('MovieDisplay allowedUserStatuses:', props.allowedUserStatuses);
Logger.debug('MovieDisplay normalizedAllowedUserStatuses:', normalizedAllowedUserStatuses.value);

import { useAuthStore } from '@/store/auth';

const authStore = useAuthStore();

const onSaveMovie = async () => {
  try {
    const payload = {
      movie: {
        id: props.movie.imdbID,
        title: props.movie.Title,
        originalTitle: props.movie.Title,
        director: props.movie.Director,
        coverUrl: props.movie.Poster,
        rating: null, // No guardar la nota de OMDb como rating en la base de datos
        description: props.movie.Plot || "",
        userStatuses: selectedUserStatuses.value,
        addedTimestamp: Date.now()
      }
    };
    const response = await authStore.apiCall('add_movie', payload);
    if (response.data && response.data.status === 'success') {
      alert('Película guardada correctamente en tu colección.');
    } else {
      alert(response.data.message || 'Error al guardar la película.');
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
.status-selector-container {
  margin-top: 15px;
  margin-bottom: 15px;
}
.status-selector-title {
  font-size: 0.95rem;
  color: #ccc;
  margin-bottom: 8px;
}
.actions-container {
  margin-top: 20px;
  display: flex;
  gap: 10px;
}
.add-button {
  padding: 10px 20px;
  font-size: 0.9rem;
  font-weight: 500;
  color: #ffffff;
  border-radius: 20px;
  cursor: pointer;
  outline: none;
  transition: background-color 0.3s ease, border-color 0.3s ease;
  border: 1px solid transparent;
  background-color: #28a745;
  border-color: #28a745;
}
.add-button:hover {
  background-color: #218838;
  border-color: #1e7e34;
}
.add-button:disabled {
  background-color: #555;
  border-color: #444;
  color: #888;
  cursor: not-allowed;
}
</style>
