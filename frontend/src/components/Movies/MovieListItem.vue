<template>
  <div class="list-item list-item--movie" @click="handleClick">
    <img
      v-if="movie.coverUrl && !imageError"
      :src="movie.coverUrl"
      alt="Poster"
      class="list-item__cover"
      @error="handleImageError"
      loading="lazy"
    />
    <div v-else class="list-item__cover-placeholder">
      <i :class="isSeries ? 'fas fa-tv' : 'fas fa-film'"></i>
    </div>

    <div class="list-item__info">
      <div class="list-item__header">
        <div class="list-item__title">{{ movie.title }}</div>
        <span class="list-item__type-badge" :class="isSeries ? 'is-series' : 'is-movie'">
          <i :class="isSeries ? 'fas fa-tv' : 'fas fa-film'"></i>
          {{ isSeries ? 'Serie' : 'Película' }}
        </span>
      </div>
      <div class="list-item__subtitle">{{ subtitle }}</div>

      <div v-if="movie.user_rating && movie.user_rating > 0" class="list-item__rating">
        <RatingComponent :rating="movie.user_rating" :readonly="true" :size="'small'" />
      </div>

      <div v-if="movie.userStatuses && movie.userStatuses.length > 0" class="list-item__statuses">
        <span
          v-for="status in movie.userStatuses"
          :key="status"
          class="list-item__status-badge"
        >
          {{ getStatusLabel(status) }}
        </span>
      </div>
    </div>

    <i class="fas fa-chevron-right list-item__arrow"></i>
  </div>
</template>

<script setup>
import { ref, computed, defineProps, defineEmits } from 'vue';
import RatingComponent from '@/components/common/RatingComponent.vue';

const props = defineProps({
  movie: {
    type: Object,
    required: true
  },
  allowedStatuses: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['click']);

const imageError = ref(false);

const isSeries = computed(() => {
  const t = props.movie.media_type || props.movie.mediaType || props.movie.type || 'movie';
  return t === 'series';
});

// Subtitle unificado: "Director • Año" cuando ambos existen
const subtitle = computed(() => {
  const director = props.movie.director || 'Director desconocido';
  const year = props.movie.year ? props.movie.year.toString() : '';
  return year ? `${director} • ${year}` : director;
});

const handleClick = () => {
  emit('click', props.movie);
};

const handleImageError = () => {
  imageError.value = true;
};

const getStatusLabel = (statusKey) => {
  const status = props.allowedStatuses.find(s => s.key === statusKey);
  return status ? status.name : statusKey;
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/list-item' as *;

.list-item--movie {
  @include list-item('movie', '2/3', 75px);

  .list-item__type-badge {
    @include list-item-type-badge;
  }
}
</style>
