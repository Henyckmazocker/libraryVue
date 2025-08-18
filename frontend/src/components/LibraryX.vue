<template>
  <div class="library-container">
    <h1 class="title">LibraryX - URL Collection</h1>
    
    <div class="controls-container">
      <div class="search-sort-row">
        <input 
          type="text" 
          v-model="searchQuery" 
          placeholder="Search URLs or domains..." 
          class="search-input"
        />
        <select v-model="currentSort" @change="sortUrls" class="sort-dropdown">
          <option value="domain-asc">Domain (A-Z)</option>
          <option value="domain-desc">Domain (Z-A)</option>
          <option value="count-desc">URL Count (Most First)</option>
          <option value="count-asc">URL Count (Least First)</option>
          <option value="newest">Recently Added</option>
          <option value="oldest">Oldest First</option>
        </select>
      </div>
      
      <div class="filter-section">
        <div class="filter-header" @click="toggleFiltersExpanded">
          <h3>
            <i class="fas fa-filter"></i>
            Filter by Domain 
            <span class="domain-count">({{ availableDomains.length }} domains)</span>
            <i :class="['fas', 'expand-icon', filtersExpanded ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
          </h3>
        </div>
        <div v-show="filtersExpanded" class="domain-filters">
          <button 
            v-for="domain in availableDomains" 
            :key="domain"
            @click="toggleDomainFilter(domain)"
            :class="['filter-button', { active: selectedDomains.includes(domain) }]"
          >
            {{ domain }} ({{ urlData[domain]?.length || 0 }})
          </button>
        </div>
        <div v-if="!filtersExpanded && selectedDomains.length > 0" class="selected-filters-preview">
          <span class="selected-count">{{ selectedDomains.length }} domain(s) selected</span>
          <button @click="clearAllFilters" class="clear-filters-btn">
            <i class="fas fa-times"></i> Clear
          </button>
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="loading-message">
      <i class="fas fa-spinner fa-spin"></i> Loading LibraryX...
    </div>
    
    <div v-if="error" class="error-message">{{ error }}</div>

    <div v-if="!isLoading && !error && filteredUrls.length === 0" class="empty-library-message">
      No URLs found matching your criteria.
    </div>

    <div v-if="filteredUrls.length > 0" class="url-list">
      <div v-for="domain in filteredUrls" :key="domain.name" class="domain-section">
        <h3 class="domain-header">
          <i class="fas fa-globe"></i>
          {{ domain.name }} 
          <span class="url-count">({{ domain.urls.length }} URLs)</span>
        </h3>
        <div class="urls-container">
          <div v-for="(url, index) in domain.urls" :key="index" class="url-item">
            <a :href="url" target="_blank" rel="noopener noreferrer" class="url-link">
              <i class="fas fa-external-link-alt"></i>
              {{ formatUrl(url) }}
            </a>
            <button @click="copyToClipboard(url)" class="copy-button" title="Copy URL">
              <i class="fas fa-copy"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useLibraryX } from '@/services/LibraryXService';
import Logger from '@/utils/logger';

const { getUrls } = useLibraryX();

// Estados reactivos
const urlData = ref({});
const isLoading = ref(false);
const error = ref(null);
const searchQuery = ref('');
const currentSort = ref('domain-asc');
const selectedDomains = ref([]);
const filtersExpanded = ref(false);

// Computed properties
const availableDomains = computed(() => {
  return Object.keys(urlData.value).sort();
});

