<template>
  <div class="book-list-item" @click="handleClick">
    <img 
      v-if="book.coverUrl && !imageError" 
      :src="book.coverUrl" 
      alt="Portada" 
      class="book-list-poster"
      @error="handleImageError"
      loading="lazy"
    />
    <div v-else class="book-list-poster-placeholder">
      <i class="fas fa-book"></i>
    </div>
    
    <div class="book-list-info">
      <div class="book-list-title">{{ book.title }}</div>
      <div class="book-list-subtitle">{{ book.author || 'Autor desconocido' }}</div>
      
      <!-- Rating (solo si existe) -->
      <div v-if="book.user_rating && book.user_rating > 0" class="book-list-rating">
        <RatingComponent
          :rating="book.user_rating"
          :readonly="true"
          :size="'small'"
        />
      </div>
      
      <!-- Status actual (solo si existe) -->
      <div v-if="book.userStatuses && book.userStatuses.length > 0" class="book-list-status">
        <span 
          v-for="status in book.userStatuses" 
          :key="status" 
          class="status-badge"
        >
          {{ getStatusLabel(status) }}
        </span>
      </div>
    </div>
    
    <i class="fas fa-chevron-right book-list-arrow"></i>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits } from 'vue';
import RatingComponent from '@/components/common/RatingComponent.vue';

const props = defineProps({
  book: {
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
  emit('click', props.book);
};

const handleImageError = () => {
  imageError.value = true;
};

// Función para obtener el label legible del status
const getStatusLabel = (statusKey) => {
  const status = props.allowedStatuses.find(s => s.key === statusKey);
  return status ? status.name : statusKey;
};
</script>

<style scoped>
.book-list-item {
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

.book-list-item:hover {
  background: var(--color-background-mute);
  border-color: var(--color-primary);
  transform: translateX(4px);
}

.book-list-poster {
  width: 50px;
  height: 75px;
  object-fit: cover;
  border-radius: 4px;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.book-list-poster-placeholder {
  width: 50px;
  height: 75px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-background);
  border: 2px dashed var(--color-border);
  border-radius: 4px;
  flex-shrink: 0;
}

.book-list-poster-placeholder i {
  font-size: 1.5rem;
  color: var(--color-text-muted);
}

.book-list-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.book-list-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.book-list-subtitle {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.book-list-rating {
  margin-top: 4px;
}

.book-list-status {
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

.book-list-arrow {
  font-size: 1rem;
  color: var(--color-text-muted);
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.book-list-item:hover .book-list-arrow {
  transform: translateX(4px);
  color: var(--color-primary);
}

/* Responsive */
@media (max-width: 768px) {
  .book-list-item {
    padding: 10px;
    gap: 12px;
  }

  .book-list-poster,
  .book-list-poster-placeholder {
    width: 40px;
    height: 60px;
  }

  .book-list-title {
    font-size: 0.9rem;
  }

  .book-list-subtitle {
    font-size: 0.8rem;
  }
}
</style>
