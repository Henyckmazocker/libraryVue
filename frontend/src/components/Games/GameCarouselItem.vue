<template>
  <button
    type="button"
    class="game-carousel-item"
    @click="handleClick"
  >
    <div class="game-cover-wrapper">
      <img 
        v-if="game.coverUrl || game.background_image" 
        :src="game.coverUrl || game.background_image" 
        :alt="game.title || game.name"
        class="game-cover"
        width="160"
        height="208"
        loading="lazy"
        decoding="async"
      >
      <div
        v-else
        class="game-cover-placeholder"
      >
        <i class="fas fa-gamepad" />
      </div>
      
      <!-- Badge de año -->
      <div
        v-if="releaseYear"
        class="year-badge"
      >
        {{ releaseYear }}
      </div>
      
      <!-- Badge de rating si existe -->
      <div
        v-if="game.user_rating && game.user_rating > 0"
        class="rating-badge"
      >
        <i class="fas fa-star" />
        <span>{{ game.user_rating }}</span>
      </div>
      
      <!-- Badge de "En tu biblioteca" -->
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
      
      <!-- Badge de status si existe -->
      <div
        v-if="game.userStatuses && game.userStatuses.length > 0 && !isInLibrary"
        class="status-badge"
      >
        <i class="fas fa-check-circle" />
      </div>
      
      <!-- Badge de plataforma principal -->
      <div
        v-if="mainPlatform"
        class="platform-badge"
        :title="platformsText"
      >
        <i
          :class="platformIcon"
          aria-hidden="true"
        />
        <span class="u-sr-only">Plataforma: {{ mainPlatform }}</span>
      </div>
    </div>
    
    <div class="game-info">
      <h3 class="game-title">
        {{ truncateText(game.title || game.name, 40) }}
      </h3>
      <div
        v-if="game.rating"
        class="igdb-score"
      >
        <span class="score-label">Rating:</span>
        <span
          class="score-value"
          :class="getRatingClass(game.rating)"
        >
          {{ game.rating }} / 5
        </span>
      </div>
    </div>
  </button>
</template>

<script setup>
import { computed, defineProps, defineEmits } from 'vue';
import { useGamesStore } from '@/store/games';

const props = defineProps({
  game: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['click']);

const gamesStore = useGamesStore();

// Check if game is in library
const isInLibrary = computed(() => {
  if (typeof props.game.is_in_user_library !== 'undefined') {
    return props.game.is_in_user_library === 1 || props.game.is_in_user_library === true;
  }
  const gameId = props.game.igdbId || props.game.gameId || props.game.id;
  return gameId ? gamesStore.isGameInLibrary(gameId) : false;
});

// Extract release year
const releaseYear = computed(() => {
  const dateStr = props.game.releaseDate || props.game.released || props.game.release_date;
  if (!dateStr) return null;
  return new Date(dateStr).getFullYear();
});

// Get platforms text
const platformsText = computed(() => {
  if (typeof props.game.platforms === 'string') {
    return props.game.platforms;
  }
  if (Array.isArray(props.game.platforms)) {
    return props.game.platforms
      .map(p => typeof p === 'string' ? p : p.platform?.name || p.name)
      .join(', ');
  }
  return '';
});

// Main platform for icon
const mainPlatform = computed(() => {
  const platforms = platformsText.value.toLowerCase();
  if (platforms.includes('playstation') || platforms.includes('ps')) return 'PlayStation';
  if (platforms.includes('xbox')) return 'Xbox';
  if (platforms.includes('nintendo') || platforms.includes('switch')) return 'Nintendo';
  if (platforms.includes('pc') || platforms.includes('windows')) return 'PC';
  if (platforms.includes('linux')) return 'Linux';
  if (platforms.includes('mac')) return 'Mac';
  if (platforms.includes('android') || platforms.includes('ios')) return 'Mobile';
  return null;
});

// Platform icon
const platformIcon = computed(() => {
  switch (mainPlatform.value) {
    case 'PlayStation': return 'fab fa-playstation';
    case 'Xbox': return 'fab fa-xbox';
    case 'Nintendo': return 'fas fa-gamepad';
    case 'PC': return 'fab fa-windows';
    case 'Linux': return 'fab fa-linux';
    case 'Mac': return 'fab fa-apple';
    case 'Mobile': return 'fas fa-mobile-alt';
    default: return 'fas fa-gamepad';
  }
});

const handleClick = () => {
  emit('click', props.game);
};

const truncateText = (text, maxLength) => {
  if (!text) return '';
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};

const getRatingClass = (rating) => {
  if (rating >= 4) return 'score-high';
  if (rating >= 3) return 'score-medium';
  return 'score-low';
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.game-carousel-item {
  @include button-reset;
  flex-shrink: 0;
  width: 160px;
  cursor: pointer;
  border-radius: 10px;
  overflow: hidden;
  background: var(--color-background-card);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: flex;
  flex-direction: column;
}

.game-carousel-item:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

.game-cover-wrapper {
  position: relative;
  width: 160px;
  height: 208px;
  overflow: hidden;
  background: var(--color-background-mute);
}

.game-cover {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.2s ease;
}

.game-carousel-item:hover .game-cover {
  transform: scale(1.05);
}

.game-cover-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-hover));
  color: rgba(255, 255, 255, 0.4);
  font-size: 3rem;
}

.year-badge {
  position: absolute;
  bottom: 6px;
  left: 6px;
  background: var(--color-overlay-strong);
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.rating-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: var(--color-overlay-strong);
  color: var(--color-rating-star);
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
}

.rating-badge i {
  color: var(--color-rating-star);
}

.library-badge {
  position: absolute;
  top: 6px;
  left: 6px;
  background: var(--color-overlay-strong);
  color: var(--color-on-overlay);
  padding: 3px 6px;
  border-radius: 4px;
  font-size: 0.75rem;
}

.status-badge {
  position: absolute;
  bottom: 8px;
  right: 8px;
  background: rgba(23, 162, 184, 0.95);
  color: white;
  padding: 6px 8px;
  border-radius: 4px;
  font-size: 0.9rem;
}

.platform-badge {
  position: absolute;
  bottom: 8px;
  left: 8px;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  padding: 6px 8px;
  border-radius: 4px;
  font-size: 0.9rem;
}

.game-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 8px 10px;
}

.game-title {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
  line-height: 1.3;
  min-height: 2.6em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.igdb-score {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
}

.score-label {
  color: var(--color-text-secondary);
}

.score-value {
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 3px;
}

.score-high {
  background: var(--color-success);
  color: var(--color-background);
}

.score-medium {
  background: var(--color-warning);
  color: var(--color-background);
}

.score-low {
  background: var(--color-error);
  color: var(--color-background);
}

</style>
