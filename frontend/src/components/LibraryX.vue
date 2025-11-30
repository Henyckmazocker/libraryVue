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

    <div v-if="!isLoading && !error && paginatedDomains.length === 0" class="empty-library-message">
      No URLs found matching your criteria.
    </div>

    <!-- Información de paginación -->
    <div v-if="paginatedDomains.length > 0" class="pagination-info">
      <div class="results-info">
        Showing {{ paginationInfo.start }}-{{ paginationInfo.end }} of {{ paginationInfo.total }} domains 
        ({{ paginationInfo.totalUrls }} total URLs)
      </div>
      <div class="pagination-controls">
        <label for="domains-per-page">Domains per page:</label>
        <select 
          id="domains-per-page"
          v-model="domainsPerPage" 
          @change="changeDomainsPerPage(domainsPerPage)"
          class="pagination-select"
        >
          <option value="5">5</option>
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
        </select>
        
        <label for="urls-per-domain" style="margin-left: 15px;">Initial URLs per domain:</label>
        <select 
          id="urls-per-domain"
          v-model="itemsPerPage" 
          @change="changeItemsPerPage(itemsPerPage)"
          class="pagination-select"
        >
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>

    <div v-if="paginatedDomains.length > 0" class="url-list">
      <div v-for="domain in paginatedDomains" :key="domain.name" class="domain-section">
        <h3 class="domain-header">
          <i class="fas fa-globe"></i>
          {{ domain.name }} 
          <span class="url-count">({{ domain.totalUrls }} URLs{{ domain.hasMoreUrls && !domain.isExpanded ? `, showing ${domain.urls.length}` : '' }})</span>
        </h3>
        <div class="urls-container">
          <div v-for="(url, index) in domain.urls" :key="index" class="url-item">
            <!-- Vista de enlace simple -->
            <div class="url-link">
              <SimpleLink 
                :url="typeof url === 'string' ? url : url.url" 
                :title="formatUrl(typeof url === 'string' ? url : url.url)"
              />
            </div>
            
            <div class="url-actions">
              <button @click="copyToClipboard(typeof url === 'string' ? url : url.url)" class="copy-button" title="Copy URL">
                <i class="fas fa-copy"></i>
              </button>
            </div>
          </div>
        </div>
        
        <!-- Botón para mostrar más/menos URLs -->
        <div v-if="domain.hasMoreUrls" class="domain-expansion">
          <button 
            @click="toggleDomainExpansion(domain.name)"
            class="expansion-button"
          >
            <i :class="domain.isExpanded ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
            {{ domain.isExpanded ? 'Show less' : `Show ${domain.totalUrls - domain.urls.length} more URLs` }}
          </button>
        </div>
      </div>
    </div>

    <!-- Componente de paginación -->
    <div v-if="totalPages > 1" class="pagination">
      <div class="pagination-summary">
        Page {{ currentPage }} of {{ totalPages }}
      </div>
      
      <div class="pagination-buttons">
        <button 
          @click="goToFirstPage" 
          :disabled="currentPage === 1"
          class="pagination-btn"
          title="First page"
        >
          <i class="fas fa-angle-double-left"></i>
        </button>
        
        <button 
          @click="goToPreviousPage" 
          :disabled="currentPage === 1"
          class="pagination-btn"
          title="Previous page"
        >
          <i class="fas fa-angle-left"></i>
        </button>
        
        <!-- Números de página -->
        <div class="pagination-numbers">
          <button
            v-for="page in getVisiblePages()"
            :key="page"
            @click="goToPage(page)"
            :class="['pagination-number', { active: page === currentPage }]"
          >
            {{ page }}
          </button>
        </div>
        
        <button 
          @click="goToNextPage" 
          :disabled="currentPage === totalPages"
          class="pagination-btn"
          title="Next page"
        >
          <i class="fas fa-angle-right"></i>
        </button>
        
        <button 
          @click="goToLastPage" 
          :disabled="currentPage === totalPages"
          class="pagination-btn"
          title="Last page"
        >
          <i class="fas fa-angle-double-right"></i>
        </button>
      </div>
      
      <div class="pagination-jump">
        <input 
          v-model.number="jumpToPage" 
          @keyup.enter="goToPage(jumpToPage)"
          type="number" 
          :min="1" 
          :max="totalPages"
          placeholder="Go to page..."
          class="pagination-input"
        />
        <button @click="goToPage(jumpToPage)" class="pagination-btn">Go</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useLibraryX } from '@/services/LibraryXService';
