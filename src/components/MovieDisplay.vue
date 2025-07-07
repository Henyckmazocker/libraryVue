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
        <vue-multiselect
          v-model="selectedUserStatuses"
          :options="normalizedAllowedUserStatuses"
          :multiple="true"
          :close-on-select="false"
          :clear-on-select="false"
          placeholder="Selecciona estados"
          class="status-vue-multiselect"
        >
          <template #option="{ option }">
            {{ option.charAt(0).toUpperCase() + option.slice(1) }}
          </template>
          <template #tag="{ option }">
            <span class="multiselect__tag">
              <span>{{ option.charAt(0).toUpperCase() + option.slice(1) }}</span>
            </span>
          </template>
        </vue-multiselect>
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
import VueMultiselect from 'vue-multiselect';

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

console.log('MovieDisplay allowedUserStatuses:', props.allowedUserStatuses);
console.log('MovieDisplay normalizedAllowedUserStatuses:', normalizedAllowedUserStatuses.value);

const onSaveMovie = () => {
  // Aquí puedes emitir un evento o manejar el guardado
  alert(`Película guardada con estados: ${selectedUserStatuses.value.join(', ')}`);
};
</script>

<style scoped>
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
.status-vue-multiselect .multiselect__tags {
  background-color: #2c2c2c;
  border: 1px solid #555;
  border-radius: 5px;
  padding-top: 7px;
}
.status-vue-multiselect .multiselect__input,
.status-vue-multiselect .multiselect__single {
  background-color: #2c2c2c;
  color: #eee;
}
.status-vue-multiselect .multiselect__content-wrapper {
  background-color: #2c2c2c;
  border: 1px solid #555;
  border-top: none;
  border-radius: 0 0 5px 5px;
}
.status-vue-multiselect .multiselect__option {
  color: #eee;
  background-color: #2c2c2c;
}
.status-vue-multiselect .multiselect__option--highlight {
  background-color: #007bff;
  color: white;
}
.status-vue-multiselect .multiselect__option--selected {
  background-color: #4a4a4a;
  color: #eee;
  font-weight: bold;
}
.status-vue-multiselect .multiselect__tag {
  background-color: #007bff;
  color: white;
  margin-bottom: 2px;
  border-radius: 4px;
}
.status-vue-multiselect .multiselect__tag-icon {
  background: none;
  border-left: 1px solid rgba(255, 255, 255, 0.5);
}
.status-vue-multiselect .multiselect__tag-icon:after {
 content: "×";
 color: rgba(255, 255, 255, 0.7);
 font-size: 14px;
 font-weight: bold;
}
.status-vue-multiselect .multiselect__tag-icon:hover {
  background-color: #0056b3;
}
.status-vue-multiselect .multiselect__placeholder {
  color: #888;
  margin-bottom: 0;
  padding-top: 0;
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
