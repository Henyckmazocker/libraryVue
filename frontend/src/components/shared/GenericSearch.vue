<template>
  <div class="generic-search-container">
    <h1 class="title">
      {{ config.title }}
    </h1>
    
    <!-- Input groups dinámicos basados en configuración -->
    <div
      v-for="(input, index) in config.inputs"
      :key="index"
      class="input-group"
    >
      <input 
        v-model="inputValues[index]" 
        type="text" 
        class="search-input" 
        :aria-label="input.placeholder" 
        :placeholder="input.placeholder" 
        @keyup.enter="() => handleSearch(input, index)"
      >
      <button
        class="search-button"
        @click="() => handleSearch(input, index)"
      >
        <i class="fas fa-search" />
        <span
          v-if="input.buttonText"
          class="button-text"
        >{{ input.buttonText }}</span>
      </button>
    </div>
    
    <div
      v-if="errorMessage"
      class="error-message"
    >
      {{ errorMessage }}
    </div>

    <!-- Resultados en carrusel horizontal -->
    <div
      v-if="results && results.length"
      class="results-section"
    >
      <h2 class="results-title">
        Resultados de búsqueda ({{ results.length }})
      </h2>
      <HorizontalCarousel>
        <component
          :is="config.carouselItemComponent"
          v-for="result in results"
          :key="getResultKey(result)"
          :[config.itemProp]="result"
          @click="(item) => handleItemClick(item)"
        />
      </HorizontalCarousel>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import HorizontalCarousel from '@/components/shared/HorizontalCarousel.vue';
import Logger from '@/utils/logger';

// Props
// eslint-disable-next-line no-undef
const props = defineProps({
  config: {
    type: Object,
    required: true,
    validator: (config) => {
      return (
        config.title &&
        Array.isArray(config.inputs) &&
        config.carouselItemComponent &&
        config.itemProp &&
        typeof config.searchHandler === 'function' &&
        typeof config.transformResult === 'function' &&
        typeof config.navigateToDetail === 'function' &&
        typeof config.getResultKey === 'function' &&
        typeof config.fetchAllowedStatuses === 'function'
      );
    }
  }
});

// Composables
const router = useRouter();

// Estados locales
const inputValues = ref(props.config.inputs.map(() => ''));
const results = ref([]);
const errorMessage = ref('');
const allowedStatuses = ref([]);

// Métodos
const handleSearch = async (input, index) => {
  errorMessage.value = '';
  const query = inputValues.value[index].trim();
  
  if (!query) {
    errorMessage.value = input.emptyMessage || 'Por favor introduce un valor para buscar.';
    return;
  }
  
  try {
    // Detectar automáticamente el tipo de búsqueda si hay una función de detección
    let searchType = input.type;
    let shouldNavigateDirect = false;
    
    if (props.config.detectSearchType) {
      const detection = props.config.detectSearchType(query);
      searchType = detection.type;
      shouldNavigateDirect = detection.isDirect;
      
      Logger.debug(`[GenericSearch] Auto-detected search type: ${searchType}, direct: ${shouldNavigateDirect}`);
    }
    
    Logger.debug(`[GenericSearch] Searching with query: ${query}, type: ${searchType}`);
    
    // Si es navegación directa (ISBN o IMDb ID válido), navegar sin búsqueda
    if (shouldNavigateDirect || input.type === 'direct') {
      const navData = input.idField ? { [input.idField]: query } : { id: query };
      props.config.navigateToDetail(router, navData);
      return;
    }
    
    // Para búsquedas normales, usar el handler de búsqueda
    const searchResults = await props.config.searchHandler(query, searchType);
    
    if (!searchResults || searchResults.length === 0) {
      errorMessage.value = 'No se encontraron resultados.';
      results.value = [];
      return;
    }
    
    results.value = searchResults.map(props.config.transformResult);
    Logger.debug(`[GenericSearch] Found ${results.value.length} results`);
  } catch (error) {
    Logger.error('[GenericSearch] Search error:', error);
    errorMessage.value = input.errorMessage || 'Error al realizar la búsqueda.';
    results.value = [];
  }
};

const handleItemClick = (item) => {
  props.config.navigateToDetail(router, item);
};

const getResultKey = (result) => {
  return props.config.getResultKey(result);
};

// Lifecycle
onMounted(async () => {
  try {
    Logger.debug('[GenericSearch] Component mounted, fetching allowed statuses...');
    allowedStatuses.value = await props.config.fetchAllowedStatuses();
    Logger.debug('[GenericSearch] Allowed statuses loaded:', allowedStatuses.value);
  } catch (error) {
    Logger.error('[GenericSearch] Error loading allowed statuses:', error);
  }
});
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.generic-search-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 30px;
  width: 100%;
  max-width: 100%;
}

.title {
  font-size: 2rem;
  color: var(--color-text);
  margin-bottom: 30px;
  text-align: center;
}

.input-group {
  display: flex;
  width: 100%;
  max-width: 600px;
  margin-bottom: 30px;
}

.search-input {
  flex-grow: 1;
  padding: 12px 18px;
  font-size: 1rem;
  color: var(--color-text);
  background-color: var(--color-background-mute);
  border: 1px solid var(--color-border);
  border-radius: 30px 0 0 30px;
  outline: none;
  transition: border-color 0.2s ease;
}

.search-input::placeholder {
  color: var(--color-text-muted);
}

.search-input:focus {
  border-color: var(--color-primary);
}

.search-button {
  padding: 12px 24px;
  font-size: 1rem;
  color: var(--color-text-light);
  background-color: var(--color-primary);
  border: 1px solid var(--color-primary);
  border-radius: 0 30px 30px 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.2s ease;
}

.search-button:hover {
  background-color: var(--color-primary-hover);
  border-color: var(--color-primary-hover);
}

.button-text {
  font-size: 0.9rem;
  font-weight: 500;
}

.error-message {
  padding: 10px 15px;
  border-radius: 12px;
  margin-bottom: 20px;
  width: 100%;
  text-align: center;
  box-sizing: border-box;
  color: var(--color-error);
  background-color: var(--color-error-bg);
}

.results-section {
  width: 100%;
  max-width: 100%;
  margin-top: 30px;
}

.results-title {
  font-size: 1.3rem;
  color: var(--color-heading);
  margin-bottom: 15px;
  font-weight: 600;
  text-align: left;
  padding: 0 20px;
}

/* Responsive design */
@include responsive-below(md) {
  .generic-search-container {
    padding: 20px;
    max-width: 100%;
  }
  
  .title {
    font-size: 1.8rem;
    margin-bottom: 20px;
  }
  
  .search-button {
    padding: 12px 18px;
  }
  
  .button-text {
    display: none;
  }
  
  .results-title {
    font-size: 1.1rem;
    padding: 0 10px;
  }
}
</style>