import Logger from '@/utils/logger';
import SimpleLink from '@/components/SimpleLink.vue';

const { getUrls } = useLibraryX();

// Estados reactivos
const urlData = ref({});
const isLoading = ref(false);
const error = ref(null);
const searchQuery = ref('');
const currentSort = ref('domain-asc');
const selectedDomains = ref([]);
const filtersExpanded = ref(false);

// Estados de paginación
const currentPage = ref(1);
const itemsPerPage = ref(20); // URLs por página
const domainsPerPage = ref(10); // Dominios por página
const jumpToPage = ref(1); // Para el input de salto de página
const expandedDomains = ref({}); // Para trackear dominios expandidos

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

// Computed properties para paginación
const totalItems = computed(() => {
  return filteredUrls.value.reduce((total, domain) => total + domain.urls.length, 0);
});

const totalDomains = computed(() => {
  return filteredUrls.value.length;
});

const totalPages = computed(() => {
  return Math.ceil(totalDomains.value / domainsPerPage.value);
});

const paginatedDomains = computed(() => {
  const start = (currentPage.value - 1) * domainsPerPage.value;
  const end = start + domainsPerPage.value;
  
  return filteredUrls.value.slice(start, end).map(domain => {
    const isExpanded = expandedDomains.value[domain.name] || false;
    const maxUrlsToShow = isExpanded ? domain.urls.length : itemsPerPage.value;
    
    return {
      ...domain,
      urls: domain.urls.slice(0, maxUrlsToShow),
      hasMoreUrls: domain.urls.length > itemsPerPage.value,
      isExpanded: isExpanded,
      totalUrls: domain.urls.length
    };
  });
});

const paginationInfo = computed(() => {
  const start = (currentPage.value - 1) * domainsPerPage.value + 1;
  const end = Math.min(currentPage.value * domainsPerPage.value, totalDomains.value);
  
  return {
    start,
    end,
    total: totalDomains.value,
    totalUrls: totalItems.value
  };
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

// Métodos de paginación
const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    Logger.info(`[LibraryX] Navigated to page ${page}`);
  }
};

const goToFirstPage = () => {
  goToPage(1);
};

const goToLastPage = () => {
  goToPage(totalPages.value);
};

const goToPreviousPage = () => {
  goToPage(currentPage.value - 1);
};

const goToNextPage = () => {
  goToPage(currentPage.value + 1);
};

const changeItemsPerPage = (newSize) => {
  itemsPerPage.value = newSize;
  currentPage.value = 1; // Reset to first page
  expandedDomains.value = {}; // Reset expanded domains
  Logger.info(`[LibraryX] Items per page changed to ${newSize}`);
};

const changeDomainsPerPage = (newSize) => {
  domainsPerPage.value = newSize;
  currentPage.value = 1; // Reset to first page
  Logger.info(`[LibraryX] Domains per page changed to ${newSize}`);
};

// Reset pagination cuando cambian los filtros
const resetPagination = () => {
  currentPage.value = 1;
};

// Método para obtener páginas visibles en la paginación
const getVisiblePages = () => {
  const pages = [];
  const total = totalPages.value;
  const current = currentPage.value;
  
  if (total <= 7) {
    // Mostrar todas las páginas si son pocas
    for (let i = 1; i <= total; i++) {
      pages.push(i);
    }
  } else {
    // Lógica más compleja para muchas páginas
    if (current <= 4) {
      // Páginas al inicio
      for (let i = 1; i <= 5; i++) {
        pages.push(i);
      }
      pages.push('...');
      pages.push(total);
    } else if (current >= total - 3) {
      // Páginas al final
      pages.push(1);
      pages.push('...');
      for (let i = total - 4; i <= total; i++) {
        pages.push(i);
      }
    } else {
      // Páginas en el medio
      pages.push(1);
      pages.push('...');
      for (let i = current - 1; i <= current + 1; i++) {
        pages.push(i);
      }
      pages.push('...');
      pages.push(total);
    }
  }
  
  return pages;
};

// Método para expandir/colapsar URLs en un dominio
const toggleDomainExpansion = (domainName) => {
  expandedDomains.value[domainName] = !expandedDomains.value[domainName];
  Logger.info(`[LibraryX] Domain ${domainName} ${expandedDomains.value[domainName] ? 'expanded' : 'collapsed'}`);
};

