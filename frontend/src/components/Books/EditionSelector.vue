<template>
  <div class="edition-selector">
    <div class="section-header">
      <h2 class="section-title">
        <i class="fas fa-layer-group" />
        Ediciones Disponibles
      </h2>
    </div>

    <!-- Filtros -->
    <div class="filters-section">
      <button 
        class="filters-toggle-btn" 
        :class="{ active: showFilters }"
        @click="toggleFilters"
      >
        <i class="fas fa-filter" />
        <span>{{ showFilters ? 'Ocultar' : 'Mostrar' }} Filtros</span>
        <i
          class="fas"
          :class="showFilters ? 'fa-chevron-up' : 'fa-chevron-down'"
        />
      </button>

      <transition name="slide-fade">
        <div
          v-if="showFilters"
          class="filters-container"
        >
          <!-- Idioma -->
          <div class="filter-group">
            <label
              class="filter-label"
              for="edition-filter-language"
            >
              <i class="fas fa-globe" />
              Idioma
            </label>
            <select
              id="edition-filter-language"
              v-model="filters.language"
              class="filter-select"
            >
              <option value="">
                Todos los idiomas
              </option>
              <option
                v-for="lang in availableLanguages"
                :key="lang.code"
                :value="lang.code"
              >
                {{ lang.name }} ({{ lang.count }})
              </option>
            </select>
          </div>

          <!-- Año de publicación -->
          <div class="filter-group">
            <!-- Dos inputs bajo una etiqueta: el <label> no puede apuntar a los dos,
                 así que cada uno lleva su propio nombre accesible. -->
            <span
              id="edition-filter-year"
              class="filter-label"
            >
              <i class="fas fa-calendar" />
              Año de publicación
            </span>
            <div class="year-range-inputs">
              <input 
                v-model.number="filters.yearFrom" 
                type="number" 
                aria-label="Año de publicación desde"
                placeholder="Desde"
                min="1000"
                :max="currentYear"
                class="filter-input"
              >
              <span class="range-separator">-</span>
              <input 
                v-model.number="filters.yearTo" 
                type="number" 
                aria-label="Año de publicación hasta"
                placeholder="Hasta"
                min="1000"
                :max="currentYear"
                class="filter-input"
              >
            </div>
          </div>

          <!-- Editorial -->
          <div class="filter-group">
            <label
              class="filter-label"
              for="edition-filter-publisher"
            >
              <i class="fas fa-building" />
              Editorial
            </label>
            <select
              id="edition-filter-publisher"
              v-model="filters.publisher"
              class="filter-select"
            >
              <option value="">
                Todas las editoriales
              </option>
              <option
                v-for="pub in availablePublishers"
                :key="pub.name"
                :value="pub.name"
              >
                {{ pub.name }} ({{ pub.count }})
              </option>
            </select>
          </div>

          <!-- Formato físico -->
          <div class="filter-group">
            <label
              class="filter-label"
              for="edition-filter-format"
            >
              <i class="fas fa-bookmark" />
              Formato
            </label>
            <select
              id="edition-filter-format"
              v-model="filters.format"
              class="filter-select"
            >
              <option value="">
                Todos los formatos
              </option>
              <option
                v-for="fmt in availableFormats"
                :key="fmt.name"
                :value="fmt.name"
              >
                {{ fmt.name }} ({{ fmt.count }})
              </option>
            </select>
          </div>

          <!-- Botón limpiar filtros -->
          <div class="filter-actions">
            <button
              class="clear-filters-btn"
              @click="clearFilters"
            >
              <i class="fas fa-times-circle" />
              Limpiar filtros
            </button>
          </div>
        </div>
      </transition>
    </div>

    <!-- Estado de carga -->
    <div
      v-if="isLoading"
      class="loading-state"
    >
      <i class="fas fa-spinner fa-spin" />
      <p>Cargando ediciones...</p>
    </div>

    <!-- Error -->
    <div
      v-else-if="error"
      class="error-state"
    >
      <i class="fas fa-exclamation-circle" />
      <p>{{ error }}</p>
      <button
        class="retry-btn"
        @click="loadEditions"
      >
        <i class="fas fa-redo" />
        Reintentar
      </button>
    </div>

    <!-- Sin resultados -->
    <div
      v-else-if="filteredEditions.length === 0"
      class="empty-state"
    >
      <i class="fas fa-inbox" />
      <p>No se encontraron ediciones con los filtros seleccionados</p>
      <button
        v-if="hasActiveFilters"
        class="clear-filters-btn"
        @click="clearFilters"
      >
        Limpiar filtros
      </button>
    </div>

    <!-- Carrusel de ediciones -->
    <div
      v-else
      class="editions-carousel"
    >
      <div class="carousel-container">
        <button 
          v-if="canScrollLeft" 
          class="carousel-nav-btn left" 
          aria-label="Scroll left"
          @click="scrollLeft"
        >
          <i class="fas fa-chevron-left" />
        </button>

        <div
          ref="carouselTrack"
          class="carousel-track"
          @scroll="updateScrollButtons"
        >
          <div 
            v-for="edition in filteredEditions" 
            :key="edition.key" 
            class="carousel-item"
          >
            <EditionCarouselItem 
              :edition="edition"
              :selected-edition-key="selectedEditionKey"
              :saved-isbn="savedIsbn"
              @select="selectEdition"
            />
          </div>
        </div>

        <button 
          v-if="canScrollRight" 
          class="carousel-nav-btn right" 
          aria-label="Scroll right"
          @click="scrollRight"
        >
          <i class="fas fa-chevron-right" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import EditionCarouselItem from './EditionCarouselItem.vue';
