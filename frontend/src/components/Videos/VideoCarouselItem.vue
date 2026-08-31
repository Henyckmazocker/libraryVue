<template>
  <button
    type="button"
    class="video-carousel-item"
    @click="handleClick"
  >
    <div class="video-cover-wrapper">
      <img
        v-if="video.cover_url || video.coverUrl || video.thumbnail"
        :src="video.cover_url || video.coverUrl || video.thumbnail"
        :alt="video.title"
        class="video-cover"
        width="200"
        height="112"
        loading="lazy"
        decoding="async"
      >
      <div
        v-else
        class="video-cover-placeholder"
      >
        <i class="fab fa-youtube" />
      </div>

      <!-- Duration badge -->
      <div
        v-if="video.duration"
        class="duration-badge"
      >
        {{ video.duration }}
      </div>

      <!-- User rating badge -->
      <div
        v-if="video.user_rating && video.user_rating > 0"
        class="rating-badge"
      >
        <i class="fas fa-star" />
        <span>{{ video.user_rating }}</span>
      </div>

      <!-- In library badge -->
      <div
        v-if="isInLibrary"
        class="library-badge"
        title="En tu biblioteca"
      >
        <i
          class="fas fa-bookmark"
          aria-hidden="true"
        />
        <span class="u-sr-only">En tu biblioteca</span>
      </div>

      <!-- Play icon overlay -->
      <div class="play-overlay">
        <i class="fab fa-youtube" />
      </div>
    </div>

    <div class="video-info">
      <h3 class="video-title">
        {{ truncateText(video.title, 50) }}
      </h3>
      <p
        v-if="video.channel_name || video.channelName"
        class="video-channel"
      >
        {{ video.channel_name || video.channelName }}
      </p>
    </div>
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { useVideosStore } from '@/store/videos';

const props = defineProps({
  video: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['click']);

const videosStore = useVideosStore();

const isInLibrary = computed(() => {
  if (typeof props.video.is_in_user_library !== 'undefined') {
    return props.video.is_in_user_library === 1 || props.video.is_in_user_library === true;
  }
  const youtubeId = props.video.youtube_id || props.video.youtubeId || props.video.id;
  return youtubeId ? videosStore.isVideoInLibrary(youtubeId) : false;
});

function truncateText(text, maxLength) {
  if (!text) return '';
  return text.length > maxLength ? text.slice(0, maxLength) + '…' : text;
}

function handleClick() {
  emit('click', props.video);
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.video-carousel-item {
  @include button-reset;
  cursor: pointer;
  width: 200px;
  flex-shrink: 0;
  border-radius: radius(md);
  overflow: hidden;
  background: var(--color-card-video-bg);
  border: 1px solid var(--color-card-video-border);
  transition: transform 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    transform: translateY(-4px);
    box-shadow: shadow(heavy);
    background: var(--color-card-video-bg-hover);

    .play-overlay {
      opacity: 1;
    }
  }
}

.video-cover-wrapper {
  position: relative;
  width: 200px;
  height: 112px; // 16:9
  overflow: hidden;
  background: var(--color-media-letterbox);
}

.video-cover {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.video-cover-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--color-card-video-bg), var(--color-card-video-border));
  color: var(--color-card-video-accent);
  font-size: var(--font-size-3xl);
  opacity: 0.6;
}

.play-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-overlay-strong);
  color: var(--color-on-overlay);
  font-size: var(--font-size-2xl);
  opacity: 0;
  transition: opacity 0.2s ease;
}

.duration-badge {
  position: absolute;
  bottom: 5px;
  right: 6px;
  background: rgba(0, 0, 0, 0.8);
  color: var(--color-on-overlay);
  font-size: var(--font-size-xs);
  padding: spacing(3xs) spacing(xs);
  border-radius: radius(sm);
  font-weight: 600;
}

.rating-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  background: var(--color-overlay-strong);
  color: var(--color-rating-star);
  font-size: var(--font-size-xs);
  padding: spacing(3xs) spacing(xs);
  border-radius: radius(sm);
  display: flex;
  align-items: center;
  gap: spacing(2xs);
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

.video-info {
  padding: spacing(xs) spacing(sm);
}

.video-title {
  font-size: var(--font-size-xs);
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 spacing(2xs);
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.video-channel {
  font-size: var(--font-size-xs);
  color: var(--color-text-secondary);
  margin: 0;
}
</style>