// Inicialización del componente
onMounted(async () => {
    await loadUrlData();
});

// Watchers para resetear paginación cuando cambian filtros
watch([searchQuery, selectedDomains, currentSort], () => {
  resetPagination();
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
  color: var(--color-text);
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-weight: bold;
}

.controls-container {
  background: var(--color-background-soft);
  border: 1.5px solid var(--color-border);
  padding: 20px;
  border-radius: 12px;
  box-shadow: var(--shadow-medium);
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
  border: 1px solid var(--color-border);
  border-radius: 20px;
  font-size: 16px;
  background-color: var(--color-background-mute);
  color: var(--color-text);
  transition: var(--transition-fast);
  min-width: 200px;
}

.search-input::placeholder {
  color: var(--color-text-muted);
}

.search-input:focus {
  outline: none;
  border-color: var(--color-secondary);
}

.sort-dropdown {
  padding: 12px 16px;
  border: 1px solid var(--color-border);
  border-radius: 20px;
  font-size: 16px;
  background-color: var(--color-background-mute);
  color: var(--color-text);
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
  flex-direction: column;
  padding: 12px 0;
  border-bottom: 1px solid #444a57;
  gap: 10px;
}

.url-item:last-child {
  border-bottom: none;
}

.url-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
}

.url-actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.url-meta {
  margin-left: 15px;
  margin-top: 5px;
}

.media-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(0, 123, 255, 0.1);
  color: #007bff;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  border: 1px solid rgba(0, 123, 255, 0.2);
}

.url-embed {
  margin-top: 15px;
  border-radius: 8px;
  overflow: hidden;
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
}

.embed-button {
  background: linear-gradient(135deg, #28a745, #20c997);
  border: none;
  padding: 8px 12px;
  border-radius: 8px;
  cursor: pointer;
  color: white;
  transition: all 0.3s ease;
  font-size: 0.9rem;
}

.embed-button:hover {
  background: linear-gradient(135deg, #218838, #1ea085);
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Estilos para expansión de dominios */
.domain-expansion {
  padding: 1rem;
  text-align: center;
  border-top: 1px solid #444a57;
  background: var(--color-background-mute);
}

.expansion-button {
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  color: var(--color-text);
  padding: 0.75rem 1.5rem;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 auto;
}

.expansion-button:hover {
  background: var(--color-background-mute);
  border-color: var(--color-primary);
  color: var(--color-primary);
}

/* Estilos de paginación */
.pagination-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 0;
  margin-bottom: 1rem;
  border-bottom: 1px solid #444a57;
}

.results-info {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pagination-controls label {
  color: var(--color-text);
  font-size: 0.875rem;
}

.pagination-select {
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: 4px;
  color: var(--color-text);
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 2rem 0 1rem 0;
  margin-top: 2rem;
  border-top: 1px solid #444a57;
  flex-wrap: wrap;
  gap: 1rem;
}

.pagination-summary {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.pagination-buttons {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pagination-btn {
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  color: var(--color-text);
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.875rem;
}

.pagination-btn:hover:not(:disabled) {
  background: var(--color-background-mute);
  border-color: var(--color-primary);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-numbers {
  display: flex;
  gap: 0.25rem;
  margin: 0 0.5rem;
}

.pagination-number {
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  color: var(--color-text);
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.875rem;
  min-width: 2.5rem;
  text-align: center;
}

.pagination-number:hover {
  background: var(--color-background-mute);
  border-color: var(--color-primary);
}

.pagination-number.active {
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: white;
}

.pagination-jump {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pagination-input {
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: 4px;
  color: var(--color-text);
  padding: 0.5rem;
  width: 100px;
  font-size: 0.875rem;
}

.pagination-input:focus {
  outline: none;
  border-color: var(--color-primary);
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

  .url-content {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .url-actions {
    margin-left: 0;
    align-self: flex-end;
  }

  /* Responsive pagination */
  .pagination {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }
  
  .pagination-info {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }
  
  .pagination-controls {
    flex-wrap: wrap;
    justify-content: center;
  }
  
  .pagination-buttons {
    flex-wrap: wrap;
    justify-content: center;
  }
  
  .pagination-numbers {
    margin: 0;
  }
  
  .pagination-number,
  .pagination-btn {
    font-size: 0.75rem;
    padding: 0.4rem 0.6rem;
  }
}
</style>