import { useWorkSearch } from '@/composables/useWorkSearch';
import { getLanguageName } from '@/utils/languageConstants';
import Logger from '@/utils/logger';

/* eslint-disable no-undef */
const props = defineProps({
  workKey: {
    type: String,
    required: true
  },
  initialSelectedEdition: {
    type: Object,
    default: null
  },
  savedIsbn: {
    type: String,
    default: null
  }
});

const emit = defineEmits(['edition-selected']);

// Composables
const { getWorkEditions, isSearching, error: searchError } = useWorkSearch();

// State
const editions = ref([]);
const selectedEditionKey = ref(props.initialSelectedEdition?.key || null);
const showFilters = ref(false);
const carouselTrack = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

// Filtros
const filters = ref({
  language: '',
  yearFrom: null,
  yearTo: null,
  publisher: '',
  format: ''
});

const currentYear = new Date().getFullYear();

// Computed
const isLoading = computed(() => isSearching.value);
const error = computed(() => searchError.value);

const hasActiveFilters = computed(() => {
  return filters.value.language !== '' ||
         filters.value.yearFrom !== null ||
         filters.value.yearTo !== null ||
         filters.value.publisher !== '' ||
         filters.value.format !== '';
});

// Obtener opciones de filtros disponibles
const availableLanguages = computed(() => {
  const langMap = new Map();
  
  editions.value.forEach(edition => {
    if (edition.languages && edition.languages.length > 0) {
      let lang = edition.languages[0];
      
      // Normalizar el código de idioma
      if (typeof lang === 'object') {
        lang = lang.key || lang.code || '';
      }
      
      // Si es una ruta de OpenLibrary, extraer el código
      if (typeof lang === 'string' && lang.includes('/')) {
        const parts = lang.split('/');
        lang = parts[parts.length - 1];
      }
      
      // Convertir a minúsculas para normalizar
      const normalizedCode = String(lang).toLowerCase();
      
      if (normalizedCode) {
        const langName = getLanguageName(normalizedCode, 'es');
        
        if (!langMap.has(normalizedCode)) {
          langMap.set(normalizedCode, { code: normalizedCode, name: langName, count: 0 });
        }
        langMap.get(normalizedCode).count++;
      }
    }
  });
  
  return Array.from(langMap.values()).sort((a, b) => b.count - a.count);
});

const availablePublishers = computed(() => {
  const pubMap = new Map();
  
  editions.value.forEach(edition => {
    if (edition.publishers && edition.publishers.length > 0) {
      const pub = edition.publishers[0];
      
      if (!pubMap.has(pub)) {
        pubMap.set(pub, { name: pub, count: 0 });
      }
      pubMap.get(pub).count++;
    }
  });
  
  return Array.from(pubMap.values()).sort((a, b) => b.count - a.count);
});

