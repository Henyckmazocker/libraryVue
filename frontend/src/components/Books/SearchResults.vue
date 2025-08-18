<template>
  <div v-if="results.length > 0" class="search-results-container">
    <h3 class="results-title">{{ title }}</h3>
    <div class="results-list">
      <div 
        v-for="(item, index) in results" 
        :key="getItemKey(item, index)" 
        class="result-card"
        :class="{ 'selected': selectedIndex === index }"
      >
        <div class="result-info">
          <div class="result-title">{{ getItemTitle(item) }}</div>
          <div class="result-author">{{ getItemAuthor(item) }}</div>
          <div v-if="getItemPublisher(item)" class="result-publishers">
            <span class="result-pub-label">Editorial:</span>
            <span class="result-pub-list">{{ getItemPublisher(item) }}</span>
          </div>
          <div v-if="getItemISBN(item)" class="result-isbn">
            <span class="result-isbn-label">ISBN:</span>
            <span class="result-isbn-value">{{ getItemISBN(item) }}</span>
          </div>
          <div v-if="getItemYear(item)" class="result-year">
            <span class="result-year-label">Año:</span>
            <span class="result-year-value">{{ getItemYear(item) }}</span>
          </div>
        </div>
        <div class="result-actions">
          <button 
            class="result-details-btn" 
            @click="selectItem(item, index)"
            :title="actionButtonTitle"
          >
            <i :class="actionButtonIcon"></i>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Pagination if needed -->
    <div v-if="showPagination && totalPages > 1" class="pagination-container">
      <button 
        @click="goToPage(currentPage - 1)"
        :disabled="currentPage <= 1"
        class="pagination-btn"
      >
        <i class="fas fa-chevron-left"></i>
      </button>
      
      <span class="pagination-info">
        Página {{ currentPage }} de {{ totalPages }}
      </span>
      
      <button 
        @click="goToPage(currentPage + 1)"
        :disabled="currentPage >= totalPages"
        class="pagination-btn"
      >
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  </div>
  
  <!-- Empty state -->
  <div v-else-if="showEmptyState" class="empty-results">
    <i class="fas fa-search empty-icon"></i>
    <p class="empty-message">{{ emptyMessage }}</p>
  </div>
</template>

<script setup>
import { ref, computed, defineProps, defineEmits, defineExpose } from 'vue';

// Props
const props = defineProps({
  results: {
    type: Array,
    default: () => []
  },
  title: {
    type: String,
    default: 'Resultados de búsqueda:'
  },
  actionButtonIcon: {
    type: String,
    default: 'fas fa-eye'
  },
  actionButtonTitle: {
    type: String,
    default: 'Ver detalles'
  },
  showPagination: {
    type: Boolean,
    default: false
  },
  itemsPerPage: {
    type: Number,
    default: 10
  },
  showEmptyState: {
    type: Boolean,
    default: false
  },
  emptyMessage: {
    type: String,
    default: 'No se encontraron resultados'
  },
  itemType: {
    type: String,
    default: 'book', // 'book' | 'movie' | 'generic'
    validator: (value) => ['book', 'movie', 'generic'].includes(value)
  }
});

// Emits
const emit = defineEmits(['select-item', 'page-changed']);

// Reactive data
const selectedIndex = ref(-1);
const currentPage = ref(1);

// Computed
const totalPages = computed(() => {
  return Math.ceil(props.results.length / props.itemsPerPage);
});

// Methods
const getItemKey = (item, index) => {
  // Generate a unique key for each item
  return item.key || item.id || item.isbn || `item-${index}`;
};

const getItemTitle = (item) => {
  if (props.itemType === 'book') {
    return `${item.title || 'Sin título'} (${getItemISBN(item) || 'Sin ISBN'})`;
  } else if (props.itemType === 'movie') {
    return item.title || item.name || 'Sin título';
  }
  return item.title || item.name || 'Sin título';
};

const getItemAuthor = (item) => {
  if (props.itemType === 'book') {
    return Array.isArray(item.author) ? item.author.join(', ') : (item.author || 'Autor desconocido');
  } else if (props.itemType === 'movie') {
    return item.director || 'Director desconocido';
  }
  return item.author || item.creator || 'Autor desconocido';
};

const getItemPublisher = (item) => {
  if (Array.isArray(item.publisher)) {
    return item.publisher.join(', ');
  }
  return item.publisher || '';
};

const getItemISBN = (item) => {
  return item.isbn || '';
};

const getItemYear = (item) => {
  return item.year || item.publicationDate || item.publishedDate || '';
};

const selectItem = (item, index) => {
  selectedIndex.value = index;
  emit('select-item', item, index);
};

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    emit('page-changed', page);
  }
};

// Expose methods for parent components
defineExpose({
  clearSelection: () => { selectedIndex.value = -1; },
  selectFirst: () => {
    if (props.results.length > 0) {
      selectItem(props.results[0], 0);
    }
  }
});
</script>

<style scoped>
.search-results-container {
  margin: 20px 0;
}

.results-title {
  color: #e0e0e0;
  font-size: 1.2rem;
  font-weight: 600;
  margin-bottom: 15px;
}

.results-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.result-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px;
  background: #3a3a3a;
  border: 1px solid #555;
  border-radius: 12px;
  transition: all 0.2s ease;
  cursor: pointer;
}

.result-card:hover {
  background: #404040;
  border-color: #007bff;
  transform: translateY(-1px);
}

.result-card.selected {
  border-color: #007bff;
  background: #404040;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
}

.result-info {
  flex: 1;
}

.result-title {
  font-weight: 600;
  color: #e0e0e0;
  margin-bottom: 5px;
  font-size: 1rem;
}

.result-author {
  color: #ccc;
  margin-bottom: 3px;
  font-size: 0.9rem;
}

.result-publishers,
.result-isbn,
.result-year {
  color: #aaa;
  font-size: 0.8rem;
  margin-bottom: 2px;
}

.result-pub-label,
.result-isbn-label,
.result-year-label {
  font-weight: 500;
}

.result-actions {
  margin-left: 15px;
}

.result-details-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 1rem;
}

.result-details-btn:hover {
  background: linear-gradient(135deg, #0056b3, #004085);
  transform: scale(1.05);
}

.pagination-container {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  margin-top: 20px;
}

.pagination-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: #3a3a3a;
  color: #e0e0e0;
  border: 1px solid #555;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.pagination-btn:hover:not(:disabled) {
  background: #007bff;
  border-color: #007bff;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-info {
  color: #e0e0e0;
  font-size: 0.9rem;
}

.empty-results {
  text-align: center;
  padding: 40px 20px;
  color: #888;
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 15px;
  opacity: 0.5;
}

.empty-message {
  font-size: 1.1rem;
  margin: 0;
}

/* Responsive design */
@media (max-width: 768px) {
  .result-card {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
  
  .result-actions {
    margin-left: 0;
    align-self: center;
  }
  
  .pagination-container {
    flex-wrap: wrap;
    gap: 10px;
  }
}
</style>
