<template>
  <button
    type="button"
    class="movie-carousel-item"
    @click="handleClick"
  >
    <div class="movie-poster-wrapper">
      <img
        v-if="coverUrl && !imageError"
        :src="coverUrl"
        :alt="movie.title"
        class="movie-poster"
        width="150"
        height="225"
        loading="lazy"
        decoding="async"
        @error="handleImageError"
      >
      <div
        v-else
        class="movie-poster-placeholder"
      >
        <i :class="isSeries ? 'fas fa-tv' : 'fas fa-film'" />
      </div>
      
      <!-- Badge de año -->
      <div
        v-if="movie.year || movie.Year"
        class="year-badge"
      >
        {{ movie.year || movie.Year }}
      </div>
      
      <!-- Badge de rating si existe -->
      <div
        v-if="movie.user_rating && movie.user_rating > 0"
        class="rating-badge"
      >
        <i class="fas fa-star" />
        <span>{{ movie.user_rating }}</span>
      </div>
      
      <!-- Badge de tipo: Serie o Película -->
      <div
        class="media-type-badge"
        :class="isSeries ? 'is-series' : 'is-movie'"
      >
        <i :class="isSeries ? 'fas fa-tv' : 'fas fa-film'" />
        {{ isSeries ? 'Serie' : 'Película' }}
      </div>

      <!-- Badge de "En tu biblioteca" -->
      <div
        v-if="isInLibrary"
        class="library-badge"
        :title="t('common.inLibrary')"
      >
        <i
          class="fas fa-bookmark"
          aria-hidden="true"
        />
        <span class="u-sr-only">{{ t('common.inLibrary') }}</span>
      </div>
      
      <!-- Badge de status si existe (para compatibilidad) -->
      <div
        v-if="movie.userStatuses && movie.userStatuses.length > 0 && !isInLibrary"
        class="status-badge"
      >
        <i class="fas fa-check-circle" />
      </div>
    </div>
    
    <div class="movie-info">
      <h3 class="movie-title">
        {{ truncateText(movie.title || movie.Title, 40) }}
      </h3>
    </div>
  </button>
</template>

<script setup>
import { computed, defineProps, defineEmits, ref } from 'vue';
import { useMoviesStore } from '@/store/movies';
import CoverService from '@/services/CoverService';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
  movie: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['click']);

const moviesStore = useMoviesStore();

// Los dumps de IMDb no traen pósters, así que la búsqueda manda `Poster: null`
// en todas y aquí no había ninguna URL remota a la que caer: se veía el icono
// de relleno. Ahora el backend resuelve el póster contra TMDB a partir del
// tconst y lo cachea, así que la segunda búsqueda ya los enseña.
//
// El escalón final sigue siendo el placeholder, no una URL remota: es lo que se
// ve hoy, así que en el peor caso no empeora nada.
const remoteUrl = computed(() => props.movie.coverUrl || null);
const localFailed = ref(false);
const imageError = ref(false);

const coverUrl = computed(() => {
  if (localFailed.value) {
    return remoteUrl.value;
  }

  const clave = props.movie.imdbID || props.movie.imdbId || props.movie.isbn;

  return CoverService.catalogCoverUrl('movie', clave) || remoteUrl.value;
});

// Mismo escalón doble que el resto: primero la copia local, luego la remota si
// la hay, y solo entonces el placeholder.
const handleImageError = () => {
  if (!localFailed.value && coverUrl.value !== remoteUrl.value) {
    localFailed.value = true;
    return;
  }
  imageError.value = true;
};

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
@use '@/assets/styles/abstracts' as *;

.movie-carousel-item {
  @include button-reset;
  flex-shrink: 0;
  width: 150px;
  cursor: pointer;
  border-radius: radius(md);
  overflow: hidden;
  background: var(--color-background-card);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: flex;
  flex-direction: column;
}

.movie-carousel-item:hover {
  transform: translateY(-4px);
  box-shadow: shadow(heavy);
}

.movie-poster-wrapper {
  position: relative;
  width: 150px;
  height: 225px;
  overflow: hidden;
  background: var(--color-background-mute);
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
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-hover));
}

.movie-poster-placeholder i {
  font-size: var(--font-size-4xl);
  color: rgba(255, 255, 255, 0.4);
}

.year-badge {
  position: absolute;
  bottom: 8px;
  left: 8px;
  background: rgba(0, 0, 0, 0.8);
  color: white;
  padding: spacing(2xs) spacing(xs);
  border-radius: radius(sm);
  font-size: var(--font-size-xs);
  font-weight: 600;
  box-shadow: shadow(light);
}

.rating-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: var(--color-overlay-strong);
  color: var(--color-rating-star);
  padding: spacing(2xs) spacing(xs);
  border-radius: radius(lg);
  font-size: var(--font-size-xs);
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: spacing(2xs);
  box-shadow: shadow(light);
}

.rating-badge i {
  font-size: var(--font-size-xs);
}

.status-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: rgba(76, 175, 80, 0.95);
  color: white;
  padding: spacing(2xs) spacing(xs);
  border-radius: 50%;
  font-size: var(--font-size-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: shadow(light);
}

.library-badge {
  position: absolute;
  top: 6px;
  left: 6px;
  background: var(--color-overlay-strong);
  color: var(--color-on-overlay);
  font-size: var(--font-size-xs);
  padding: spacing(2xs) spacing(xs);
  border-radius: radius(sm);
}

.media-type-badge {
  position: absolute;
  bottom: 8px;
  right: 8px;
  display: flex;
  align-items: center;
  gap: spacing(2xs);
  padding: spacing(2xs) spacing(xs);
  border-radius: radius(sm);
  font-size: var(--font-size-xs);
  font-weight: 600;
  letter-spacing: 0.02em;
}

// Ambos van sobre la carátula, así que comparten scrim y tinta. La distinción
// serie/película la llevan el icono y la palabra; el borde de acento la refuerza
// sin depender del color solo. Antes eran `rgba(139,92,246,.9)` —el acento viejo de
// película— y un teal con su propio gris azulado.
.media-type-badge.is-series,
.media-type-badge.is-movie {
  background: var(--color-overlay-strong);
  color: var(--color-on-overlay);
}

.media-type-badge.is-series {
  border: 1px solid var(--color-card-movie-accent);
}

.movie-info {
  display: flex;
  flex-direction: column;
  padding: spacing(xs) spacing(sm);
}

.movie-title {
  font-size: var(--font-size-sm);
  font-weight: 600;
  color: var(--color-text);
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
@include responsive-below(md) {
  .movie-carousel-item {
    width: 130px;
  }
  .movie-poster-wrapper {
    width: 130px;
    height: 195px;
  }
}

@include responsive-below(sm) {
  .movie-carousel-item {
    width: 110px;
  }
  .movie-poster-wrapper {
    width: 110px;
    height: 165px;
  }
  .movie-poster-placeholder i {
    font-size: var(--font-size-2xl);
  }
}
</style>