const availableFormats = computed(() => {
  const fmtMap = new Map();
  
  editions.value.forEach(edition => {
    if (edition.physical_format) {
      const fmt = edition.physical_format;
      
      if (!fmtMap.has(fmt)) {
        fmtMap.set(fmt, { name: fmt, count: 0 });
      }
      fmtMap.get(fmt).count++;
    }
  });
  
  return Array.from(fmtMap.values()).sort((a, b) => b.count - a.count);
});

// Ediciones filtradas
const filteredEditions = computed(() => {
  let result = editions.value;
  
  // Filtro de idioma
  if (filters.value.language) {
    result = result.filter(ed => {
      if (!ed.languages || ed.languages.length === 0) return false;
      
      // Normalizar el idioma de la edición
      let edLang = ed.languages[0];
      
      if (typeof edLang === 'object') {
        edLang = edLang.key || edLang.code || '';
      }
      
      if (typeof edLang === 'string' && edLang.includes('/')) {
        const parts = edLang.split('/');
        edLang = parts[parts.length - 1];
      }
      
      const normalizedEdLang = String(edLang).toLowerCase();
      
      return normalizedEdLang === filters.value.language;
    });
  }
  
  // Filtro de año
  if (filters.value.yearFrom || filters.value.yearTo) {
    result = result.filter(ed => {
      if (!ed.publish_date) return false;
      
      const year = extractYear(ed.publish_date);
      if (!year) return false;
      
      if (filters.value.yearFrom && year < filters.value.yearFrom) return false;
      if (filters.value.yearTo && year > filters.value.yearTo) return false;
      
      return true;
    });
  }
  
  // Filtro de editorial
  if (filters.value.publisher) {
    result = result.filter(ed => 
      ed.publishers && ed.publishers.includes(filters.value.publisher)
    );
  }
  
  // Filtro de formato
  if (filters.value.format) {
    result = result.filter(ed => 
      ed.physical_format === filters.value.format
    );
  }
  
  return result;
});

// Methods
const loadEditions = async () => {
  Logger.debug('[EditionSelector] Loading editions for work:', props.workKey);
  
  try {
    const result = await getWorkEditions(props.workKey);
    editions.value = result || [];
    
    Logger.debug(`[EditionSelector] Loaded ${editions.value.length} editions`);
    
    // Actualizar botones de scroll después de cargar
    await nextTick();
    updateScrollButtons();
  } catch (err) {
    Logger.error('[EditionSelector] Error loading editions:', err);
  }
};

const selectEdition = (edition) => {
  Logger.debug('[EditionSelector] Edition selected:', edition);
  selectedEditionKey.value = edition.key;
  emit('edition-selected', edition);
};

const toggleFilters = () => {
  showFilters.value = !showFilters.value;
};

const clearFilters = () => {
  filters.value = {
    language: '',
    yearFrom: null,
    yearTo: null,
    publisher: '',
    format: ''
  };
};

const scrollLeft = () => {
  if (carouselTrack.value) {
    carouselTrack.value.scrollBy({ left: -300, behavior: 'smooth' });
  }
};

const scrollRight = () => {
  if (carouselTrack.value) {
    carouselTrack.value.scrollBy({ left: 300, behavior: 'smooth' });
  }
};

const updateScrollButtons = () => {
  if (!carouselTrack.value) return;
  
  const { scrollLeft, scrollWidth, clientWidth } = carouselTrack.value;
  
  canScrollLeft.value = scrollLeft > 0;
  canScrollRight.value = scrollLeft + clientWidth < scrollWidth - 1;
};

const extractYear = (dateString) => {
  if (!dateString) return null;
  
  // Try to extract 4-digit year
  const match = dateString.match(/\d{4}/);
  return match ? parseInt(match[0]) : null;
};

// Lifecycle
onMounted(() => {
  loadEditions();
});

// Watch para actualizar botones cuando cambian las ediciones filtradas
watch(filteredEditions, async () => {
  await nextTick();
  updateScrollButtons();
});

// Watch para actualizar el work key
watch(() => props.workKey, () => {
  loadEditions();
});
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.edition-selector {
  margin: 32px 0;
  padding: 24px;
  background: var(--color-background-soft);
  border-radius: 12px;
}

.section-header {
  margin-bottom: 24px;
}