const filteredUrls = computed(() => {
  let domains = availableDomains.value;
  
  // Filtrar por dominios seleccionados
  if (selectedDomains.value.length > 0) {
    domains = domains.filter(domain => selectedDomains.value.includes(domain));
  }
  
  // Filtrar por búsqueda (mejorado)
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase().trim();
    domains = domains.filter(domain => {
      // Buscar en el nombre del dominio
      const domainMatches = domain.toLowerCase().includes(query);
      
      // Buscar en las URLs del dominio
      const urlMatches = urlData.value[domain]?.some(url => 
        url.toLowerCase().includes(query)
      ) || false;
      
      return domainMatches || urlMatches;
    });
  }
  
  // Crear estructura de datos para mostrar con URLs filtradas
  let result = domains.map(domain => {
    let urls = urlData.value[domain] || [];
    
    // Si hay una búsqueda, filtrar también las URLs individuales
    if (searchQuery.value.trim()) {
      const query = searchQuery.value.toLowerCase().trim();
      urls = urls.filter(url => url.toLowerCase().includes(query));
    }
    
    return {
      name: domain,
      urls: urls,
      count: urls.length
    };
  });
  
  // Aplicar ordenación
  switch (currentSort.value) {
    case 'domain-desc':
      result.sort((a, b) => b.name.localeCompare(a.name));
      break;
    case 'count-desc':
      result.sort((a, b) => b.count - a.count);
      break;
    case 'count-asc':
      result.sort((a, b) => a.count - b.count);
      break;
    case 'domain-asc':
    default:
      result.sort((a, b) => a.name.localeCompare(b.name));
      break;
  }
  
  return result;
});

// Métodos
const loadUrlData = async () => {
  isLoading.value = true;
  error.value = null;
  
  try {
    Logger.info('[LibraryX] Loading URL data...');
    const data = await getUrls();
    urlData.value = data;
    Logger.info(`[LibraryX] Loaded ${Object.keys(data).length} domains`);
  } catch (err) {
    error.value = 'Failed to load URL data: ' + err.message;
    Logger.error('[LibraryX] Failed to load URL data:', err);
  } finally {
    isLoading.value = false;
  }
};

const sortUrls = () => {
  Logger.info(`[LibraryX] Sorting changed to: ${currentSort.value}`);
};

const toggleDomainFilter = (domain) => {
  const index = selectedDomains.value.indexOf(domain);
  if (index > -1) {
    selectedDomains.value.splice(index, 1);
  } else {
    selectedDomains.value.push(domain);
  }
  Logger.info(`[LibraryX] Domain filter toggled: ${domain}`, {
    selected: selectedDomains.value
  });
};

const toggleFiltersExpanded = () => {
  filtersExpanded.value = !filtersExpanded.value;
  Logger.info(`[LibraryX] Filters ${filtersExpanded.value ? 'expanded' : 'collapsed'}`);
};

const clearAllFilters = () => {
  selectedDomains.value = [];
  Logger.info('[LibraryX] All domain filters cleared');
};

const formatUrl = (url) => {
  try {
    const urlObj = new URL(url);
    const path = urlObj.pathname + urlObj.search;
    return path.length > 50 ? path.substring(0, 50) + '...' : path;
  } catch {
    return url.length > 50 ? url.substring(0, 50) + '...' : url;
  }
};

const copyToClipboard = async (url) => {
  try {
    await navigator.clipboard.writeText(url);
    Logger.info(`[LibraryX] URL copied to clipboard: ${url}`);
    // Aquí podrías agregar una notificación visual
  } catch (err) {
    Logger.error('[LibraryX] Failed to copy URL:', err);
  }
};

// Inicialización del componente
onMounted(async () => {
    await loadUrlData();
});
</script>

