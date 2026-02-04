<template>
  <div class="movie-carousel-item" @click="handleClick">
    <div class="movie-poster-wrapper">
      <img 
        v-if="movie.coverUrl" 
        :src="movie.coverUrl" 
        :alt="movie.title"
        class="movie-poster" 
        loading="lazy"
      />
      <div v-else class="movie-poster-placeholder">
        <i class="fas fa-film"></i>
      </div>
      
      <!-- Badge de año -->
      <div v-if="movie.year || movie.Year" class="year-badge">
        {{ movie.year || movie.Year }}
      </div>
      
      <!-- Badge de rating si existe -->
      <div v-if="movie.user_rating && movie.user_rating > 0" class="rating-badge">
        <i class="fas fa-star"></i>
        <span>{{ movie.user_rating }}</span>
      </div>
      
      <!-- Badge de "En tu biblioteca" -->
      <div v-if="isInLibrary" class="library-badge" title="En tu biblioteca">
        <i class="fas fa-bookmark"></i>
      </div>
      
      <!-- Badge de status si existe (para compatibilidad) -->
      <div v-if="movie.userStatuses && movie.userStatuses.length > 0 && !isInLibrary" class="status-badge">
        <i class="fas fa-check-circle"></i>
      </div>
    </div>
    
    <div class="movie-info">
      <h3 class="movie-title">{{ truncateText(movie.title || movie.Title, 40) }}</h3>
    </div>
  </div>
</template>

<script setup>
import { computed, defineProps, defineEmits } from 'vue';
import { useMoviesStore } from '@/store/movies';

const props = defineProps({
  movie: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['click']);

const moviesStore = useMoviesStore();

// Check if movie is in library (from trending API or store check)
const isInLibrary = computed(() => {
  // If trending API provided the field, use it
  if (typeof props.movie.is_in_user_library !== 'undefined') {
    return props.movie.is_in_user_library === 1 || props.movie.is_in_user_library === true;
  }
  // Otherwise check the store (for search results)
  const movieId = props.movie.imdbID || props.movie.isbn;
  return movieId ? moviesStore.isMovieInLibrary(movieId) : false;
});

const handleClick = () => {
  emit('click', props.movie);
};

const truncateText = (text, maxLength) => {
  if (!text) return '';
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};
</script>

<style scoped>
@import '@/assets/styles/variables.css';

.movie-carousel-item {
  flex-shrink: 0;
  width: 150px;
  cursor: pointer;
  transition: transform 0.2s ease;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.movie-carousel-item:hover {
  transform: translateY(-5px);
}

.movie-poster-wrapper {
  position: relative;
  width: 150px;
  height: 225px;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transition: box-shadow 0.2s ease;
}

.movie-carousel-item:hover .movie-poster-wrapper {
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
}

.movie-poster {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.movie-poster-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--color-background-mute) 0%, var(--color-background-soft) 100%);
  border: 2px dashed var(--color-border);
}

.movie-poster-placeholder i {
  font-size: 3rem;
  color: var(--color-text-muted);
  opacity: 0.5;
}

.year-badge {
  position: absolute;
  bottom: 8px;
  left: 8px;
  background: rgba(0, 0, 0, 0.8);
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.rating-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(255, 193, 7, 0.95);
  color: #000;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 3px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.rating-badge i {
  font-size: 0.7rem;
}

.status-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: rgba(76, 175, 80, 0.95);
  color: white;
  padding: 4px 8px;
  border-radius: 50%;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.library-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: rgba(33, 150, 243, 0.95);
  color: white;
  padding: 6px 8px;
  border-radius: 6px;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(4px);
  transition: transform 0.2s ease;
}

.library-badge:hover {
  transform: scale(1.1);
}

.movie-info {
  display: flex;
  flex-direction: column;
  text-align: center;
  padding: 0 5px;
}

.movie-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-heading);
  line-height: 1.3;
  margin: 0;
  min-height: 2.6em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Responsive */
@media (max-width: 768px) {
  .movie-carousel-item {
    width: 130px;
  }
  
  .movie-poster-wrapper {
    width: 130px;
    height: 195px;
  }
  
  .movie-title {
    font-size: 0.85rem;
  }
}

@media (max-width: 480px) {
  .movie-carousel-item {
    width: 110px;
  }
  
  .movie-poster-wrapper {
    width: 110px;
    height: 165px;
  }
  
  .movie-poster-placeholder i {
    font-size: 2rem;
  }
  
  .movie-title {
    font-size: 0.8rem;
  }
}
</style>