.section-title {
  font-size: 24px;
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 8px 0;
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-title i {
  color: var(--color-primary);
}

.section-subtitle {
  font-size: 14px;
  color: var(--color-text-secondary);
  margin: 0;
}

.editions-count {
  font-weight: 600;
  color: var(--color-highlight);
}

/* Filtros */
.filters-section {
  margin-bottom: 24px;
}

.filters-toggle-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: var(--color-background-card);
  border: 2px solid var(--color-border);
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-dark);
  cursor: pointer;
  transition: all var(--transition-medium);
}

.filters-toggle-btn:hover {
  border-color: var(--color-primary);
  background: var(--color-primary-light);
  color: var(--color-text-light);
}

.filters-toggle-btn.active {
  background: var(--color-primary);
  color: var(--color-text-light);
  border-color: var(--color-primary);
}

.filters-container {
  margin-top: 16px;
  padding: 20px;
  background: var(--color-background-card);
  border-radius: 8px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-dark);
}

.filter-label i {
  color: var(--color-primary);
  font-size: 12px;
}

.filter-select,
.filter-input {
  padding: 10px 12px;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  font-size: 14px;
  background: var(--color-background);
  color: var(--color-text);
  transition: border-color var(--transition-fast);
}

.filter-select:focus,
.filter-input:focus {
  outline: none;
  border-color: var(--color-primary);
}

.year-range-inputs {
  display: flex;
  align-items: center;
  gap: 8px;
}

.year-range-inputs .filter-input {
  flex: 1;
}

.range-separator {
  color: var(--color-text-secondary);
  font-weight: 500;
}

.filter-actions {
  grid-column: 1 / -1;
  display: flex;
  justify-content: flex-end;
}

.clear-filters-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: transparent;
  border: 1px solid var(--color-warning);
  border-radius: 6px;
  color: var(--color-warning);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition-medium);
}

.clear-filters-btn:hover {
  background: var(--color-warning);
  color: var(--color-on-status);
}

/* Estados */
.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 48px 24px;
}

.loading-state i,
.error-state i,
.empty-state i {
  font-size: 48px;
  color: var(--color-primary);
  margin-bottom: 16px;
}

.error-state i {
  color: var(--color-error);
}

.empty-state i {
  color: var(--color-text-muted);
}

.retry-btn {
  margin-top: 16px;
  padding: 10px 20px;
  background: var(--btn-primary-bg);
  color: var(--btn-primary-text);
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: background var(--transition-medium);
}

.retry-btn:hover {
  background: var(--btn-primary-bg-hover);
}

/* Carrusel */
.editions-carousel {
  position: relative;
}

.carousel-container {
  position: relative;
}

.carousel-track {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  scroll-behavior: smooth;
  padding: 16px 4px;
  scrollbar-width: thin;
  scrollbar-color: var(--color-primary) var(--color-border);
}

.carousel-track::-webkit-scrollbar {
  height: 8px;
}

.carousel-track::-webkit-scrollbar-track {
  background: var(--color-border);
  border-radius: 4px;
}

.carousel-track::-webkit-scrollbar-thumb {
  background: var(--color-primary);
  border-radius: 4px;
}

.carousel-item {
  flex: 0 0 280px;
  max-width: 280px;
}

.carousel-nav-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 40px;
  height: 40px;
  background: var(--color-background-card);
  border: 2px solid var(--color-border);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 10;
  transition: all var(--transition-medium);
  box-shadow: var(--shadow-medium);
  color: var(--color-text-dark);
}

.carousel-nav-btn:hover {
  background: var(--color-primary);
  color: var(--color-text-light);
  border-color: var(--color-primary);
}

.carousel-nav-btn.left {
  left: -20px;
}

.carousel-nav-btn.right {
  right: -20px;
}

/* Transiciones */
.slide-fade-enter-active {
  transition: all var(--transition-medium);
}

.slide-fade-leave-active {
  transition: all var(--transition-medium);
}

.slide-fade-enter-from {
  transform: translateY(-10px);
  opacity: 0;
}

.slide-fade-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}

/* Responsive */
@include responsive-below(md) {
  .filters-container {
    grid-template-columns: 1fr;
  }
  
  .carousel-item {
    flex: 0 0 240px;
    max-width: 240px;
  }
  
  .carousel-nav-btn {
    display: none;
  }
}
</style>
