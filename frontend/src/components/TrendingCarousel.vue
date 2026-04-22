<template>
  <div class="trending-section">
    <div class="trending-header">
      <h2 class="trending-title">
        {{ title }}
      </h2>
    </div>

    <!-- Empty State -->
    <div v-if="items.length === 0" class="trending-empty">
      <span>No hay contenido trending disponible</span>
    </div>

    <!-- Carousel -->
    <div v-else class="trending-carousel">
      <HorizontalCarousel :show-navigation="true" :scroll-amount="300">
        <component
          v-for="item in items"
          :key="getItemKey(item)"
          :is="itemComponent"
          :book="type === 'books' ? item : undefined"
          :movie="type === 'movies' ? item : undefined"
          :game="type === 'games' ? item : undefined"
          :album="type === 'albums' ? item : undefined"
          @click="handleItemClick(item)"
        />
      </HorizontalCarousel>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';
import HorizontalCarousel from '@/components/shared/HorizontalCarousel.vue';

const props = defineProps({
  // Datos
  items: {
    type: Array,
    default: () => []
  },
  
  // Estado
  isLoading: {
    type: Boolean,
    default: false
  },
  
  error: {
    type: String,
    default: null
  },
  
  // Configuración
  type: {
    type: String,
    required: true,
    validator: (value) => ['books', 'movies', 'games', 'albums'].includes(value)
  },
  
  itemComponent: {
    type: Object,
    required: true
  },
  
  // Personalización
  title: {
    type: String,
    default: 'Tendencias Locales'
  },
  
  subtitle: {
    type: String,
    default: null
  },
  
  showTrendingBadge: {
    type: Boolean,
    default: true
  }
});

const emit = defineEmits(['item-click']);

/**
 * Obtiene la key única para cada item del carousel
 */
const getItemKey = (item) => {
  if (props.type === 'books') {
    return item.isbn;
  } else if (props.type === 'games') {
    return item.igdbId || item.gameId || item.id || item.title;
  } else if (props.type === 'albums') {
    return item.spotify_id || item.spotifyId || item.id || item.title;
  } else {
    // Para películas, el campo es 'isbn' (que es el imdb_id)
    return item.isbn || item.imdb_id || item.title;
  }
};

/**
 * Maneja el click en un item del carousel
 */
const handleItemClick = (item) => {
  emit('item-click', item);
};
</script>

<style scoped>
.trending-section {
  width: 100%;
  margin: 30px 0;
}

.trending-header {
  margin-bottom: 20px;
}

.trending-title {
  font-size: 1.8rem;
  font-weight: 600;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0 0 8px 0;
}

.trending-subtitle {
  font-size: 0.95rem;
  color: var(--color-text-secondary);
  margin: 0;
}

/* Empty State */
.trending-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 40px;
  background: var(--color-background-mute);
  border-radius: 20px;
  color: var(--color-text-secondary);
  font-style: italic;
  border: 1px solid var(--color-border);
}

.trending-empty i {
  font-size: 1.3rem;
}

/* Carousel Container */
.trending-carousel {
  position: relative;
}

@media (max-width: 768px) {
  .trending-title {
    font-size: 1.5rem;
  }
  
  .trending-subtitle {
    font-size: 0.9rem;
  }
  
  .trending-empty {
    padding: 30px 20px;
    font-size: 0.9rem;
  }
}

@media (max-width: 480px) {
  .trending-title {
    font-size: 1.3rem;
  }
  
  .trending-info {
    font-size: 0.8rem;
    padding: 6px 12px;
  }
}
</style>
