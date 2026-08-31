<template>
  <button
    type="button"
    class="edition-card"
    @click="handleClick"
  >
    <div class="edition-cover">
      <img 
        v-if="edition.cover_url" 
        :src="edition.cover_url" 
        :alt="edition.title"
        class="cover-image"
        width="248"
        height="200"
        loading="lazy"
        decoding="async"
        @error="handleImageError"
      >
      <div
        v-else
        class="cover-placeholder"
      >
        <i class="fas fa-book" />
      </div>
      
      <!-- Badge para indicar la edición seleccionada -->
      <div
        v-if="isSelected"
        class="selected-badge"
      >
        <i class="fas fa-check-circle" />
      </div>
      
      <!-- Badge para indicar la edición guardada en biblioteca -->
      <div
        v-if="isSaved && !isSelected"
        class="saved-badge"
        :title="t('carousel.editionSaved')"
      >
        <i
          class="fas fa-bookmark"
          aria-hidden="true"
        />
        <span class="u-sr-only">{{ t('carousel.editionSaved') }}</span>
      </div>
    </div>

    <div class="edition-info">
      <h4 class="edition-title">
        {{ edition.title }}
      </h4>
      
      <div
        v-if="edition.publishers && edition.publishers.length > 0"
        class="edition-publisher"
      >
        <i class="fas fa-building" />
        <span>{{ edition.publishers[0] }}</span>
      </div>
      
      <div class="edition-metadata">
        <span
          v-if="edition.publish_date"
          class="metadata-item"
        >
          <i class="fas fa-calendar" />
          {{ edition.publish_date }}
        </span>
        
        <span
          v-if="edition.number_of_pages"
          class="metadata-item"
        >
          <i class="fas fa-file-alt" />
          {{ t('carousel.pages', { n: edition.number_of_pages }) }}
        </span>
      </div>

      <div
        v-if="edition.languages && edition.languages.length > 0"
        class="edition-language"
      >
        <i class="fas fa-globe" />
        <span>{{ getLanguageName(edition.languages[0], 'ui') }}</span>
      </div>

      <div
        v-if="edition.isbn_13 || edition.isbn_10"
        class="edition-isbn"
      >
        <strong>{{ t('carousel.isbn') }}</strong> {{ edition.isbn_13 || edition.isbn_10 }}
      </div>

      <div
        v-if="edition.physical_format"
        class="edition-format"
      >
        <i class="fas fa-bookmark" />
        <span>{{ edition.physical_format }}</span>
      </div>
    </div>
  </button>
</template>

<script setup>
import { computed } from 'vue';
import Logger from '@/utils/logger';
import { getLanguageName } from '@/utils/languageConstants';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

/* eslint-disable no-undef */
const props = defineProps({
  edition: {
    type: Object,
    required: true
  },
  selectedEditionKey: {
    type: String,
    default: null
  },
  savedIsbn: {
    type: String,
    default: null
  }
});

const emit = defineEmits(['select']);

const isSelected = computed(() => {
  return props.edition.key === props.selectedEditionKey;
});

const isSaved = computed(() => {
  if (!props.savedIsbn) return false;
  
  // Comparar ISBN-13 o ISBN-10 con el ISBN guardado
  return props.edition.isbn_13 === props.savedIsbn || 
         props.edition.isbn_10 === props.savedIsbn;
});

const handleClick = () => {
  Logger.debug('[EditionCarouselItem] Edition selected:', props.edition);
  emit('select', props.edition);
};

const handleImageError = (event) => {
  Logger.warn('[EditionCarouselItem] Cover image failed to load:', props.edition.cover_url);
  event.target.style.display = 'none';
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.edition-card {
  @include button-reset;
  // El <div> llenaba el wrapper `.carousel-item` (280px) por ser block; un <button>
  // se encoge al contenido, así que aquí el ancho se declara. `button-reset` no lo
  // trae de serie a propósito: en los demás carruseles rompería la maqueta.
  width: 100%;
  background: var(--color-background-card);
  border: 2px solid var(--color-border);
  border-radius: radius(lg);
  padding: spacing(md);
  cursor: pointer;
  transition: all var(--transition-medium);
  height: 100%;
  display: flex;
  flex-direction: column;
  position: relative;
}

.edition-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-heavy);
  border-color: var(--color-primary);
}

.edition-cover {
  position: relative;
  width: 100%;
  height: 200px;
  margin-bottom: spacing(sm);
  border-radius: radius(md);
  overflow: hidden;
  background: var(--color-background-soft);
  display: flex;
  align-items: center;
  justify-content: center;
}

.cover-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cover-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, var(--color-background-soft), var(--color-background-mute));
}

.cover-placeholder i {
  font-size: var(--font-size-4xl);
  color: var(--color-text-muted);
}

.selected-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: var(--color-success);
  color: var(--color-on-status);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: var(--shadow-medium);
  animation: scaleIn var(--transition-medium);
}

.saved-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: var(--color-highlight);
  color: var(--color-text-light);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: var(--shadow-medium);
  animation: slideInLeft var(--transition-medium);
}

@keyframes scaleIn {
  from {
    transform: scale(0);
  }
  to {
    transform: scale(1);
  }
}

@keyframes slideInLeft {
  from {
    transform: translateX(-20px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.edition-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: spacing(xs);
}

.edition-title {
  font-size: var(--font-size-base);
  font-weight: 600;
  color: var(--color-text-dark);
  margin: 0;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.edition-publisher {
  display: flex;
  align-items: center;
  gap: spacing(xs);
  font-size: var(--font-size-sm);
  color: var(--color-text-dark);
}

.edition-publisher i {
  color: var(--color-primary);
  font-size: var(--font-size-xs);
}

.edition-metadata {
  display: flex;
  flex-wrap: wrap;
  gap: spacing(sm);
  font-size: var(--font-size-xs);
  color: var(--color-text-dark);
}

.metadata-item {
  display: flex;
  align-items: center;
  gap: spacing(2xs);
}

.metadata-item i {
  color: var(--color-primary);
  font-size: var(--font-size-xs);
}

.edition-language {
  display: flex;
  align-items: center;
  gap: spacing(xs);
  font-size: var(--font-size-xs);
  color: var(--color-text-dark);
}

.edition-language i {
  color: var(--color-primary);
}

.edition-isbn {
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
  font-family: 'Courier New', monospace;
}

.edition-format {
  display: inline-flex;
  align-items: center;
  gap: spacing(2xs);
  padding: spacing(2xs) spacing(xs);
  background: var(--color-primary-light);
  color: var(--color-text-light);
  border-radius: radius(sm);
  font-size: var(--font-size-xs);
  font-weight: 500;
  align-self: flex-start;
}

.edition-format i {
  font-size: var(--font-size-xs);
}
</style>
