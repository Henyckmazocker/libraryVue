<template>
  <div class="game-carousel-item" @click="handleClick">
    <div class="game-cover-wrapper">
      <img 
        v-if="game.coverUrl || game.background_image" 
        :src="game.coverUrl || game.background_image" 
        :alt="game.title || game.name"
        class="game-cover" 
        loading="lazy"
      />
      <div v-else class="game-cover-placeholder">
        <i class="fas fa-gamepad"></i>
      </div>
      
      <!-- Badge de año -->
      <div v-if="releaseYear" class="year-badge">
        {{ releaseYear }}
      </div>
      
      <!-- Badge de rating si existe -->
      <div v-if="game.user_rating && game.user_rating > 0" class="rating-badge">
        <i class="fas fa-star"></i>
        <span>{{ game.user_rating }}</span>
      </div>
      
      <!-- Badge de "En tu biblioteca" -->
      <div v-if="isInLibrary" class="library-badge" title="En tu biblioteca">
        <i class="fas fa-bookmark"></i>
      </div>
      
      <!-- Badge de status si existe -->
      <div v-if="game.userStatuses && game.userStatuses.length > 0 && !isInLibrary" class="status-badge">
        <i class="fas fa-check-circle"></i>
      </div>
      
      <!-- Badge de plataforma principal -->
      <div v-if="mainPlatform" class="platform-badge" :title="platformsText">
        <i :class="platformIcon"></i>
      </div>
    </div>
    
    <div class="game-info">
      <h3 class="game-title">{{ truncateText(game.title || game.name, 40) }}</h3>
      <div v-if="game.rating" class="igdb-score">
        <span class="score-label">Rating:</span>
        <span class="score-value" :class="getRatingClass(game.rating)">
          {{ game.rating }} / 5
        </span>
      </div>
    </div>
  </div>
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

<style scoped>
@import '@/assets/styles/variables.css';

.game-carousel-item {
  flex-shrink: 0;
  width: 200px;
  cursor: pointer;
  transition: transform 0.2s ease;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.game-carousel-item:hover {
  transform: translateY(-5px);
}

.game-cover-wrapper {
  position: relative;
  width: 200px;
  height: 260px;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
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
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  font-size: 3rem;
}

.year-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: rgba(0, 0, 0, 0.7);
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
  background: rgba(255, 193, 7, 0.95);
  color: #000;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
}

.rating-badge i {
  color: #000;
}

.library-badge {
  position: absolute;
  bottom: 8px;
  right: 8px;
  background: rgba(40, 167, 69, 0.95);
  color: white;
  padding: 6px 8px;
  border-radius: 4px;
  font-size: 0.9rem;
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
  gap: 6px;
}

.game-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-primary, #333);
  margin: 0;
  line-height: 1.3;
  min-height: 2.6em;
}

.igdb-score {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
}

.score-label {
  color: var(--text-secondary, #666);
}

.score-value {
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 3px;
}

.score-high {
  background: #6c3;
  color: white;
}

.score-medium {
  background: #fc3;
  color: #333;
}

.score-low {
  background: #f00;
  color: white;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
  .game-title {
    color: var(--text-primary, #e0e0e0);
  }
  
  .score-label {
    color: var(--text-secondary, #aaa);
  }
}
</style>
