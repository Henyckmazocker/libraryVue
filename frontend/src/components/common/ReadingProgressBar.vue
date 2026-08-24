<template>
  <div class="reading-progress-container">
    <div class="progress-header">
      <span class="progress-label">Progreso de lectura</span>
      <span class="progress-text">{{ currentPage || 0 }} / {{ totalPages || 0 }} páginas ({{ progressPercentage }}%)</span>
    </div>
    
    <!-- Interactive progress bar/slider -->
    <div
      class="progress-bar-container"
      :class="{ 'editable': editable }"
    >
      <div 
        class="progress-bar" 
        :class="progressBarClass"
        :style="{ width: progressPercentage + '%' }"
        :title="`${progressPercentage}% completado`"
      >
        <div
          v-if="progressPercentage > 0"
          class="progress-bar-shine"
        />
      </div>
      
      <!-- Slider overlay for interaction -->
      <input 
        v-if="editable && totalPages > 0"
        type="range"
        class="progress-slider"
        :min="0"
        :max="totalPages || 100"
        :value="localCurrentPage"
        :disabled="saving"
        aria-label="Página actual"
        :title="`Arrastra para cambiar la página (0-${totalPages || 100})`"
        @input="updateCurrentPageFromSlider"
      >
    </div>
    
    <div
      v-if="editable"
      class="progress-footer"
    >
      <div class="page-input-container">
        <label
          for="current-page-input"
          class="page-label"
        >Página actual:</label>
        <input 
          id="current-page-input"
          type="number" 
          :value="localCurrentPage"
          :min="0"
          :max="totalPages || 999" 
          class="page-input"
          :disabled="saving"
          @input="updateCurrentPage"
        >
        <span
          v-if="saving"
          class="saving-indicator"
        >
          <i class="fas fa-spinner fa-spin" />
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, defineExpose } from 'vue';
import { defineProps, defineEmits } from 'vue';
import Logger from '@/utils/logger';

const props = defineProps({
  currentPage: {
    type: Number,
    default: 0
  },
  totalPages: {
    type: Number,
    required: true
  },
  editable: {
    type: Boolean,
    default: false
  },
  // Color themes para la barra de progreso
  theme: {
    type: String,
    default: 'blue',
    validator: (value) => ['blue', 'green', 'orange', 'purple', 'red'].includes(value)
  }
});

const emit = defineEmits(['update-progress']);

const localCurrentPage = ref(props.currentPage || 0);
const saving = ref(false);

// Expose current page value for parent components
const getCurrentPage = () => localCurrentPage.value;

// Computed properties
const progressPercentage = computed(() => {
  if (!props.totalPages || props.totalPages <= 0) return 0;
  const percentage = Math.round(((localCurrentPage.value || 0) / props.totalPages) * 100);
  return Math.min(100, Math.max(0, percentage));
});

const progressBarClass = computed(() => {
  const baseClass = 'progress-bar-fill';
  const themeClass = `progress-${props.theme}`;
  
  let statusClass = '';
  if (progressPercentage.value === 0) {
    statusClass = 'progress-not-started';
  } else if (progressPercentage.value === 100) {
    statusClass = 'progress-completed';
  } else if (progressPercentage.value >= 75) {
    statusClass = 'progress-almost-done';
  } else if (progressPercentage.value >= 25) {
    statusClass = 'progress-in-progress';
  } else {
    statusClass = 'progress-just-started';
  }
  
  return `${baseClass} ${themeClass} ${statusClass}`;
});

// Methods
const updateCurrentPage = (event) => {
  const value = parseInt(event.target.value) || 0;
  localCurrentPage.value = props.totalPages > 0
    ? Math.min(props.totalPages, Math.max(0, value))
    : Math.max(0, value);
  // Emitir el cambio inmediatamente
  emit('update-progress', localCurrentPage.value);
};

const updateCurrentPageFromSlider = (event) => {
  const value = parseInt(event.target.value) || 0;
  localCurrentPage.value = props.totalPages > 0
    ? Math.min(props.totalPages, Math.max(0, value))
    : Math.max(0, value);
  // Emitir el cambio inmediatamente
  emit('update-progress', localCurrentPage.value);
};

const saveProgress = async () => {
  if (saving.value) return;
  
  saving.value = true;
  try {
    await emit('update-progress', localCurrentPage.value);
  } catch (error) {
    // En caso de error, restaurar el valor anterior
    localCurrentPage.value = props.currentPage || 0;
    Logger.error('Error updating reading progress:', error);
  } finally {
    saving.value = false;
  }
};

// Watch for external changes
watch(() => props.currentPage, (newValue) => {
  localCurrentPage.value = newValue || 0;
});

// Expose methods and reactive values to parent
defineExpose({
  getCurrentPage,
  localCurrentPage,
  saveProgress
});
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.reading-progress-container {
  margin: 12px 0;
  padding: 10px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  font-size: 0.85rem;
}

