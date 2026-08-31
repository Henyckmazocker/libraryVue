<template>
  <div class="trending-section">
    <div class="trending-header">
      <h2 class="trending-title">
        {{ title }}
      </h2>
    </div>

    <!-- Carga: el carril de siluetas evita que la sección aparezca vacía y luego
         salte de golpe al llegar los datos. -->
    <MediaSkeleton
      v-if="isLoading"
      variant="carousel"
      :count="6"
      :label="`Cargando ${title}…`"
    />

    <!-- Empty State -->
    <EmptyState
      v-else-if="items.length === 0"
      icon="fas fa-fire"
      title="No hay contenido trending disponible"
    />

    <!-- Carousel -->
    <div
      v-else
      class="trending-carousel"
    >
      <HorizontalCarousel
        :show-navigation="true"
        :scroll-amount="300"
      >
        <component
          :is="itemComponent"
          v-for="item in items"
          :key="getItemKey(item)"
          :book="type === 'books' ? item : undefined"
          :movie="type === 'movies' ? item : undefined"
          :game="type === 'games' ? item : undefined"
          :album="type === 'albums' ? item : undefined"
          :video="type === 'videos' ? item : undefined"
          @click="handleItemClick(item)"
        />
      </HorizontalCarousel>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';
import EmptyState from '@/components/common/EmptyState.vue'
import HorizontalCarousel from '@/components/shared/HorizontalCarousel.vue';
import MediaSkeleton from '@/components/shared/MediaSkeleton.vue';

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
    validator: (value) => ['books', 'movies', 'games', 'albums', 'videos'].includes(value)
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
  } else if (props.type === 'videos') {
    return item.youtube_id || item.youtubeId || item.id || item.title;
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

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.trending-section {
  width: 100%;
  margin: spacing(xl) 0;
}

.trending-header {
  margin-bottom: spacing(md);
}

.trending-title {
  font-size: var(--font-size-2xl);
  font-weight: 600;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: spacing(sm);
  margin: 0 0 spacing(xs) 0;
}

.trending-subtitle {
  font-size: var(--font-size-base);
  color: var(--color-text-secondary);
  margin: 0;
}

/* Empty State */
/* Carousel Container */
.trending-carousel {
  position: relative;
}

@include responsive-below(md) {
  .trending-title {
    font-size: var(--font-size-xl);
  }
  
  .trending-subtitle {
    font-size: var(--font-size-sm);
  }
}

@include responsive-below(sm) {
  .trending-title {
    font-size: var(--font-size-lg);
  }
  
  .trending-info {
    font-size: var(--font-size-xs);
    padding: spacing(xs) spacing(sm);
  }
}
</style>
