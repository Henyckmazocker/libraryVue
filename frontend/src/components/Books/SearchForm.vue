<template>
  <div class="search-form-container">
    <h1 v-if="title" class="title">{{ title }}</h1>
    
    <!-- ISBN Search -->
    <div class="input-group">
      <input 
        type="text" 
        class="search-input" 
        :placeholder="isbnPlaceholder"
        v-model="isbnQuery"
        @keyup.enter="handleIsbnSearch"
        :disabled="loading"
      />
      <button 
        @click="handleIsbnSearch" 
        class="search-button"
        :disabled="loading || !isbnQuery.trim()"
      >
        <i v-if="loading && activeSearch === 'isbn'" class="fas fa-spinner fa-spin"></i>
        <i v-else class="fas fa-search"></i>
        <span class="button-text">ISBN</span>
      </button>
    </div>
    
    <!-- Name Search -->
    <div class="input-group">
      <input 
        type="text" 
        class="search-input" 
        :placeholder="namePlaceholder"
        v-model="nameQuery"
        @keyup.enter="handleNameSearch"
        :disabled="loading"
      />
      <button 
        @click="handleNameSearch" 
        class="search-button"
        :disabled="loading || !nameQuery.trim()"
      >
        <i v-if="loading && activeSearch === 'name'" class="fas fa-spinner fa-spin"></i>
        <i v-else class="fas fa-search"></i>
        <span class="button-text">Nombre</span>
      </button>
    </div>
    
    <!-- Clear button -->
    <div v-if="showClear && (isbnQuery || nameQuery)" class="clear-container">
      <button @click="clearSearch" class="clear-button">
        <i class="fas fa-times"></i>
        Limpiar
      </button>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, defineExpose, ref } from 'vue';

// Props
const props = defineProps({
  title: {
    type: String,
    default: ''
  },
  isbnPlaceholder: {
    type: String,
    default: 'Enter ISBN manually'
  },
  namePlaceholder: {
    type: String,
    default: 'Buscar por nombre de libro'
  },
  loading: {
    type: Boolean,
    default: false
  },
  showClear: {
    type: Boolean,
    default: true
  }
});

// Emits
const emit = defineEmits(['isbn-search', 'name-search', 'clear-search']);

// Reactive data
const isbnQuery = ref('');
const nameQuery = ref('');
const activeSearch = ref('');

// Methods
const handleIsbnSearch = () => {
  if (!isbnQuery.value.trim() || props.loading) return;
  
  activeSearch.value = 'isbn';
  emit('isbn-search', isbnQuery.value.trim());
};

const handleNameSearch = () => {
  if (!nameQuery.value.trim() || props.loading) return;
  
  activeSearch.value = 'name';
  emit('name-search', nameQuery.value.trim());
};

const clearSearch = () => {
  isbnQuery.value = '';
  nameQuery.value = '';
  activeSearch.value = '';
  emit('clear-search');
};

// Expose methods for parent components
defineExpose({
  clearSearch,
  setIsbnQuery: (query) => { isbnQuery.value = query; },
  setNameQuery: (query) => { nameQuery.value = query; }
});
</script>

<style scoped>
.search-form-container {
  margin-bottom: 20px;
}

.title {
  color: #e0e0e0;
  font-size: 1.8rem;
  font-weight: 600;
  margin-bottom: 25px;
  text-align: center;
}

.input-group {
  display: flex;
  margin-bottom: 15px;
  gap: 10px;
}

.search-input {
  flex: 1;
  padding: 12px 15px;
  font-size: 1rem;
  border: 1px solid #555;
  border-radius: 12px;
  background-color: #3a3a3a;
  color: #e0e0e0;
  transition: all 0.2s ease;
}

.search-input:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
}

.search-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.search-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  font-size: 1rem;
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 500;
  min-width: 100px;
}

.search-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #0056b3, #004085);
  transform: translateY(-1px);
}

.search-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.button-text {
  font-weight: 500;
}

.clear-container {
  display: flex;
  justify-content: center;
  margin-top: 10px;
}

.clear-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  font-size: 0.9rem;
  background: transparent;
  color: #888;
  border: 1px solid #555;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.clear-button:hover {
  color: #e0e0e0;
  border-color: #888;
  background: rgba(255, 255, 255, 0.05);
}

/* Responsive design */
@media (max-width: 768px) {
  .input-group {
    flex-direction: column;
  }
  
  .search-button {
    justify-content: center;
  }
  
  .title {
    font-size: 1.5rem;
  }
}
</style>