<style scoped>
.library-container {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.title {
  font-size: 2.5rem;
  margin-bottom: 2rem;
  text-align: center;
  color: #e0e0e0;
  background: linear-gradient(135deg, #007bff 0%, #20c997 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-weight: bold;
}

.controls-container {
  background: #23272f;
  border: 1.5px solid #444a57;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.3);
  margin-bottom: 30px;
}

.search-sort-row {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
  align-items: center;
}

.search-input {
  flex: 1;
  padding: 12px 16px;
  border: 1px solid #555;
  border-radius: 20px;
  font-size: 16px;
  background-color: #3a3a3a;
  color: #e0e0e0;
  transition: border-color 0.3s ease;
  min-width: 200px;
}

.search-input::placeholder {
  color: #888;
}

.search-input:focus {
  outline: none;
  border-color: #007bff;
}

.sort-dropdown {
  padding: 12px 16px;
  border: 1px solid #555;
  border-radius: 20px;
  font-size: 16px;
  background-color: #3a3a3a;
  color: #e0e0e0;
  cursor: pointer;
  min-width: 200px;
}

.filter-section h3 {
  margin: 0 0 15px 0;
  color: #e0e0e0;
  font-size: 1.2rem;
}

.filter-header {
  cursor: pointer;
  user-select: none;
  transition: all 0.2s ease;
}

.filter-header:hover {
  opacity: 0.8;
}

.filter-header h3 {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}

.domain-count {
  font-size: 0.9rem;
  opacity: 0.7;
  font-weight: normal;
}

.expand-icon {
  margin-left: auto;
  font-size: 0.8rem;
  transition: transform 0.2s ease;
}

.domain-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 15px;
}

.selected-filters-preview {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 10px;
  padding: 8px 12px;
  background: rgba(0, 123, 255, 0.1);
  border: 1px solid #007bff;
  border-radius: 8px;
}

.selected-count {
  color: #007bff;
  font-size: 0.9rem;
  font-weight: 500;
}

.clear-filters-btn {
  background: none;
  border: none;
  color: #dc3545;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  transition: background-color 0.2s;
}

.clear-filters-btn:hover {
  background: rgba(220, 53, 69, 0.1);
}

.filter-button {
  padding: 8px 16px;
  border: 1.5px solid #444a57;
  background: #23272f;
  border-radius: 999px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 14px;
  color: #e0e0e0;
}

.filter-button:hover {
  border-color: #007bff;
  background: #3a3a3a;
}

.filter-button.active {
  background: linear-gradient(135deg, #007bff, #20c997);
  color: white;
  border-color: #007bff;
}

.loading-message, .error-message, .empty-library-message {
  text-align: center;
  padding: 40px;
  font-size: 1.2rem;
  color: #e0e0e0;
}

.error-message {
  color: #dc3545;
  background: rgba(220, 53, 69, 0.1);
  border-radius: 8px;
  border: 1px solid rgba(220, 53, 69, 0.3);
}

.url-list {
  display: flex;
  flex-direction: column;
  gap: 25px;
}

.domain-section {
  background: #23272f;
  border: 1.5px solid #444a57;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.3);
  overflow: hidden;
}

.domain-header {
  background: linear-gradient(135deg, #007bff 0%, #20c997 100%);
  color: white;
  padding: 20px;
  margin: 0;
  font-size: 1.4rem;
  display: flex;
  align-items: center;
  gap: 10px;
}

.url-count {
  font-size: 0.9rem;
  opacity: 0.9;
  font-weight: normal;
}

.urls-container {
  padding: 20px;
}

.url-item {
  display: flex;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #444a57;
  gap: 15px;
}

.url-item:last-child {
  border-bottom: none;
}

.url-link {
  flex: 1;
  text-decoration: none;
  color: #007bff;
  font-size: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: color 0.3s ease;
}

.url-link:hover {
  color: #20c997;
  text-decoration: underline;
}

.copy-button {
  padding: 8px 12px;
  background: #3a3a3a;
  border: 1px solid #555;
  border-radius: 6px;
  cursor: pointer;
  color: #007bff;
  transition: all 0.3s ease;
}

.copy-button:hover {
  background: linear-gradient(135deg, #007bff, #20c997);
  color: white;
  border-color: #007bff;
}

@media (max-width: 768px) {
  .search-sort-row {
    flex-direction: column;
  }
  
  .domain-filters {
    justify-content: center;
  }
  
  .url-item {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }
}
</style>
