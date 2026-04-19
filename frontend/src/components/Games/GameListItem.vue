<template>
  <div class="game-list-item" @click="handleClick">
    <img 
      v-if="game.coverUrl && !imageError" 
      :src="game.coverUrl" 
      alt="Game Cover" 
      class="game-list-cover"
      @error="handleImageError"
      loading="lazy"
    />
    <div v-else class="game-list-cover-placeholder">
      <i class="fas fa-gamepad"></i>
    </div>
    
    <div class="game-list-info">
      <div class="game-list-title">{{ game.title || game.name }}</div>
      <div class="game-list-subtitle">
        {{ getDeveloperOrYear() }}
      </div>
      
      <!-- Platforms -->
      <div v-if="platformsText" class="game-list-platforms">
        <i :class="getPlatformIcon()"></i>
        <span>{{ platformsText }}</span>
      </div>
      
      <!-- Rating (solo si existe) -->
      <div v-if="game.user_rating && game.user_rating > 0" class="game-list-rating">
        <RatingComponent
          :rating="game.user_rating"
          :readonly="true"
          :size="'small'"
        />
      </div>
      
      <!-- Status actual (solo si existe) -->
      <div v-if="game.userStatuses && game.userStatuses.length > 0" class="game-list-status">
        <span 
          v-for="status in game.userStatuses" 
          :key="status" 
          class="status-badge"
        >
          {{ getStatusLabel(status) }}
        </span>
      </div>
    </div>
    
    <i class="fas fa-chevron-right game-list-arrow"></i>
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

// Obtener desarrollador o año
const getDeveloperOrYear = () => {
  const developer = props.game.developer || 
    (Array.isArray(props.game.developers) && props.game.developers.length > 0 
      ? props.game.developers[0].name || props.game.developers[0]
      : '');
  
  const year = props.game.releaseDate 
    ? new Date(props.game.releaseDate).getFullYear()
    : (props.game.released ? new Date(props.game.released).getFullYear() : '');
  
  if (developer && year) return `${developer} • ${year}`;
  if (developer) return developer;
  if (year) return year;
  return 'Desarrollador desconocido';
};

// Obtener texto de plataformas
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

// Obtener icono de plataforma principal
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

// Función para obtener el label legible del status
const getStatusLabel = (statusKey) => {
  const status = props.allowedStatuses.find(s => s.key === statusKey);
  return status ? status.name : statusKey;
};
</script>

<style scoped>
.game-list-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 12px 15px;
  background: var(--color-background-soft);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid transparent;
}

.game-list-item:hover {
  background: var(--color-background-mute);
  border-color: var(--color-primary);
  transform: translateX(4px);
}

.game-list-cover {
  width: 60px;
  height: 60px;
  object-fit: cover;
  border-radius: 6px;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.game-list-cover-placeholder {
  width: 60px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 6px;
  flex-shrink: 0;
}

.game-list-cover-placeholder i {
  font-size: 1.8rem;
  color: white;
}

.game-list-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.game-list-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.game-list-subtitle {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.game-list-platforms {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: var(--color-text-muted);
  margin-top: 2px;
}

.game-list-platforms i {
  font-size: 0.9rem;
}

.game-list-platforms span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.game-list-rating {
  margin-top: 4px;
}

.game-list-status {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-top: 4px;
}

.status-badge {
  display: inline-block;
  padding: 2px 8px;
  font-size: 0.75rem;
  font-weight: 500;
  background: var(--color-primary);
  color: white;
  border-radius: 12px;
  text-transform: capitalize;
}

.game-list-arrow {
  font-size: 1rem;
  color: var(--color-text-muted);
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.game-list-item:hover .game-list-arrow {
  transform: translateX(4px);
  color: var(--color-primary);
}

/* Responsive */
@media (max-width: 768px) {
  .game-list-item {
    padding: 10px;
    gap: 12px;
  }

  .game-list-cover,
  .game-list-cover-placeholder {
    width: 50px;
    height: 50px;
  }

  .game-list-cover-placeholder i {
    font-size: 1.5rem;
  }

  .game-list-title {
    font-size: 0.9rem;
  }

  .game-list-subtitle {
    font-size: 0.8rem;
  }

  .game-list-platforms {
    font-size: 0.75rem;
  }
}
</style>
