<template>
  <div class="reading-progress-container">
    <div class="progress-header">
      <span class="progress-label">Progreso de lectura</span>
      <span class="progress-text">{{ currentPage || 0 }} / {{ totalPages || 0 }} páginas ({{ progressPercentage }}%)</span>
    </div>
    
    <!-- Interactive progress bar/slider -->
    <div class="progress-bar-container" :class="{ 'editable': editable }">
      <div 
        class="progress-bar" 
        :class="progressBarClass"
        :style="{ width: progressPercentage + '%' }"
        :title="`${progressPercentage}% completado`"
      >
        <div class="progress-bar-shine" v-if="progressPercentage > 0"></div>
      </div>
      
      <!-- Slider overlay for interaction -->
      <input 
        v-if="editable && totalPages > 0"
        type="range"
        class="progress-slider"
        :min="0"
        :max="totalPages || 100"
        :value="localCurrentPage"
        @input="updateCurrentPageFromSlider"
        @change="saveProgress"
        :disabled="saving"
        :title="`Arrastra para cambiar la página (0-${totalPages || 100})`"
      />
    </div>
    
    <div class="progress-footer" v-if="editable">
      <div class="page-input-container">
        <label for="current-page-input" class="page-label">Página actual:</label>
        <input 
          id="current-page-input"
          type="number" 
          :value="localCurrentPage"
          @input="updateCurrentPage"
          @blur="saveProgress"
          @keyup.enter="saveProgress"
          :min="0" 
          :max="totalPages || 999"
          class="page-input"
          :disabled="saving"
        />
        <span v-if="saving" class="saving-indicator">
          <i class="fas fa-spinner fa-spin"></i>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { defineProps, defineEmits } from 'vue';

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
  localCurrentPage.value = Math.min(props.totalPages, Math.max(0, value));
};

const updateCurrentPageFromSlider = (event) => {
  const value = parseInt(event.target.value) || 0;
  localCurrentPage.value = Math.min(props.totalPages, Math.max(0, value));
};

const saveProgress = async () => {
  if (saving.value) return;
  
  saving.value = true;
  try {
    await emit('update-progress', localCurrentPage.value);
  } catch (error) {
    // En caso de error, restaurar el valor anterior
    localCurrentPage.value = props.currentPage || 0;
    console.error('Error updating progress:', error);
  } finally {
    saving.value = false;
  }
};

// Watch for external changes
watch(() => props.currentPage, (newValue) => {
  localCurrentPage.value = newValue || 0;
});
</script>

<style scoped>
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
  color: #e0e0e0;
}

.progress-text {
  color: #bbb;
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
  background: #ffffff;
  border: 2px solid #1976d2;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  transition: all 0.2s ease;
}

.progress-slider::-webkit-slider-thumb:hover {
  background: #f0f0f0;
  border-color: #1565c0;
  transform: scale(1.1);
  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
}

.progress-slider::-webkit-slider-thumb:active {
  background: #e0e0e0;
  transform: scale(1.05);
}

/* Custom slider styles for Firefox */
.progress-slider::-moz-range-thumb {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #ffffff;
  border: 2px solid #1976d2;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  transition: all 0.2s ease;
}

.progress-slider::-moz-range-thumb:hover {
  background: #f0f0f0;
  border-color: #1565c0;
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
  background: #888;
  border-color: #666;
  cursor: not-allowed;
}

.progress-slider:disabled::-moz-range-thumb {
  background: #888;
  border-color: #666;
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
  background: linear-gradient(90deg, #1976d2, #42a5f5);
}

.progress-green {
  background: linear-gradient(90deg, #388e3c, #66bb6a);
}

.progress-orange {
  background: linear-gradient(90deg, #f57c00, #ffb74d);
}

.progress-purple {
  background: linear-gradient(90deg, #7b1fa2, #ba68c8);
}

.progress-red {
  background: linear-gradient(90deg, #d32f2f, #ef5350);
}

/* Estados de progreso */
.progress-not-started {
  background: #424242 !important;
}

.progress-just-started {
  background: linear-gradient(90deg, #ff9800, #ffb74d) !important;
}

.progress-in-progress {
  background: linear-gradient(90deg, #2196f3, #64b5f6) !important;
}

.progress-almost-done {
  background: linear-gradient(90deg, #4caf50, #81c784) !important;
}

.progress-completed {
  background: linear-gradient(90deg, #4caf50, #66bb6a) !important;
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
  color: #bbb;
  font-size: 0.8rem;
}

.page-input {
  width: 80px;
  padding: 4px 6px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 4px;
  color: #e0e0e0;
  font-size: 0.8rem;
  text-align: center;
}

.page-input:focus {
  outline: none;
  border-color: #1976d2;
  background: rgba(255, 255, 255, 0.15);
}

.page-input:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.saving-indicator {
  color: #1976d2;
  font-size: 0.8rem;
}

/* Responsive */
@media (max-width: 480px) {
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

/* Dark theme adjustments */
@media (prefers-color-scheme: dark) {
  .reading-progress-container {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.08);
  }
  
  .progress-bar-container {
    background: rgba(255, 255, 255, 0.08);
  }
}
</style>
