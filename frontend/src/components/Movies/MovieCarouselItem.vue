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
        <i :class="isSeries ? 'fas fa-tv' : 'fas fa-film'"></i>
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
      
      <!-- Badge de tipo: Serie o Película -->
      <div class="media-type-badge" :class="isSeries ? 'is-series' : 'is-movie'">
        <i :class="isSeries ? 'fas fa-tv' : 'fas fa-film'"></i>
        {{ isSeries ? 'Serie' : 'Película' }}
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

// Detectar si es serie usando cualquiera de los posibles campos
const isSeries = computed(() => {
  const t = props.movie.media_type || props.movie.mediaType || props.movie.type || 'movie';
  return t === 'series';
});

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

<style scoped lang="scss">
.movie-carousel-item {
  flex-shrink: 0;
  width: 150px;
  cursor: pointer;
  border-radius: 10px;
  overflow: hidden;
  background: var(--surface-card, #2a2d36);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: flex;
  flex-direction: column;
}

.movie-carousel-item:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

.movie-poster-wrapper {
  position: relative;
  width: 150px;
  height: 225px;
  overflow: hidden;
  background: var(--surface-ground, #1e2127);
}

.movie-poster {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.2s ease;
}

.movie-carousel-item:hover .movie-poster {
  transform: scale(1.05);
}

.movie-poster-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1D4E4A, #2a5c58);
}

.movie-poster-placeholder i {
  font-size: 3rem;
  color: rgba(255, 255, 255, 0.4);
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
  top: 6px;
  left: 6px;
  background: rgba(29, 78, 74, 0.9);
  color: #4ade80;
  font-size: 0.75rem;
  padding: 3px 6px;
  border-radius: 4px;
}

.media-type-badge {
  position: absolute;
  bottom: 8px;
  right: 8px;
  display: flex;
  align-items: center;
  gap: 3px;
  padding: 3px 7px;
  border-radius: 4px;
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.media-type-badge.is-series {
  background: rgba(139, 92, 246, 0.9);
  color: #fff;
}

.media-type-badge.is-movie {
  background: rgba(29, 78, 74, 0.85);
  color: #e0f7f5;
}

.movie-info {
  display: flex;
  flex-direction: column;
  padding: 8px 10px;
}

.movie-title {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-color, #e0e0e0);
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
}
</style>
