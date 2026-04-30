<template>
  <div class="album-list-item" @click="handleClick">
    <img
      v-if="album.cover_url && !imageError"
      :src="album.cover_url"
      alt="Album Cover"
      class="album-list-cover"
      @error="handleImageError"
      loading="lazy"
    />
    <div v-else class="album-list-cover-placeholder">
      <i class="fas fa-music"></i>
    </div>

    <div class="album-list-info">
      <div class="album-list-title">{{ album.title || album.name }}</div>
      <div class="album-list-subtitle">{{ artistName }}</div>

      <div v-if="releaseYear" class="album-list-year">
        <i class="fas fa-calendar-alt"></i>
        <span>{{ releaseYear }}</span>
      </div>

      <!-- Rating (si existe) -->
      <div v-if="album.user_rating && album.user_rating > 0" class="album-list-rating">
        <RatingComponent
          :rating="album.user_rating"
          :readonly="true"
          :size="'small'"
        />
      </div>

      <!-- Statuses -->
      <div v-if="album.userStatuses && album.userStatuses.length > 0" class="album-list-status">
        <span
          v-for="status in album.userStatuses"
          :key="status"
          class="status-badge"
        >
          {{ getStatusLabel(status) }}
        </span>
      </div>
    </div>

    <i class="fas fa-chevron-right album-list-arrow"></i>
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
  return props.album?.artist || props.album?.artists?.[0]?.name || '';
});

const releaseYear = computed(() => {
  const date = props.album?.release_date || props.album?.releaseDate;
  if (!date) return '';
  return date.toString().substring(0, 4);
});

const getStatusLabel = (status) => {
  const found = props.allowedStatuses.find(s => s.name === status || s.id === status);
  return found?.label || found?.name || status;
};
</script>

<style scoped>
.album-list-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 14px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s;
  background: var(--surface-card, #1e2028);
  border: 1px solid var(--surface-border, #2d3141);
}

.album-list-item:hover {
  background: var(--surface-hover, #252836);
}

.album-list-cover {
  width: 56px;
  height: 56px;
  border-radius: 6px;
  object-fit: cover;
  flex-shrink: 0;
}

.album-list-cover-placeholder {
  width: 56px;
  height: 56px;
  border-radius: 6px;
  background: var(--surface-section, #2a2d36);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--text-color-secondary, #6b7280);
}

.album-list-info {
  flex: 1;
  min-width: 0;
}

.album-list-title {
  font-size: 0.92rem;
  font-weight: 600;
  color: var(--text-color, #e0e0e0);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.album-list-subtitle {
  font-size: 0.78rem;
  color: var(--text-color-secondary, #9ca3af);
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.album-list-year {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  color: var(--text-color-secondary, #9ca3af);
  margin-top: 4px;
}

.album-list-status {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}

.status-badge {
  font-size: 0.7rem;
  padding: 2px 7px;
  border-radius: 10px;
  background: var(--primary-color, #1D4E4A);
  color: white;
}

.album-list-arrow {
  color: var(--text-color-secondary, #6b7280);
  flex-shrink: 0;
  font-size: 0.75rem;
}
</style>
