<template>
  <div class="list-item list-item--album" @click="handleClick">
    <img
      v-if="album.cover_url && !imageError"
      :src="album.cover_url"
      alt="Album Cover"
      class="list-item__cover"
      @error="handleImageError"
      loading="lazy"
    />
    <div v-else class="list-item__cover-placeholder">
      <i class="fas fa-music"></i>
    </div>

    <div class="list-item__info">
      <div class="list-item__title">{{ album.title || album.name }}</div>
      <div class="list-item__subtitle">{{ subtitle }}</div>

      <div v-if="album.user_rating && album.user_rating > 0" class="list-item__rating">
        <RatingComponent :rating="album.user_rating" :readonly="true" :size="'small'" />
      </div>

      <div v-if="album.userStatuses && album.userStatuses.length > 0" class="list-item__statuses">
        <span
          v-for="status in album.userStatuses"
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
import { ref, computed } from 'vue';
import RatingComponent from '@/components/common/RatingComponent.vue';

const props = defineProps({
  album: {
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

const handleClick = () => emit('click', props.album);
const handleImageError = () => { imageError.value = true; };

const artistName = computed(() => {
  return props.album?.artist || props.album?.artists?.[0]?.name || 'Artista desconocido';
});

const releaseYear = computed(() => {
  const date = props.album?.release_date || props.album?.releaseDate;
  if (!date) return '';
  return date.toString().substring(0, 4);
});

// Subtitle unificado: "Artista • Año"
const subtitle = computed(() => {
  return releaseYear.value ? `${artistName.value} • ${releaseYear.value}` : artistName.value;
});

const getStatusLabel = (status) => {
  const found = props.allowedStatuses.find(s => s.name === status || s.id === status || s.key === status);
  return found?.label || found?.name || status;
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/list-item' as *;

.list-item--album {
  @include list-item('album', '1/1', 56px);
}
</style>
