<template>
  <div class="library-movie-item-container">
    <div class="movie-details">
      <div class="cover-image-container" v-if="movie.coverUrl">
        <img :src="movie.coverUrl" alt="Movie Poster" class="cover-image" />
      </div>
      <div class="info-text">
        <h3 class="movie-title">{{ movie.title }}</h3>
        <p v-if="movie.author" class="movie-author"><strong>author:</strong> {{ movie.author }}</p>
        <p v-if="movie.year" class="movie-year"><strong>Year:</strong> {{ movie.year }}</p>
        <p class="movie-isbn"><strong>IMDb ID:</strong> {{ movie.isbn }}</p>
        <div class="rating-section">
          <p class="current-rating">Rating: {{ movie.rating !== null ? movie.rating + '/5' : 'Not Rated' }}</p>
          <div class="stars-input">
            <div v-for="starPosition in 5" :key="'star-' + starPosition" class="star-container">
              <span
                class="star-half left-half"
                @click="setRating(starPosition - 0.5)"
                @mouseover="hoverRating = starPosition - 0.5"
                @mouseleave="hoverRating = 0"
                :class="{ 
                  'filled': (currentVisualRating >= starPosition - 0.5),
                  'hovered': (hoverRating >= starPosition - 0.5) && (hoverRating < starPosition)
                }"
              >★</span>
              <span
                class="star-half right-half"
                @click="setRating(starPosition)"
                @mouseover="hoverRating = starPosition"
                @mouseleave="hoverRating = 0"
                :class="{
                  'filled': (currentVisualRating >= starPosition),
                  'hovered': (hoverRating >= starPosition)
                }"
              >★</span>
            </div>
          </div>
        </div>
        <div class="status-selector-container" v-if="allowedUserStatuses && allowedUserStatuses.length > 0" style="overflow:visible;">
          <p class="status-selector-title"><strong>Status:</strong> (selecciona uno o más)</p>
          <MultiSelect
            v-model="selectedUserStatuses"
            :options="allowedUserStatuses"
            :filter="true"
            :display="'chip'"
            placeholder="Selecciona estados"
            style="width: 100%; max-width: 20rem;"
            @change="onStatusesChange"
          >
          </MultiSelect>
        </div>
        <button v-if="!movie.userStatuses || movie.userStatuses.length === 0" @click="onSaveMovie" class="save-button" :disabled="!movie.title || selectedUserStatuses.length === 0">
          <i class="fas fa-save"></i>
        </button>
        <button v-if="movie.userStatuses && movie.userStatuses.length > 0" @click="onDeleteMovie" class="delete-button">
          <i class="fas fa-trash"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, ref, computed, watch } from 'vue';
import MultiSelect from 'primevue/multiselect';

const props = defineProps({
  movie: {
    type: Object,
    required: true,
    default: () => ({ isbn: '', title: '', author: '', coverUrl: '', rating: null })
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
const selectedUserStatuses = ref(props.movie.userStatuses ? [...props.movie.userStatuses] : []);

watch(() => props.movie.isbn, (newId, oldId) => {
  if (newId !== oldId) {
    selectedUserStatuses.value = props.movie.userStatuses ? [...props.movie.userStatuses] : [];
  }
});

const onStatusesChange = () => {
  if (props.movie.userStatuses && props.movie.userStatuses.length > 0) {
    emit('update-statuses', { isbn: props.movie.isbn, statuses: [...selectedUserStatuses.value], itemType: 'movie' });
  }
};

const onSaveMovie = () => {
  if (!props.movie.title || selectedUserStatuses.value.length === 0) return;
  emit('save-movie', { movie: { ...props.movie, userStatuses: [...selectedUserStatuses.value] }, statuses: [...selectedUserStatuses.value] });
};
const hoverRating = ref(0);
const currentVisualRating = computed(() => {
  return hoverRating.value || (props.movie.rating === null ? 0 : props.movie.rating);
});
const onDeleteMovie = () => {
  emit('delete-movie', props.movie.isbn);
};
const setRating = (ratingValue) => {
  emit('update-rating', { isbn: props.movie.isbn, rating: ratingValue, itemType: 'movie' });
};
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
.rating-section {
  margin-top: 10px;
  margin-bottom: 10px;
}
.current-rating {
  font-size: 0.9em;
  color: #ccc;
  margin-bottom: 5px;
}
.stars-input {
  display: flex;
}
.star-container {
  position: relative;
  display: inline-block;
  width: 1em;
  height: 1em;
  font-size: 1.8em;
  line-height: 1;
  margin-right: 3px;
}
.star-half {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  line-height: 1;
  text-align: center;
  color: #555;
  transition: color 0.2s ease-in-out;
  cursor: pointer;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.star-half.left-half {
  clip-path: polygon(0% 0%, 50% 0%, 50% 100%, 0% 100%);
}
.star-half.right-half {
  clip-path: polygon(50% 0%, 100% 0%, 100% 100%, 50% 100%);
}
.star-half.filled {
  color: #f5c518;
}
.star-half.hovered {
  color: #f5b508;
}
.save-button {
  background-color: #007bff;
  border-color: #007bff;
  color: #fff;
  padding: 8px 15px;
  font-size: 0.85rem;
  font-weight: 500;
  border-radius: 20px;
  cursor: pointer;
  outline: none;
  transition: background-color 0.3s ease, border-color 0.3s ease;
  margin-top: 15px;
  align-self: flex-start;
  position: relative;
  z-index: 1;
}
.save-button:hover {
  background-color: #0056b3;
  border-color: #0056b3;
}
.save-button:disabled {
  background-color: #555;
  border-color: #444;
  color: #888;
  cursor: not-allowed;
}
.delete-button {
  padding: 8px 15px;
  font-size: 0.85rem;
  font-weight: 500;
  color: #ffffff;
  background-color: #dc3545;
  border: 1px solid #dc3545;
  border-radius: 20px;
  cursor: pointer;
  outline: none;
  transition: background-color 0.3s ease, border-color 0.3s ease;
  margin-top: 15px;
  align-self: flex-start;
}
.delete-button:hover {
  background-color: #c82333;
  border-color: #bd2130;
}
.p-multiselect-panel {
  z-index: 1002 !important;
}
</style>
