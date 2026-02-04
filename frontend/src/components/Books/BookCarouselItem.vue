<template>
  <div class="book-carousel-item" @click="handleClick">
    <div class="book-cover-wrapper">
      <img 
        v-if="book.coverUrl" 
        :src="book.coverUrl" 
        :alt="book.title"
        class="book-cover" 
        loading="lazy"
      />
      <div v-else class="book-cover-placeholder">
        <i class="fas fa-book"></i>
      </div>
      
      <!-- Badge de rating si existe -->
      <div v-if="book.user_rating && book.user_rating > 0" class="rating-badge">
        <i class="fas fa-star"></i>
        <span>{{ book.user_rating }}</span>
      </div>
      
      <!-- Badge de "En tu biblioteca" -->
      <div v-if="isInLibrary" class="library-badge" title="En tu biblioteca">
        <i class="fas fa-bookmark"></i>
      </div>
      
      <!-- Badge de status si existe (para compatibilidad) -->
      <div v-if="book.userStatuses && book.userStatuses.length > 0 && !isInLibrary" class="status-badge">
        <i class="fas fa-check-circle"></i>
      </div>
    </div>
    
    <div class="book-info">
      <h3 class="book-title">{{ truncateText(book.title, 40) }}</h3>
      <p class="book-author">{{ truncateText(book.author || 'Autor desconocido', 30) }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed, defineProps, defineEmits } from 'vue';
import { useBooksStore } from '@/store/books';

const props = defineProps({
  book: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['click']);

const booksStore = useBooksStore();

// Check if book is in library (from trending API or store check)
const isInLibrary = computed(() => {
  // If trending API provided the field, use it
  if (typeof props.book.is_in_user_library !== 'undefined') {
    return props.book.is_in_user_library === 1 || props.book.is_in_user_library === true;
  }
  // Otherwise check the store (for search results)
  return props.book.isbn ? booksStore.isBookInLibrary(props.book.isbn) : false;
});

const handleClick = () => {
  emit('click', props.book);
};

const truncateText = (text, maxLength) => {
  if (!text) return '';
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};
</script>

<style scoped>
@import '@/assets/styles/variables.css';

.book-carousel-item {
  flex-shrink: 0;
  width: 150px;
  cursor: pointer;
  transition: transform 0.2s ease;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.book-carousel-item:hover {
  transform: translateY(-5px);
}

.book-cover-wrapper {
  position: relative;
  width: 150px;
  height: 225px;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transition: box-shadow 0.2s ease;
}

.book-carousel-item:hover .book-cover-wrapper {
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
}

.book-cover {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.book-cover-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--color-background-mute) 0%, var(--color-background-soft) 100%);
  border: 2px dashed var(--color-border);
}

.book-cover-placeholder i {
  font-size: 3rem;
  color: var(--color-text-muted);
  opacity: 0.5;
}

.rating-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(255, 193, 7, 0.95);
  color: #000;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 3px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.rating-badge i {
  font-size: 0.7rem;
}

.status-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: rgba(76, 175, 80, 0.95);
  color: white;
  padding: 4px 8px;
  border-radius: 50%;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.library-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: rgba(33, 150, 243, 0.95);
  color: white;
  padding: 6px 8px;
  border-radius: 6px;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(4px);
  transition: transform 0.2s ease;
}

.library-badge:hover {
  transform: scale(1.1);
}

.book-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  text-align: center;
  padding: 0 5px;
}

.book-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-heading);
  line-height: 1.3;
  margin: 0;
  min-height: 2.6em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.book-author {
  font-size: 0.8rem;
  color: var(--color-text-secondary);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Responsive */
@media (max-width: 768px) {
  .book-carousel-item {
    width: 130px;
  }
  
  .book-cover-wrapper {
    width: 130px;
    height: 195px;
  }
  
  .book-title {
    font-size: 0.85rem;
  }
  
  .book-author {
    font-size: 0.75rem;
  }
}

@media (max-width: 480px) {
  .book-carousel-item {
    width: 110px;
  }
  
  .book-cover-wrapper {
    width: 110px;
    height: 165px;
  }
  
  .book-cover-placeholder i {
    font-size: 2rem;
  }
  
  .book-title {
    font-size: 0.8rem;
  }
  
  .book-author {
    font-size: 0.7rem;
  }
}
</style>
