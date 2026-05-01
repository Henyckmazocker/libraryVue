<template>
  <div class="list-item list-item--game" @click="handleClick">
    <img
      v-if="game.coverUrl && !imageError"
      :src="game.coverUrl"
      alt="Game Cover"
      class="list-item__cover"
      @error="handleImageError"
      loading="lazy"
    />
    <div v-else class="list-item__cover-placeholder">
      <i class="fas fa-gamepad"></i>
    </div>

    <div class="list-item__info">
      <div class="list-item__title">{{ game.title || game.name }}</div>
      <div class="list-item__subtitle">{{ subtitle }}</div>

      <div v-if="platformsText" class="list-item__extra">
        <i :class="getPlatformIcon()"></i>
        <span>{{ platformsText }}</span>
      </div>

      <div v-if="game.user_rating && game.user_rating > 0" class="list-item__rating">
        <RatingComponent :rating="game.user_rating" :readonly="true" :size="'small'" />
      </div>

      <div v-if="game.userStatuses && game.userStatuses.length > 0" class="list-item__statuses">
        <span
          v-for="status in game.userStatuses"
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
  game: {
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

const handleClick = () => {
  emit('click', props.game);
};

const handleImageError = () => {
  imageError.value = true;
};

// Subtitle unificado: "Desarrollador • Año"
const subtitle = computed(() => {
  const developer = props.game.developer ||
    (Array.isArray(props.game.developers) && props.game.developers.length > 0
      ? props.game.developers[0].name || props.game.developers[0]
      : '');

  const year = props.game.releaseDate
    ? new Date(props.game.releaseDate).getFullYear()
    : (props.game.released ? new Date(props.game.released).getFullYear() : '');

  if (developer && year) return `${developer} • ${year}`;
  if (developer) return developer;
  if (year) return String(year);
  return 'Desarrollador desconocido';
});

const platformsText = computed(() => {
  if (typeof props.game.platforms === 'string') {
    const platforms = props.game.platforms.split(',').map(p => p.trim());
    return platforms.length > 2 ? `${platforms.slice(0, 2).join(', ')}...` : props.game.platforms;
  }
  if (Array.isArray(props.game.platforms)) {
    const platformNames = props.game.platforms
      .map(p => typeof p === 'string' ? p : p.platform?.name || p.name)
      .filter(Boolean);
    return platformNames.length > 2
      ? `${platformNames.slice(0, 2).join(', ')}...`
      : platformNames.join(', ');
  }
  return '';
});

const getPlatformIcon = () => {
  const platforms = platformsText.value.toLowerCase();
  if (platforms.includes('playstation') || platforms.includes('ps')) return 'fab fa-playstation';
  if (platforms.includes('xbox')) return 'fab fa-xbox';
  if (platforms.includes('nintendo') || platforms.includes('switch')) return 'fas fa-gamepad';
  if (platforms.includes('pc') || platforms.includes('windows')) return 'fab fa-windows';
  if (platforms.includes('linux')) return 'fab fa-linux';
  if (platforms.includes('mac')) return 'fab fa-apple';
  if (platforms.includes('android') || platforms.includes('ios')) return 'fas fa-mobile-alt';
  return 'fas fa-gamepad';
};

const getStatusLabel = (statusKey) => {
  const status = props.allowedStatuses.find(s => s.key === statusKey);
  return status ? status.name : statusKey;
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/list-item' as *;

.list-item--game {
  @include list-item('game', '1/1', 60px);
}
</style>
