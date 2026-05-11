<template>
  <div class="list-item list-item--video" @click="handleClick">
    <img
      v-if="video.cover_url && !imageError"
      :src="video.cover_url"
      alt="Video Thumbnail"
      class="list-item__cover"
      @error="handleImageError"
      loading="lazy"
    />
    <div v-else class="list-item__cover-placeholder">
      <i class="fab fa-youtube"></i>
    </div>

    <div class="list-item__info">
      <div class="list-item__title">{{ video.title }}</div>
      <div class="list-item__subtitle">{{ subtitle }}</div>

      <div v-if="video.user_rating && video.user_rating > 0" class="list-item__rating">
        <RatingComponent :rating="video.user_rating" :readonly="true" :size="'small'" />
      </div>

      <div v-if="video.userStatuses && video.userStatuses.length > 0" class="list-item__statuses">
        <span
          v-for="status in video.userStatuses"
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
  video: {
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

const handleClick = () => emit('click', props.video);
const handleImageError = () => { imageError.value = true; };

const channelName = computed(() => {
  return props.video?.channel_name || props.video?.channelName || 'Canal desconocido';
});

const duration = computed(() => {
  return props.video?.duration || '';
});

const subtitle = computed(() => {
  return duration.value ? `${channelName.value} • ${duration.value}` : channelName.value;
});

const getStatusLabel = (status) => {
  const found = props.allowedStatuses.find(s => s.name === status || s.id === status || s.key === status);
  return found?.label || found?.name || status;
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/list-item' as *;

.list-item--video {
  @include list-item('video', '16/9', 56px);
}
</style>