.progress-label {
  font-weight: 500;
  color: var(--color-text);
}

.progress-text {
  color: var(--color-text-muted);
  font-size: 0.8rem;
}

.progress-bar-container {
  position: relative;
  width: 100%;
  height: 8px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  overflow: visible; /* Changed from hidden to allow slider thumb */
  margin-bottom: 8px;
}

.progress-bar-container.editable {
  cursor: pointer;
  height: 12px; /* Slightly taller when editable */
}

.progress-bar {
  height: 100%;
  border-radius: 4px;
  position: relative;
  transition: width 0.3s ease, background-color 0.3s ease;
  overflow: hidden;
}

.progress-slider {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0; /* Invisible but functional */
  cursor: pointer;
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
  outline: none;
}

/* Custom slider styles for WebKit browsers */
.progress-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--color-background-card);
  border: 2px solid var(--color-info);
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  transition: all 0.2s ease;
}

.progress-slider::-webkit-slider-thumb:hover {
  background: var(--color-border-light);
  border-color: var(--color-info);
  transform: scale(1.1);
  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
}

.progress-slider::-webkit-slider-thumb:active {
  background: var(--color-border-light);
  transform: scale(1.05);
}

/* Custom slider styles for Firefox */
.progress-slider::-moz-range-thumb {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--color-background-card);
  border: 2px solid var(--color-info);
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  transition: all 0.2s ease;
}

.progress-slider::-moz-range-thumb:hover {
  background: var(--color-border-light);
  border-color: var(--color-info);
  transform: scale(1.1);
  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
}

.progress-slider::-moz-range-track {
  background: transparent;
  border: none;
  height: 100%;
}

/* Show slider thumb only when hovering over the container */
.progress-bar-container:not(:hover) .progress-slider::-webkit-slider-thumb {
  opacity: 0;
  transform: scale(0.8);
}

.progress-bar-container:not(:hover) .progress-slider::-moz-range-thumb {
  opacity: 0;
  transform: scale(0.8);
}

.progress-bar-container:hover .progress-slider::-webkit-slider-thumb {
  opacity: 1;
  transform: scale(1);
}

.progress-bar-container:hover .progress-slider::-moz-range-thumb {
  opacity: 1;
  transform: scale(1);
}

/* Disabled state */
.progress-slider:disabled::-webkit-slider-thumb {
  background: var(--color-border);
  border-color: var(--color-background-mute);
  cursor: not-allowed;
}

.progress-slider:disabled::-moz-range-thumb {
  background: var(--color-border);
  border-color: var(--color-background-mute);
  cursor: not-allowed;
}

.progress-bar-shine {
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.3),
    transparent
  );
  animation: shine 2s infinite;
}

@keyframes shine {
  0% { left: -100%; }
  100% { left: 100%; }
}

/* Temas de color */
.progress-blue {
  background: linear-gradient(90deg, var(--color-info), var(--color-info));
}

.progress-green {
  background: var(--color-success);
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10));
}

.progress-orange {
  background: var(--color-warning);
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10));
}

.progress-purple {
  background: var(--color-card-movie-accent);
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10));
}

.progress-red {
  background: var(--color-error);
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10));
}

/* Estados de progreso */
.progress-not-started {
  background: var(--color-background-mute) !important;
}

.progress-just-started {
  background: var(--color-warning) !important;
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10)) !important;
}

.progress-in-progress {
  background: var(--color-info) !important;
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10)) !important;
}

.progress-almost-done {
  background: var(--color-success) !important;
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10)) !important;
}

.progress-completed {
  background: linear-gradient(90deg, var(--color-success), var(--color-success)) !important;
  box-shadow: 0 0 8px rgba(76, 175, 80, 0.5);
}

.progress-footer {
  margin-top: 8px;
}

.page-input-container {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.8rem;
}

.page-label {
  color: var(--color-text-muted);
  font-size: 0.8rem;
}

.page-input {
  width: 80px;
  padding: 4px 6px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 4px;
  color: var(--color-text);
  font-size: 0.8rem;
  text-align: center;
}

.page-input:focus {
  outline: none;
  border-color: var(--color-info);
  background: rgba(255, 255, 255, 0.15);
}

.page-input:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.saving-indicator {
  color: var(--color-info);
  font-size: 0.8rem;
}

/* Responsive */
@include responsive-below(sm) {
  .progress-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
  
  .progress-text {
    font-size: 0.75rem;
  }
  
  .page-input-container {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
}

// El modo oscuro va por la clase `.app-dark` de `<html>`, no por la preferencia del
// sistema: `store/ui.js` permite conmutarlo a mano, y `prefers-color-scheme` se
// saltaba ese interruptor. Lo prohíbe `.github/skills/styles.md`.
.app-dark {
  .reading-progress-container {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.08);
  }

  .progress-bar-container {
    background: rgba(255, 255, 255, 0.08);
  }
}
</style>
