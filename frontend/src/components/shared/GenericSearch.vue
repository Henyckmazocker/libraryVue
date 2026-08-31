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
        class="btn btn--primary search-button"
        @click="() => handleSearch(input, index)"
      >
        <i class="fas fa-search" />
        <span
          v-if="input.buttonText"
          class="button-text"
        >{{ input.buttonText }}</span>
      </button>
    </div>
    
    <!-- Un error de verdad: la API no respondió, o no se escribió qué buscar. -->
    <div
      v-if="errorMessage"
      class="error-message"
      role="alert"
    >
      {{ errorMessage }}
    </div>

    <!-- Y el vacío, que NO es un error: la búsqueda funcionó y no hay nada. -->
    <EmptyState
      v-if="sinResultados"
      :icon="config.emptyIcon || 'fas fa-magnifying-glass'"
      :title="t('search.noResults')"
      :message="t('search.noResultsHint')"
    />

    <StaleNotice
      :stale="isStale"
      :cached-at="cachedAt"
      :provider="config.staleProvider"
    />

    <!-- Resultados en carrusel horizontal -->
    <div
      v-if="results && results.length"
      class="results-section"
    >
      <h2 class="results-title">
        {{ t('search.resultsTitle', { n: results.length }) }}
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
import { computed, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import HorizontalCarousel from '@/components/shared/HorizontalCarousel.vue';
import StaleNotice from '@/components/shared/StaleNotice.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry';
import Logger from '@/utils/logger';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

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
        typeof config.fetchAllowedStatuses === 'function' &&
        // Opcional, pero si `media` viene tiene que ser una clave del registry:
        // un typo dejaría la franja muda en silencio.
        (config.media === undefined || mediaKeys.includes(config.media))
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
// Separado de `errorMessage` a propósito. Antes el vacío viajaba dentro del
// error y salía en rojo: buscar algo que no existe no es un fallo, y esa
// confusión es la que este componente y `EmptyState` deshacen.
const sinResultados = ref(false);
const allowedStatuses = ref([]);

// Degradación visible. `supportsStale` sale del `api` del registry —la única
// descripción de lo que diferencia a un medio— y no de la config, para que
// añadir un medio que puede degradar sea una línea en un sitio. Un medio que no
// lo declara no cambia ni un píxel: `isStale` se queda en `false` para siempre.
const supportsStale = computed(() => {
  // `getMediaConfig` LANZA con un medio desconocido (`mediaRegistry.js:1497`),
  // así que se pregunta antes: aquí un typo no puede tumbar la búsqueda entera.
  if (!mediaKeys.includes(props.config.media)) return false;
  return getMediaConfig(props.config.media).api?.supportsStale === true;
});

const staleFlag = ref(false);
const cachedAt = ref(null);

const isStale = computed(() => supportsStale.value && staleFlag.value);

// Métodos
const handleSearch = async (input, index) => {
  errorMessage.value = '';
  sinResultados.value = false;
  const query = inputValues.value[index].trim();
  
  if (!query) {
    errorMessage.value = input.emptyMessage || t('toasts.searchEmpty');
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
    
    // Para búsquedas normales, usar el handler de búsqueda.
    //
    // El handler puede devolver la lista pelada de siempre o el sobre
    // `{ results, stale, cached_at }`. Las dos formas conviven a propósito: solo
    // los medios que pueden degradar necesitan mandar la frescura, y los otros
    // dos no tienen por qué cambiar de contrato para no decir nada.
    const respuesta = await props.config.searchHandler(query, searchType);
    const searchResults = Array.isArray(respuesta) ? respuesta : (respuesta?.results || []);

    staleFlag.value = Array.isArray(respuesta) ? false : respuesta?.stale === true;
    cachedAt.value = Array.isArray(respuesta) ? null : (respuesta?.cached_at ?? null);

    if (!searchResults || searchResults.length === 0) {
      sinResultados.value = true;
      results.value = [];
      // Sin nada que enseñar, la franja no describe nada: el proveedor caído y
      // sin caché tiene que dar el error de siempre, no un aviso sobre el vacío.
      staleFlag.value = false;
      cachedAt.value = null;
      return;
    }
    
    results.value = searchResults.map(props.config.transformResult);
    Logger.debug(`[GenericSearch] Found ${results.value.length} results`);
  } catch (error) {
    Logger.error('[GenericSearch] Search error:', error);
    errorMessage.value = input.errorMessage || t('toasts.searchFailed');
    results.value = [];
    // Sin resultados no hay nada que la franja describa, y dejarla puesta
    // pondría un aviso de caché encima de un error de búsqueda.
    staleFlag.value = false;
    cachedAt.value = null;
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
  padding: spacing(xl);
  width: 100%;
  max-width: 100%;
}

.title {
  font-size: var(--font-size-2xl);
  color: var(--color-text);
  margin-bottom: spacing(xl);
  text-align: center;
}

.input-group {
  display: flex;
  width: 100%;
  max-width: 600px;
  margin-bottom: spacing(xl);
}

.search-input {
  flex-grow: 1;
  padding: spacing(sm) spacing(md);
  font-size: var(--font-size-base);
  color: var(--color-text);
  background-color: var(--color-background-mute);
  border: 1px solid var(--color-border);
  border-radius: radius(2xl) 0 0 radius(2xl);
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
  border-radius: 0 radius(2xl) radius(2xl) 0;
}

.button-text {
  font-size: var(--font-size-sm);
  font-weight: 500;
}

.error-message {
  padding: spacing(sm) spacing(md);
  border-radius: radius(lg);
  margin-bottom: spacing(md);
  width: 100%;
  text-align: center;
  box-sizing: border-box;
  color: var(--color-error);
  background-color: var(--color-error-bg);
}

.results-section {
  width: 100%;
  max-width: 100%;
  margin-top: spacing(xl);
}

.results-title {
  font-size: var(--font-size-lg);
  color: var(--color-heading);
  margin-bottom: spacing(md);
  font-weight: 600;
  text-align: left;
  padding: 0 spacing(md);
}

/* Responsive design */
@include responsive-below(md) {
  .generic-search-container {
    padding: spacing(md);
    max-width: 100%;
  }
  
  .title {
    font-size: var(--font-size-2xl);
    margin-bottom: spacing(md);
  }
  
  .search-button {
    padding: spacing(sm) spacing(md);
  }
  
  .button-text {
    display: none;
  }
  
  .results-title {
    font-size: var(--font-size-md);
    padding: 0 spacing(sm);
  }
}
</style>
